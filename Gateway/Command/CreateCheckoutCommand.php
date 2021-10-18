<?php

namespace Nelo\Bnpl\Gateway\Command;

use Magento\Framework\Exception\LocalizedException;
use Nelo\Bnpl\Gateway\Validator\AbstractResponseValidator;
use Magento\Payment\Gateway\Command\Result\ArrayResult;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Validator\ValidatorInterface;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Command\Result\ArrayResultFactory;

/**
 * Class CreateCheckoutCommand
 *
 * @package Nelo\Bnpl\Gateway\Command
 */
class CreateCheckoutCommand implements CommandInterface
{
    /**
     * @var BuilderInterface
     */
    private $requestBuilder;

    /**
     * @var TransferFactoryInterface
     */
    private $transferFactory;

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var ArrayResultFactory
     */
    private $resultFactory;

    /**
     * Constructor
     *
     * @param BuilderInterface         $requestBuilder
     * @param TransferFactoryInterface $transferFactory
     * @param ClientInterface          $client
     * @param ArrayResultFactory       $resultFactory
     * @param ValidatorInterface       $validator
     */
    public function __construct(
        BuilderInterface $requestBuilder,
        TransferFactoryInterface $transferFactory,
        ClientInterface $client,
        ArrayResultFactory $resultFactory,
        ValidatorInterface $validator
    ) {
        $this->requestBuilder  = $requestBuilder;
        $this->transferFactory = $transferFactory;
        $this->client          = $client;
        $this->resultFactory   = $resultFactory;
        $this->validator       = $validator;
    }

    /**
     * @param array $commandSubject
     * @return ArrayResult|ResultInterface|null
     * @throws CommandException
     * @throws ClientException
     * @throws ConverterException
     */
    public function execute(array $commandSubject)
    {
        $transfer = $this->transferFactory->create($this->requestBuilder->build($commandSubject));
        $response  = $this->client->placeRequest($transfer);
        $result    = $this->validator->validate(array_merge($commandSubject, ['response' => $response]));

        if (!$result->isValid()) {
            throw new CommandException(__(implode("\n", $result->getFailsDescription())));
        }

        return $this->resultFactory->create(
            [
                'array' => [
                    AbstractResponseValidator::REDIRECT_URL => $response[AbstractResponseValidator::REDIRECT_URL]
                ]
            ]
        );
    }
}
