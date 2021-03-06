<?php

namespace Nelo\Bnpl\Gateway\Command;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Gateway\Validator\ValidatorInterface;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Command\Result\ArrayResultFactory;

/**
 * Class CreateCheckoutCommand
 *
 * @package Nelo\Bnpl\Gateway\Command
 */
class CaptureCommand implements CommandInterface
{
    /**
     * @var BuilderInterface
     */
    private $requestBuilder;

    /**
     * @var TransferFactoryInterface
     */
    private $getPaymentTransferFactory;

    /**
     * @var TransferFactoryInterface
     */
    private $capturePaymentTransferFactory;

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
     * @var HandlerInterface
     */
    protected $paymentCapturedHandler;

    /**
     * Constructor
     *
     * @param BuilderInterface         $requestBuilder
     * @param TransferFactoryInterface $getPaymentTransferFactory
     * @param TransferFactoryInterface $capturePaymentTransferFactory
     * @param ClientInterface          $client
     * @param ArrayResultFactory       $resultFactory
     * @param ValidatorInterface       $validator
     */
    public function __construct(
        BuilderInterface $requestBuilder,
        TransferFactoryInterface $getPaymentTransferFactory,
        TransferFactoryInterface $capturePaymentTransferFactory,
        ClientInterface $client,
        ArrayResultFactory $resultFactory,
        ValidatorInterface $validator,
        HandlerInterface $paymentCapturedHandler
    ) {
        $this->requestBuilder                = $requestBuilder;
        $this->getPaymentTransferFactory     = $getPaymentTransferFactory;
        $this->capturePaymentTransferFactory = $capturePaymentTransferFactory;
        $this->client                        = $client;
        $this->resultFactory                 = $resultFactory;
        $this->validator                     = $validator;
        $this->paymentCapturedHandler        = $paymentCapturedHandler;
    }

    /**
     * @param array $commandSubject
     * @return void
     * @throws CommandException
     * @throws ClientException
     * @throws ConverterException
     */
    public function execute(array $commandSubject)
    {
        $getPaymentTransfer = $this->getPaymentTransferFactory->create($this->requestBuilder->build($commandSubject));
        $response  = $this->client->placeRequest($getPaymentTransfer);
        $getPaymentResult = $this
            ->validator
            ->validate(array_merge($commandSubject, ['receivedReference' => $response['reference']]));

        if (!$getPaymentResult->isValid()) {
            throw new CommandException(__(implode("\n", $getPaymentResult->getFailsDescription())));
        }

        $capturePaymentTransfer = $this->capturePaymentTransferFactory->create($this->requestBuilder->build($commandSubject));
        // capture payment respond with empty body
        $this->client->placeRequest($capturePaymentTransfer);
        $this->paymentCapturedHandler->handle($commandSubject, []);
    }
}
