<?php

namespace Nelo\Bnpl\Gateway\Validator;

use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

/**
 * Class CaptureValidator
 *
 * @package Nelo\Bnpl\Gateway\Validator
 */
class CaptureValidator extends AbstractResponseValidator
{

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
        $validationResult = $validationSubject['reference'] == $validationSubject['receivedReference'];
        $errorMessages = [];

        if (!$validationResult) {
            $errorMessages = [__('The received order id (#%1) does not match with current order id (#%2).',
                $validationSubject['receivedReference'], $validationSubject['reference'])];
        }

        return $this->createResult($validationResult, $errorMessages);
    }
}
