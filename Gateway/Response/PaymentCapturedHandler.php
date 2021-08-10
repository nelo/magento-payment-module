<?php

namespace Nelo\Bnpl\Gateway\Response;

use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
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
     */
    public function __construct(
        LoggerInterface $logger,
        OrderRepositoryInterface $ordersRepository,
        InvoiceService $invoiceService,
        TransactionFactory $transactionFactory,
        TransactionSearchResultInterfaceFactory $transactionSearchResultFactory,
        TransactionRepositoryInterface $transactionsRepository,
        OrderPaymentRepositoryInterface $paymentsRepository,
        InvoiceRepositoryInterface $invoiceRepository
    ) {
        $this->logger                         = $logger;
        $this->ordersRepository               = $ordersRepository;
        $this->invoiceService                 = $invoiceService;
        $this->transactionFactory             = $transactionFactory;
        $this->transactionSearchResultFactory = $transactionSearchResultFactory;
        $this->transactionsRepository         = $transactionsRepository;
        $this->paymentsRepository             = $paymentsRepository;
        $this->invoiceRepository              = $invoiceRepository;
    }

    /**
     * @param array $handlingSubject
     * @param array $response
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentId = $handlingSubject['paymentUuid'];
        $orderId = $handlingSubject['reference'];
        $order = $this->ordersRepository->get($orderId);

        if($this->getTransaction($orderId, $paymentId) == NULL) { // Making our module idempotent too
            $this->updateOrderStatus($order, $paymentId);
            $this->addTransactionToOrder($order, $handlingSubject['paymentUuid']);
            $this->addPurchaseInvoiceToOrder($order, $handlingSubject['paymentUuid']);
        }

    }

    private function updateOrderStatus(Order $order, $paymentId) {
        $order->setState(Order::STATE_PROCESSING);
        $order->setStatus(Order::STATE_PROCESSING);
        $comment = __('Order #%1 in processing state. Transaction id #%2 was successful.', $order->getId(), $paymentId);
        $order->addStatusToHistory(Order::STATE_PROCESSING, $comment,FALSE);
        $this->ordersRepository->save($order);
        $this->logger->info(__FUNCTION__ . ': Order with id ' . $order->getId() . ' was paid and status was set to processing.' );
    }

    private function addTransactionToOrder(Order $order, $paymentId): void {
        $payment = $order->getPayment();
        $payment->setTransactionId($paymentId);
        $payment->setLastTransId($paymentId);
        $transaction = $payment->addTransaction(Payment\Transaction::TYPE_CAPTURE, null, TRUE);
        $order->setExtOrderId($paymentId);

        $this->paymentsRepository->save($payment);
        $this->transactionsRepository->save($transaction);
        $this->ordersRepository->save($order);

        $this->logger->info(__FUNCTION__ . ': Transaction ' . $paymentId . ' added to the order ' . $order->getId());
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
                $this->invoiceRepository->save($invoice);

                $order->addCommentToStatusHistory('Automatically INVOICED', FALSE);
                $transaction = $this->transactionFactory->create()->addObject($invoice)->addObject($invoice->getOrder());
                $transaction->save();
            }
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
