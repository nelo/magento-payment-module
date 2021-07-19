<?php

namespace Nelo\Bnpl\Gateway\Http;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Http\TransferBuilder;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;

/**
 * Class TransferFactory
 *
 * @package Nelo\Bnpl\Gateway\Http
 */
class TransferFactory implements TransferFactoryInterface
{
    /**@#+
     * Nelo create checkout endpoint
     *
     * @const
     */
    const CREATE_CHECKOUT_ENDPOINT = '/checkout';

    /**@#+
     * Nelo capture endpoint
     *
     * @const
     */
    const CAPTURE_PAYMENT_ENDPOINT = '/payments/%s/capture';

    /**
     * Nelo get payment endpoint
     */
    const GET_PAYMENT_ENDPOINT = '/payments/%s';

    /**
     * Http method POST
     */
    const HTTP_METHOD_POST = 'POST';

    /**
     * Http method GET
     */
    const HTTP_METHOD_GET = 'GET';

    /**
     * @var ConfigInterface
     */
    private ConfigInterface $config;

    /**
     * @var Json
     */
    private Json $serializer;

    /**
     * @var TransferBuilder
     */
    protected TransferBuilder $transferBuilder;


    /**
     * @var string
     */
    private string $method;

    /**
     * @var string
     */
    private string $endpoint;

    /**
     * TransferFactory constructor.
     *
     * @param string $method
     * @param string $endpoint
     * @param ConfigInterface $config
     * @param Json $serializer
     * @param TransferBuilder $transferBuilder
     */
    public function __construct(
        string $method,
        string $endpoint,
        ConfigInterface $config,
        Json $serializer,
        TransferBuilder $transferBuilder
    ) {
        $this->method       = $method;
        $this->endpoint     = $endpoint;
        $this->config = $config;
        $this->serializer = $serializer;
        $this->transferBuilder = $transferBuilder;
    }

    /**
     * @inheritdoc
     */
    public function create(array $request)
    {
        $requestBody = $this->serializer->serialize($request);
        return $this->transferBuilder
            ->setMethod($this->method)
            ->setHeaders($this->getHeaders())
            ->setBody($requestBody)
            ->setUri($this->getUrl($request['pathVariables'] ?? []))
            ->build();
    }

    /**
     * Get Url
     *
     * @param array $pathVariables
     * @return string
     */
    private function getUrl(array $pathVariables): string
    {
        $prefix = $this->isSandboxMode() ? 'sandbox_' : '';
        $baseUrl   = $prefix . 'payment_url';

        return rtrim($this->config->getValue($baseUrl), '/') . sprintf($this->endpoint, ...$pathVariables);
    }

    private function isSandboxMode(): bool
    {
        return (bool)$this->config->getValue('sandbox_flag');
    }

    private function getHeaders(): array
    {
        $apiKey = $this->config->getValue('api_key');
        return [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ];
    }
}
