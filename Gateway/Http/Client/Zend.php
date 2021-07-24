<?php

namespace Nelo\Bnpl\Gateway\Http\Client;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\ZendClientFactory;
use Magento\Framework\HTTP\ZendClient;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Payment\Gateway\Http\ConverterInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;

/**
 * Class Zend
 */
class Zend implements ClientInterface
{
    /**
     * @var ZendClientFactory
     */
    private $clientFactory;

    /**
     * @var ConverterInterface | null
     */
    private $converter;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param ZendClientFactory         $clientFactory
     * @param Logger                    $logger
     * @param ConverterInterface | null $converter
     */
    public function __construct(
        ZendClientFactory $clientFactory,
        Logger $logger,
        ConverterInterface $converter = null
    ) {
        $this->clientFactory = $clientFactory;
        $this->converter     = $converter;
        $this->logger        = $logger;
    }

    /**
     * @param TransferInterface $transferObject
     * @return array
     * @throws ClientException
     * @throws ConverterException
     * @throws \Zend_Http_Client_Exception
     */
    public function placeRequest(TransferInterface $transferObject): array
    {
        $log    = [
            'method' => $transferObject->getMethod(),
            'request_uri' => $transferObject->getUri(),
            'request' => $this->converter ?
                $this->converter->convert($transferObject->getBody()) :
                $transferObject->getBody()
        ];
        $result = [];
        $client = $this->clientFactory->create();
        $client->setConfig($transferObject->getClientConfig());
        $client->setHeaders($transferObject->getHeaders());
        $client->setUri($transferObject->getUri());
        $client->setMethod($transferObject->getMethod());
        if($transferObject->getMethod() != 'GET') {
            $client->setRawData($transferObject->getBody());
            $client->setUrlEncodeBody($transferObject->shouldEncode());
        }

        try {
            $response        = $client->request();
            $responseBody = $response->getBody();
            $result          = $responseBody ? ($this->converter ? $this->converter->convert($responseBody) : [$responseBody]) : [];
            $log['response'] = $result;
            if($response->getStatus() > 299) {
                throw new ClientException(
                    __('When interacting with Nelo an unexpected response was received.')
                );
            }
        } catch (\Zend_Http_Client_Exception $e) {
            throw new ClientException(
                __($e->getMessage())
            );
        } catch (ConverterException $e) {
            throw $e;
        } finally {
            $this->logger->debug($log);
        }

        return $result;
    }
}
