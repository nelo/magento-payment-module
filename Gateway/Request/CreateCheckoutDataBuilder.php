<?php

namespace Nelo\Bnpl\Gateway\Request;

use Magento\Framework\UrlInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Sales\Model\Order\Payment;

/**
 * Class CreateCheckoutDataBuilder
 *
 * @package Nelo\Bnpl\Gateway\Request
 */
class CreateCheckoutDataBuilder extends AbstractDataBuilder
{
    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * CreateCheckoutDataBuilder constructor.
     *
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        UrlInterface $urlBuilder
    ) {
        $this->urlBuilder   = $urlBuilder;
    }

    /**
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        /** @var Payment $payment */
        $payment = $paymentDO->getPayment();
        $order   = $payment->getOrder();
        return [
            self::ORDER                => [
                self::REFERENCE    => $order->getIncrementId(),
                self::TOTAL_AMOUNT => [
                    self::AMOUNT        => ((float)SubjectReader::readAmount($buildSubject)) * 100,
                    self::CURRENCY_CODE => 'MXN'
                ]
            ],
            self::CUSTOMER             => $buildSubject[self::CUSTOMER],
            self::REDIRECT_CONFIRM_URL => rtrim($this->urlBuilder->getUrl('nelo/payment/confirm'), '/'),
            self::REDIRECT_CANCEL_URL  => rtrim($this->urlBuilder->getUrl('nelo/payment/confirm'), '/') .
                '?' . self::REFERENCE . '=' . $order->getIncrementId()
        ];
    }
}
