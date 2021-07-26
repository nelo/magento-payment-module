<?php
namespace Nelo\Bnpl\Gateway\Validator;

use Magento\Framework\App\ObjectManager;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Psr\Log\LoggerInterface;

/**
 * Class CurrencyValidator
 * This class responsible for the currency validation
 *
 * @package Nelo\Bnpl\Gateway\Validator
 */
class CurrencyValidator extends AbstractResponseValidator
{
    /**
     * Injected config object
     *
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Inject config object and result factory
     *
     * @param ResultInterfaceFactory $resultFactory
     * @param ConfigInterface $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        ConfigInterface $config,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->logger = $logger;
        parent::__construct($resultFactory);
    }

    /**
     * Performs domain-related validation for business object
     *
     * @param array $validationSubject
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $errorMessages = [];
        $validationResult = true;

        $availableCurrencies = explode(
            ',',
            $this->config->getValue('specificcurrency')
        );

        if (!in_array($validationSubject['currency'], $availableCurrencies)) {
            $validationResult = false;
            $errorMessages = [__('The (#%1) currency is not supported by this payment method. Supported currencies are (#%2)',
                $validationSubject['currency'], $availableCurrencies)];
            $this->logger->info('The ' . $validationSubject['currency'] . ' currency is not supported by Nelo payment.');
        }
        
        return $this->createResult($validationResult, $errorMessages);
    }
}
