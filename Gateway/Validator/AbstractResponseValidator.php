<?php

namespace Nelo\Bnpl\Gateway\Validator;

use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Payment\Gateway\Validator\ValidatorInterface;

/**
 * Class AbstractResponseValidator
 */
abstract class AbstractResponseValidator implements ValidatorInterface
{
    /**
     * Redirect Url
     */
    const REDIRECT_URL = 'redirectUrl';

    /**
     * @var ResultInterfaceFactory
     */
    private $resultInterfaceFactory;

    /**
     * @param ResultInterfaceFactory $resultFactory
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory
    ) {
        $this->resultInterfaceFactory = $resultFactory;
    }

    /**
     * Factory method
     *
     * @param bool $isValid
     * @param array $fails
     * @param array $errorCodes
     * @return ResultInterface
     */
    protected function createResult(bool $isValid, array $fails = [], array $errorCodes = []): ResultInterface
    {
        return $this->resultInterfaceFactory->create(
            [
                'isValid' => $isValid,
                'failsDescription' => $fails,
                'errorCodes' => $errorCodes
            ]
        );
    }
}
