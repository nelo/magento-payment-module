<?php

namespace Nelo\Bnpl\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;

/**
 * Class AbstractDataBuilder
 * @package Nelo\Bnpl\Gateway\Request
 */
abstract class AbstractDataBuilder implements BuilderInterface
{
    /**
     * Http method to call Nelo API endpoint
     */
    const HTTP_METHOD = 'httpMethod';

    /**
     * The field name to set the endpoint value
     */
    const REQUEST_ENDPOINT_FIELD = 'endpoint';

    /**
     * Order object
     */
    const ORDER = 'order';

    /**
     * Merchant Ref
     */
    const REFERENCE = 'reference';

    /**
     * The total amount object
     */
    const TOTAL_AMOUNT = 'totalAmount';

    /**
     * Amount number
     */
    const AMOUNT = 'amount';

    /**
     * The currency code
     */
    const CURRENCY_CODE = 'currencyCode';

    /**
     * Return Url after confirm
     */
    const REDIRECT_CONFIRM_URL = 'redirectConfirmUrl';

    /**
     * Return Url after cancel
     */
    const REDIRECT_CANCEL_URL = 'redirectCancelUrl';

    /**@#+
     * Requests path variables key
     *
     * @const
     */
    const REQUESTS_PATH_VARIABLES_KEY = 'pathVariables';
}
