<?php
/**
 * PagSeguro Transparente Magento
 * Form DirectPayment Block Class
 *
 * @category    RicardoMartins
 * @package     RicardoMartins_PagSeguro
 * @author      Ricardo Martins
 * @copyright   Copyright (c) 2015 Ricardo Martins (http://r-martins.github.io/PagSeguro-Magento-Transparente/)
 * @license     https://opensource.org/licenses/MIT MIT License
 */
class RicardoMartins_PagSeguro_Block_Form_Directpayment extends Mage_Core_Block_Template
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('ricardomartins_pagseguro/form/directpayment.phtml');
    }

    protected function _toHtml()
    {
//        avoids block being inserted twice
        if (false == Mage::registry('directpayment_loaded')) {
            Mage::register('directpayment_loaded', true);
            return parent::_toHtml();
        }

        return '';
    }
}