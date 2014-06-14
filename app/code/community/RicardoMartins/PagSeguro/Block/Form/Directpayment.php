<?php
class RicardoMartins_PagSeguro_Block_Form_Directpayment extends Mage_Core_Block_Template
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('ricardomartins_pagseguro/form/directpayment.phtml');
    }

    protected function _toHtml()
    {
//        avoids block for being inserted twice
        if(false == Mage::registry('directpayment_loaded')) {
            Mage::register('directpayment_loaded', true);
            return parent::_toHtml();
        }

        return '';
    }
}