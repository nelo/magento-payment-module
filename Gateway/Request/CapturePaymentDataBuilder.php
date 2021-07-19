<?php

namespace Nelo\Bnpl\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;

/**
 * Class CreateCheckoutDataBuilder
 *
 * @package Nelo\Bnpl\Gateway\Request
 */
class CapturePaymentDataBuilder extends AbstractDataBuilder
{

    /**
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {

        return [
            self::REQUESTS_PATH_VARIABLES_KEY => [
                $buildSubject['paymentUuid']
            ]
        ];
    }
}
