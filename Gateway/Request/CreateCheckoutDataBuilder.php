<?php

namespace Nelo\Bnpl\Gateway\Request;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Sales\Model\Order\Payment;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class CreateCheckoutDataBuilder
 *
 * @package Nelo\Bnpl\Gateway\Request
 */
class CreateCheckoutDataBuilder extends AbstractDataBuilder
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * CreateCheckoutDataBuilder constructor.
     *
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        UrlInterface $urlBuilder
    ) {
        $this->storeManager = $storeManager;
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
            self::ORDER => [
                self::REFERENCE => $order->getId(),
                self::TOTAL_AMOUNT => [
                    self::AMOUNT => ((float)SubjectReader::readAmount($buildSubject)) * 100,
                    self::CURRENCY_CODE => 'MXN'
                ]
            ],
            self::REDIRECT_CONFIRM_URL => $this->urlBuilder->getUrl('nelo/payment/confirm'),
            self::REDIRECT_CANCEL_URL => $this->urlBuilder->getUrl('nelo/payment/confirm')
        ];
    }
}
