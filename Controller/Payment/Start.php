<?php

namespace Nelo\Bnpl\Controller\Payment;

use Exception;
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
    protected $_request;

    /**
     * @var ResponseInterface
     */
    protected $_response;

    /**
     * @var ObjectManagerInterface
     */
    protected $_objectManager;


    /**
     * @var MessageManagerInterface
     */
    protected $messageManager;

    /**
     * @var CommandPoolInterface
     */
    private $commandPool;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var PaymentDataObjectFactory
     */
    private $paymentDataObjectFactory;

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var PaymentFailuresInterface
     */
    private $paymentFailures;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var ConfigInterface
     */
    private $config;

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
                        'payment'  => $paymentDataObject,
                        'amount'   => $order->getTotalDue(),
                        'customer' => $this->getCustomerData($order)
                    ]
                );

                $redirectUrl = TransactionReader::readRedirectUrl($commandResult->get());
                if ($redirectUrl) {
                    return $this->_response->setRedirect($redirectUrl);
                }
            }
        } catch (Exception $e) {
            $this->paymentFailures->handle((int)$this->checkoutSession->getLastQuoteId(), $e->getMessage());
            $this->logger->critical($e);
            $this->messageManager->addErrorMessage(__('Sorry, but something went wrong.'));
            return $this->setRedirect('redirect_on_nelo_fail');
        }
    }

    private function setRedirect(string $configValue) {
        return $this->_response->setRedirect($this->urlInterface->getBaseUrl() . $this->config->getValue($configValue));
    }

    private function getCustomerData(Order $order) {
        $phoneNumber = $order->getShippingAddress()->getTelephone();
        $phoneCountry = 'MX';
        if(strpos($phoneNumber, '+52') === 0){
            $phoneNumber = substr($phoneNumber, 3);
            $phoneCountry = 'MX';
        } else if(strpos($phoneNumber, '+1') === 0){
            $phoneNumber = substr($phoneNumber, 2);
            $phoneCountry = 'US';
        }
        return [
            'firstName'        => $order->getCustomerFirstname(),
            'paternalLastName' => $order->getCustomerLastname(),
            'email'            => $order->getCustomerEmail(),
            'phoneNumber'      => [
                'number'      => $phoneNumber,
                'countryIso2' => $phoneCountry
            ],
            'address'          => [
                'countryIso2' => 'MX',
                'addressMX'   => [
                    'street'     => $order->getShippingAddress()->getStreet()[0],
                    'city'       => $order->getShippingAddress()->getCity(),
                    'postalCode' => $order->getShippingAddress()->getPostcode(),
                    'state'      => $order->getBillingAddress()->getRegion()
                ]
            ]
        ];
    }
}
