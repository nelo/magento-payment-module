<?php

namespace Nelo\Bnpl\Gateway\Response;

use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderInterfaceFactory;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Api\Data\TransactionSearchResultInterfaceFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Model\Spi\OrderResourceInterface;
use Psr\Log\LoggerInterface;

/**
 * Class TransactionCompleteHandler
 *
 * @package Nelo\Bnpl\Gateway\Response
 */
class PaymentCapturedHandler implements HandlerInterface
{

    /**
     * @var OrderRepositoryInterface
     */
    private $ordersRepository;

    /**
     * @var InvoiceService
     */
    private $invoiceService;

    /**
     * @var TransactionFactory
     */
    private $transactionFactory;

    /**
     * @var TransactionSearchResultInterfaceFactory
     */
    private $transactionSearchResultFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var TransactionRepositoryInterface
     */
    private $transactionsRepository;

    /**
     * @var OrderPaymentRepositoryInterface
     */
    private $paymentsRepository;

    /**
     * @var InvoiceRepositoryInterface
     */
    private $invoiceRepository;

    /**
     * @var OrderResourceInterface
     */
    private $orderResource;

    /**
     * @var OrderInterfaceFactory
     */
    private $orderFactory;

    /**
     * PaymentCapturedHandler constructor.
     *
     * @param LoggerInterface $logger
     * @param OrderRepositoryInterface $ordersRepository
     * @param InvoiceService $invoiceService
     * @param TransactionFactory $transactionFactory
     * @param TransactionSearchResultInterfaceFactory $transactionSearchResultFactory
     * @param TransactionRepositoryInterface $transactionsRepository
     * @param OrderPaymentRepositoryInterface $paymentsRepository
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param OrderResourceInterface $orderResource
     * @param OrderInterfaceFactory $orderFactory
     */
    public function __construct(
        LoggerInterface $logger,
        OrderRepositoryInterface $ordersRepository,
        InvoiceService $invoiceService,
        TransactionFactory $transactionFactory,
        TransactionSearchResultInterfaceFactory $transactionSearchResultFactory,
        TransactionRepositoryInterface $transactionsRepository,
        OrderPaymentRepositoryInterface $paymentsRepository,
        InvoiceRepositoryInterface $invoiceRepository,
        OrderResourceInterface $orderResource,
        OrderInterfaceFactory $orderFactory
    ) {
        $this->logger                         = $logger;
        $this->ordersRepository               = $ordersRepository;
        $this->invoiceService                 = $invoiceService;
        $this->transactionFactory             = $transactionFactory;
        $this->transactionSearchResultFactory = $transactionSearchResultFactory;
        $this->transactionsRepository         = $transactionsRepository;
        $this->paymentsRepository             = $paymentsRepository;
        $this->invoiceRepository              = $invoiceRepository;
        $this->orderResource                  = $orderResource;
        $this->orderFactory                   = $orderFactory;
    }

    /**
     * @param array $handlingSubject
     * @param array $response
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentId = $handlingSubject['paymentUuid'];
        $incrementalId = $handlingSubject['reference'];
        $order = $this->orderFactory->create();
        $this->orderResource->load($order, $incrementalId, OrderInterface::INCREMENT_ID);

        if($this->getTransaction($order->getId(), $paymentId) == NULL) { // Making our module idempotent too
            try {
                $this->updateOrderStatus($order, $paymentId);
                $this->addTransactionToOrder($order, $handlingSubject['paymentUuid']);
                $this->addPurchaseInvoiceToOrder($order, $handlingSubject['paymentUuid']);
            } catch (\Exception $e) {
                $comment = 'An exception occurred and the order has no transaction or invoice attached. But you can proceed ' .
                    ' with next steps since the payment ' . $paymentId . ' was successful in Nelo\'s side.';
                $order->addCommentToStatusHistory($comment, FALSE);
                $this->ordersRepository->save($order);
                $this->logger->critical('Next logs are only for research purpose.');
                $this->logger->critical($e);
            }
        }
    }

    private function updateOrderStatus(Order $order, $paymentId) {
        $order->setState(Order::STATE_PROCESSING);
        $order->setStatus(Order::STATE_PROCESSING);
        $comment = 'Order ' . $order->getIncrementId() . ' was moved to \'processing\' state by Nelo. Transaction ' .
            $paymentId . ' was successful.';
        $order->addStatusToHistory(Order::STATE_PROCESSING, $comment,FALSE);
        $this->ordersRepository->save($order);
        $this->logger->info(__FUNCTION__ . ': ' . $comment);
    }

    /**
     * @param Order $order
     * @param $paymentId
     * @throws \Exception
     */
    private function addTransactionToOrder(Order $order, $paymentId): void {
        $payment = $order->getPayment();
        $payment->setTransactionId($paymentId);
        $payment->setLastTransId($paymentId);
        $transaction = $payment->addTransaction(Payment\Transaction::TYPE_CAPTURE, null, TRUE);
        $order->setExtOrderId($paymentId);
        $comment = 'Transaction id ' . $paymentId . ' was added to the order ' . $order->getIncrementId() . ' by Nelo.';
        $order->addCommentToStatusHistory($comment, FALSE);

        $this->paymentsRepository->save($payment);
        $this->transactionsRepository->save($transaction);
        $this->ordersRepository->save($order);

        $this->logger->info(__FUNCTION__ . ': ' . $comment);
    }

    /**
     * @throws LocalizedException
     */
    private function addPurchaseInvoiceToOrder(Order $order, string $paymentId) {
        if($order->canInvoice()) {
            /** @var Invoice $invoice */
            $invoice = $this->invoiceService->prepareInvoice($order);
            if ($invoice && $invoice->getTotalQty()) {
                $invoice->register();
                $invoice->getOrder()->setCustomerNoteNotify(FALSE);
                $invoice->setTransactionId($paymentId);

                $comment = 'Invoice ' . $invoice->getIncrementId() . ' was added to the order ' . $order->getIncrementId() .
                    ' by Nelo.';
                $order->addCommentToStatusHistory($comment, FALSE);
                try {
                    $transaction = $this->transactionFactory->create()->addObject($invoice)->addObject($invoice->getOrder());
                    $transaction->save();
                } catch (\Exception $e) {
                    $this->logger->warning($e);
                }

                $this->invoiceRepository->save($invoice);
                $this->ordersRepository->save($order);
                $this->logger->info($comment);
            }
        } else {
            $this->logger->warning(__FUNCTION__ . ': Order ' . $order->getIncrementId() .
                ' can\'t be invoiced by Nelo, order state is ' . $order->getState() . '. Related transaction is ' . $paymentId);
        }
    }

    private function getTransaction(string $orderId, string $transactionId): ?TransactionInterface
    {
        $transactions = $this
            ->transactionSearchResultFactory
            ->create()
            ->addOrderIdFilter($orderId)
            ->getItems();

        $transaction = NULL;
        foreach ($transactions as $key => $_transaction) {
            if ($_transaction->getTxnId() == $transactionId) {
                $transaction = $_transaction;
                break;
            }
        }

        return $transaction;
    }
}
