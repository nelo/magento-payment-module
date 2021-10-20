<?php

namespace Nelo\Bnpl\Controller\Payment;

use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Helper\ContextHelper;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;


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
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var FilterBuilder
     */
    private $filterBuilder;

    /**
     * @var FilterGroupBuilder
     */
    private $filterGroupBuilder;

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
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
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
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder,
        FilterGroupBuilder $filterGroupBuilder
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
        $this->searchCriteriaBuilder    = $searchCriteriaBuilder;
        $this->filterBuilder            = $filterBuilder;
        $this->filterGroupBuilder       = $filterGroupBuilder;
    }

    /**
     * @return ResponseInterface|ResultInterface|void
     */
    public function execute()
    {
        try {
            $response = $this->_request->getParams();
            $incrementalId = $response[self::RESPONSE_REFERENCE];
            $order = $this->getOrderByIncrementId($incrementalId);

            if($incrementalId && $order && $order->getState() == Order::STATE_PENDING_PAYMENT) {
                if (isset($response[self::RESPONSE_PAYMENT_UUID])) { //payment was authorized
                    $payment = $order->getPayment();
                    ContextHelper::assertOrderPayment($payment);
                    if ($payment->getMethod() === $this->method->getCode()) {
                        if ($order->getState() == Order::STATE_PENDING_PAYMENT) {
                            $this->commandPool->get('capture')->execute(
                                [
                                    'magentoOrderId'     => $order->getEntityId(),
                                    'reference'   => $incrementalId,
                                    'paymentUuid' => $response[self::RESPONSE_PAYMENT_UUID],
                                ]
                            );
                        }
                        return $this->setRedirect('redirect_on_nelo_success');
                    }
                } else {
                    $this->handleCancel($order);
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
                $this->handleCancel($order);
                return $this->setRedirect('redirect_on_unexpected_error');
            }
        }
    }

    private function setRedirect(string $configValue) {
        return $this->_response->setRedirect($this->urlInterface->getBaseUrl() . $this->config->getValue($configValue));
    }

    public function getOrderByIncrementId($incrementId): ?OrderInterface
    {
        $filter = $this->filterBuilder->setField(OrderInterface::INCREMENT_ID)->setValue($incrementId)->create();
        /** @var FilterGroup $filterGroup */
        $filterGroup = $this->filterGroupBuilder->setFilters([$filter])->create();
        $criteria = $this->searchCriteriaBuilder->create();
        $criteria->setFilterGroups([$filterGroup]);
        $orders = $this->orderRepository->getList($criteria)->getItems();
        return count($orders)? reset($orders) : null;
    }

    /**
     * @param Order $order
     */
    private function handleCancel(Order $order)
    {
        $order->setState(Order::STATE_CANCELED);
        $order->setStatus(Order::STATE_CANCELED);
        $this->orderRepository->save($order);
    }
}
