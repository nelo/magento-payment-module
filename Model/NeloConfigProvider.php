<?php

namespace Nelo\Bnpl\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\UrlInterface;
use Magento\Payment\Helper\Data as PaymentHelper;

/**
 * Class NeloConfigProvider
 *
 * @package Nelo\Bnpl\Model
 */
class NeloConfigProvider implements ConfigProviderInterface
{
    /**
     * @var ResolverInterface
     */
    protected ResolverInterface $localeResolver;

    /**
     * @var PaymentHelper
     */
    protected PaymentHelper $paymentHelper;

    /**
     * @var UrlInterface
     */
    protected UrlInterface $urlBuilder;

    /**
     * @var ConfigInterface
     */
    private ConfigInterface $config;

    /**
     * NeloConfigProvider constructor.
     *
     * @param ResolverInterface $localeResolver
     * @param PaymentHelper     $paymentHelper
     * @param UrlInterface      $urlBuilder
     */
    public function __construct(
        ResolverInterface $localeResolver,
        PaymentHelper $paymentHelper,
        UrlInterface $urlBuilder,
        ConfigInterface $config
    ) {
        $this->localeResolver = $localeResolver;
        $this->paymentHelper  = $paymentHelper;
        $this->urlBuilder     = $urlBuilder;
        $this->config         = $config;
    }

    /**
     * @inheritdoc
     */
    public function getConfig(): array
    {
        return [
            'payment' => [
                'bnpl' => [
                    'redirectUrl'       => $this->urlBuilder->getUrl('nelo/payment/start'),
                    'publishableApiKey' => $this->config->getValue('publishable_api_key'),
                    'isSandboxMode'     => (bool)$this->config->getValue('sandbox_flag')
                ]
            ]
        ];
    }
}
