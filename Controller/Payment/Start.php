<?php

namespace Nelo\Bnpl\Controller\Payment;

use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Payment\Gateway\ConfigInterface;
use Nelo\Bnpl\Gateway\Helper\TransactionReader;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Helper\ContextHelper;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\PaymentFailuresInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

/**
 * Class Start
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Start implements ActionInterface
{
    /**
     * @var RequestInterface
     */
    protected RequestInterface $_request;

    /**
     * @var ResponseInterface
     */
    protected ResponseInterface $_response;

    /**
     * @var ObjectManagerInterface
     */
    protected ObjectManagerInterface $_objectManager;


    /**
     * @var MessageManagerInterface
     */
    protected $messageManager;

    /**
     * @var CommandPoolInterface
     */
    private CommandPoolInterface $commandPool;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var PaymentDataObjectFactory
     */
    private PaymentDataObjectFactory $paymentDataObjectFactory;

    /**
     * @var Session
     */
    private Session $checkoutSession;

    /**
     * @var ResultFactory
     */
    protected ResultFactory $resultFactory;

    /**
     * @var PaymentFailuresInterface
     */
    private PaymentFailuresInterface $paymentFailures;

    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * @var ConfigInterface
     */
    private ConfigInterface $config;

    /**
     * Start constructor.
     *
     * @param Context $context
     * @param CommandPoolInterface $commandPool
     * @param LoggerInterface $logger
     * @param OrderRepositoryInterface $orderRepository
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param Session $checkoutSession
     * @param PaymentFailuresInterface|null $paymentFailures
     * @param ConfigInterface $config
     */
    public function __construct(
        Context $context,
        CommandPoolInterface $commandPool,
        LoggerInterface $logger,
        OrderRepositoryInterface $orderRepository,
        PaymentDataObjectFactory $paymentDataObjectFactory,
        Session $checkoutSession,
        PaymentFailuresInterface $paymentFailures = null,
        ConfigInterface $config
    ) {
        $this->_request                 = $context->getRequest();
        $this->_response                = $context->getResponse();
        $this->_objectManager           = $context->getObjectManager();
        $this->messageManager           = $context->getMessageManager();
        $this->resultFactory            = $context->getResultFactory();
        $this->commandPool              = $commandPool;
        $this->logger                   = $logger;
        $this->paymentDataObjectFactory = $paymentDataObjectFactory;
        $this->checkoutSession          = $checkoutSession;
        $this->paymentFailures          = $paymentFailures ?: $this->_objectManager->get(PaymentFailuresInterface::class);
        $this->orderRepository          = $orderRepository;
        $this->config                   = $config;
    }

    /**
     * @return ResponseInterface|ResultInterface|void
     */
    public function execute()
    {
        try {
            $orderId = $this->checkoutSession->getLastOrderId();
            if ($orderId) {
                /** @var Order $order */
                $order   = $this->orderRepository->get($orderId);
                $payment = $order->getPayment();
                ContextHelper::assertOrderPayment($payment);
                $paymentDataObject = $this->paymentDataObjectFactory->create($payment);
                $commandResult     = $this->commandPool->get('create_checkout')->execute(
                    [
                        'payment' => $paymentDataObject,
                        'amount'  => $order->getTotalDue(),
                    ]
                );

                $redirectUrl = TransactionReader::readRedirectUrl($commandResult->get());
                if ($redirectUrl) {
                    return $this->_response->setRedirect($redirectUrl);
                }
            }
        } catch (\Exception $e) {
            $this->paymentFailures->handle((int)$this->checkoutSession->getLastQuoteId(), $e->getMessage());
            $this->logger->critical($e);

            $this->messageManager->addErrorMessage(__('Sorry, but something went wrong.'));
            /** @var Redirect $resultRedirect */
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $resultRedirect->setPath($this->config->getValue("redirect_on_nelo_fail"));
        }
    }
}
