<?php

namespace Nelo\Bnpl\Controller\Payment;

use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Helper\ContextHelper;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;


/**
 * Class Confirm
 * @package Nelo\Bnpl\Controller\Payment
 */
class Confirm implements ActionInterface
{
    const RESPONSE_PAYMENT_UUID = 'paymentUuid';
    const RESPONSE_REFERENCE = 'reference';

    /**
     * @var RequestInterface
     */
    private $_request;

    /**
     * @var ResponseInterface
     */
    private $_response;

    /**
     * @var MessageManagerInterface
     */
    protected $messageManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var CommandPoolInterface
     */
    private $commandPool;

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var MethodInterface
     */
    private $method;

    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var UrlInterface
     */
    private $urlInterface;

    /**
     * @var OrderResource
     */
    private $resource;

    /**
     * @var OrderManagementInterface
     */
    private $orderManager;

    /**
     * Confirm constructor.
     *
     * @param Context $context
     * @param Session $checkoutSession
     * @param MethodInterface $method
     * @param OrderRepositoryInterface $orderRepository
     * @param CommandPoolInterface $commandPool
     * @param QuoteFactory $quoteFactory
     * @param LoggerInterface $logger
     * @param ConfigInterface $config
     * @param UrlInterface $urlInterface
     * @param OrderResource $resource
     * @param OrderManagementInterface $orderManager
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        MethodInterface $method,
        OrderRepositoryInterface $orderRepository,
        CommandPoolInterface $commandPool,
        QuoteFactory $quoteFactory,
        LoggerInterface $logger,
        ConfigInterface $config,
        UrlInterface $urlInterface,
        OrderResource $resource,
        OrderManagementInterface $orderManager
    ) {
        $this->_request                 = $context->getRequest();
        $this->_response                = $context->getResponse();
        $this->messageManager           = $context->getMessageManager();
        $this->commandPool              = $commandPool;
        $this->checkoutSession          = $checkoutSession;
        $this->orderRepository          = $orderRepository;
        $this->method                   = $method;
        $this->quoteFactory             = $quoteFactory;
        $this->logger                   = $logger;
        $this->config                   = $config;
        $this->urlInterface             = $urlInterface;
        $this->resource                 = $resource;
        $this->orderManager             = $orderManager;
    }

    /**
     * @return ResponseInterface|ResultInterface|void
     */
    public function execute()
    {
        try {
            $response = $this->_request->getParams();
            $incrementalId = $response[self::RESPONSE_REFERENCE];
            $id = $this->getRealId($incrementalId);
            if(!$id) {
                $id = $this->checkoutSession->getLastOrderId();
            }

            if($incrementalId && $id) {
                /**@var Order $order */
                $order = $this->orderRepository->get($id);
                if ($order && $order->getState() == Order::STATE_PENDING_PAYMENT &&
                    isset($response[self::RESPONSE_PAYMENT_UUID])) { //payment was authorized
                    $payment = $order->getPayment();
                    ContextHelper::assertOrderPayment($payment);
                    if ($payment->getMethod() === $this->method->getCode()) {
                        $this->commandPool->get('capture')->execute(
                            [
                                'magentoOrderId' => $order->getId(),
                                'reference'   => $incrementalId,
                                'paymentUuid' => $response[self::RESPONSE_PAYMENT_UUID],
                            ]
                        );
                        return $this->setRedirect('redirect_on_nelo_success');
                    }
                } else {
                    $this->orderManager->cancel($order->getId());
                    //restore the cart, because magento default behavior is remove all items from the cart
                    $lastQuoteId = $this->checkoutSession->getLastQuoteId();
                    $quote = $this->quoteFactory->create()->loadByIdWithoutStore($lastQuoteId);
                    if (!$quote->getId()) {
                        return $this->setRedirect('redirect_on_unexpected_error');
                    } else {
                        $quote->setIsActive(true)->setReservedOrderId(null)->save();
                        $this->checkoutSession->replaceQuote($quote);
                        return $this->setRedirect('redirect_on_nelo_fail');
                    }
                }
            }
        } catch (Exception $e) {
            $this->logger->critical("An error occurred when receive the redirect from Nelo.");
            $this->logger->critical($e->getMessage());
            $this->logger->critical($e->getTraceAsString());
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->logger->critical($e->getMessage());
            if(isset($order)) {
                $this->orderManager->cancel($order->getId());
                return $this->setRedirect('redirect_on_unexpected_error');
            }
        }
    }

    private function setRedirect(string $configValue) {
        return $this->_response->setRedirect($this->urlInterface->getBaseUrl() . $this->config->getValue($configValue));
    }

    /**
     * Get order id by order increment id.
     *
     * @param string|int $incrementalId
     * @return int
     */
    public function getRealId($incrementalId): int
    {
        $result = 0;
        try {
            /** @var  AdapterInterface $adapter */
            $adapter = $this->resource->getConnection();
            $bind = [':increment_id' => $incrementalId];
            /** @var Select $select */
            $select = $adapter->select();
            $select->from($this->resource->getMainTable(), $this->resource->getIdFieldName())
                ->where('increment_id = :increment_id');
            $entityId = $adapter->fetchOne($select, $bind);
            if ($entityId > 0) {
                $result = (int)$entityId;
            }
        } catch (Exception $e) {
            $result = 0;
        }

        return $result;
    }
}
