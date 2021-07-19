<?php

namespace Nelo\Bnpl\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
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
     * Nelo Logo
     */
    const NELO_LOGO_SRC = 'https://assets.website-files.com/60738c4cc5ee7fcb4d666020/607861e85ed0f047531fd842_wordmark.svg';

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
     * NeloConfigProvider constructor.
     *
     * @param ResolverInterface $localeResolver
     * @param PaymentHelper     $paymentHelper
     * @param UrlInterface      $urlBuilder
     */
    public function __construct(
        ResolverInterface $localeResolver,
        PaymentHelper $paymentHelper,
        UrlInterface $urlBuilder
    ) {
        $this->localeResolver = $localeResolver;
        $this->paymentHelper  = $paymentHelper;
        $this->urlBuilder     = $urlBuilder;
    }

    /**
     * @inheritdoc
     */
    public function getConfig(): array
    {
        return [
            'payment' => [
                'bnpl' => [
                    'redirectUrl' => $this->urlBuilder->getUrl('nelo/payment/start'),
                    'logoSrc' => self::NELO_LOGO_SRC
                ]
            ]
        ];
    }
}
