<?php

namespace Nelo\Bnpl\Gateway\Helper;

use Nelo\Bnpl\Gateway\Validator\AbstractResponseValidator;

/**
 * Class TransactionReader
 *
 * @package Nelo\Bnpl\Gateway\Helper
 */
class TransactionReader
{

    /**
     * Read Redirect Url from transaction data
     *
     * @param array $transactionData
     * @return string
     */
    public static function readRedirectUrl(array $transactionData): string
    {
        if (empty($transactionData[AbstractResponseValidator::REDIRECT_URL])) {
            throw new \InvalidArgumentException('Redirect Url should be provided');
        }

        return $transactionData[AbstractResponseValidator::REDIRECT_URL];
    }
}
