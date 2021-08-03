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
    protected $localeResolver;

    /**
     * @var PaymentHelper
     */
    protected $paymentHelper;

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var ConfigInterface
     */
    private $config;

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
        $localeOption = $this->config->getValue('locale_option');
        $locale = null;
        if($localeOption == LocaleOption::STORE_LOCALE) {
            $locale = str_replace('_', '-', $this->localeResolver->getLocale());
        }
        else if($localeOption != LocaleOption::BROWSER_LOCALE) {
            $locale = $localeOption;
        }


        return [
            'payment' => [
                'bnpl' => [
                    'redirectUrl'       => $this->urlBuilder->getUrl('nelo/payment/start'),
                    'publishableApiKey' => $this->config->getValue('publishable_api_key'),
                    'isSandboxMode'     => (bool)$this->config->getValue('sandbox_flag'),
                    'locale'            => $locale
                ]
            ]
        ];
    }
}
