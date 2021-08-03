<?php

namespace Nelo\Bnpl\Model;

class LocaleOption implements \Magento\Framework\Option\ArrayInterface
{
    const ES_MX = 'es-MX';
    const EN_US = 'en-US';
    const BROWSER_LOCALE = 'browser_locale';
    const STORE_LOCALE = 'store_locale';

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            $this::ES_MX          => $this::ES_MX,
            $this::EN_US          => $this::EN_US,
            $this::BROWSER_LOCALE => 'Browser locale',
            $this::STORE_LOCALE   => 'Store locale'
        ];
    }
}
