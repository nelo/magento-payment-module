<?php

namespace Nelo\Bnpl\Gateway\Validator;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

/**
 * Class CreateCheckoutValidator
 * @package Nelo\Bnpl\Gateway\Validator
 */
class CreateCheckoutValidator extends AbstractResponseValidator
{

    /**
     * The redirectUrl field in create checkout response
     */
    const RESPONSE_REDIRECT_URL = 'redirectUrl';

    /**
     * @param ResultInterfaceFactory $resultFactory
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory
    ) {
        parent::__construct($resultFactory);
    }

    /**
     * @param array $validationSubject
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $response         = SubjectReader::readResponse($validationSubject);
        $errorMessages    = [];
        $validationResult = $this->validateRedirectUrl($response);

        if (!$validationResult) {
            $errorMessages = [__('Something went wrong when get pay url.')];
        }

        return $this->createResult($validationResult, $errorMessages);
    }

    /**
     * Validate Order Id
     *
     * @param array $response
     * @return boolean
     */
    private function validateRedirectUrl(array $response): bool
    {
        return isset($response[self::RESPONSE_REDIRECT_URL]);
    }
}
