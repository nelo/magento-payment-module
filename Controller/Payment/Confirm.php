<?php

namespace Nelo\Bnpl\Controller\Payment;

use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Helper\ContextHelper;
use Magento\Quote\Model\QuoteFactory;
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
    private RequestInterface $_request;

    /**
     * @var ResponseInterface
     */
    private ResponseInterface $_response;

    /**
     * @var ResultFactory
     */
    protected ResultFactory $resultFactory;

    /**
     * @var MessageManagerInterface
     */
    protected MessageManagerInterface $messageManager;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var CommandPoolInterface
     */
    private CommandPoolInterface $commandPool;

    /**
     * @var Session
     */
    private Session $checkoutSession;

    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * @var MethodInterface
     */
    private $method;

    /**
     * @var PaymentDataObjectFactory
     */
    private $paymentDataObjectFactory;

    /**
     * @var QuoteFactory
     */
    private QuoteFactory $quoteFactory;

    /**
     * Confirm constructor.
     *
     * @param Context                  $context
     * @param Session                  $checkoutSession
     * @param MethodInterface          $method
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param CommandPoolInterface     $commandPool
     * @param QuoteFactory             $quoteFactory
     * @param LoggerInterface          $logger
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        MethodInterface $method,
        PaymentDataObjectFactory $paymentDataObjectFactory,
        OrderRepositoryInterface $orderRepository,
        CommandPoolInterface $commandPool,
        QuoteFactory $quoteFactory,
        LoggerInterface $logger
    ) {
        $this->_request                 = $context->getRequest();
        $this->_response                = $context->getResponse();
        $this->messageManager           = $context->getMessageManager();
        $this->resultFactory            = $context->getResultFactory();
        $this->commandPool              = $commandPool;
        $this->checkoutSession          = $checkoutSession;
        $this->orderRepository          = $orderRepository;
        $this->method                   = $method;
        $this->paymentDataObjectFactory = $paymentDataObjectFactory;
        $this->quoteFactory             = $quoteFactory;
        $this->logger                   = $logger;
    }

    /**
     * @return ResponseInterface|ResultInterface|void
     */
    public function execute()
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        try {
            $response = $this->_request->getParams();
            $orderId = $response[self::RESPONSE_REFERENCE];
            /** @var Order $order */
            $order = $this->orderRepository->get($orderId);

            if($orderId && isset($response[self::RESPONSE_PAYMENT_UUID])){ //payment was authorized
                $payment = $order->getPayment();
                ContextHelper::assertOrderPayment($payment);
                if ($payment->getMethod() === $this->method->getCode()) {
                    if ($order->getState() == Order::STATE_PENDING_PAYMENT) {
                        $this->commandPool->get('capture')->execute(
                            [
                                'reference' => $orderId,
                                'paymentUuid' => $response[self::RESPONSE_PAYMENT_UUID],
                            ]
                        );
                    }
                    return $resultRedirect->setPath('checkout/onepage/success');
                }
            } else {
                return $this->handleCancel($order);
            }
//
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->logger->critical($e->getMessage());
            return $resultRedirect->setPath('checkout/onepage/failure');
        }
    }

    /**
     * @param Order $order
     * @return Redirect
     * @throws Exception
     */
    private function handleCancel(Order $order): Redirect
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $order->setState(Order::STATE_CANCELED);
        $order->setStatus(Order::STATE_CANCELED);
        $this->orderRepository->save($order);

        //restore the cart, because magento default behavior is remove all items from the cart
        $lastQuoteId = $this->checkoutSession->getLastQuoteId();
        $quote = $this->quoteFactory->create()->loadByIdWithoutStore($lastQuoteId);
        if(!$quote->getId()) {
            $this->logger->critical('======= no $quote->getId()');
            /** @var Redirect $resultRedirect */
            return $resultRedirect->setPath('checkout/onepage/failure');
        }
        $quote->setIsActive(true)->setReservedOrderId(null)->save();
        $this->checkoutSession->replaceQuote($quote);

        /** @var Redirect $resultRedirect */
        return $resultRedirect->setPath('checkout/cart');
    }
}
