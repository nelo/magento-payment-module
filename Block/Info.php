<?php

namespace Nelo\Bnpl\Block;

use Magento\Framework\Phrase;
use Magento\Payment\Block\ConfigurableInfo;

/**
 * Class Info
 *
 * @package Nelo\Bnpl\Block
 */
class Info extends ConfigurableInfo
{
    /**
     * Returns label
     *
     * @param string $field
     * @return Phrase
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function getLabel($field)
    {
        switch ($field) {
            case 'transaction_type':
                return __('Transaction Type');
            case 'transaction_id':
                return __('Transaction ID');
            case 'response_code':
                return __('Response Code');
            case 'approve_messages':
                return __('Approve Messages');
            default:
                return parent::getLabel($field);
        }
    }
}
