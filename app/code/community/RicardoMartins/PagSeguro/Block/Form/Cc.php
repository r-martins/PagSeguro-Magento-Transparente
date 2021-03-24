<?php
/**
 * PagSeguro Transparente Magento
 * Form CC Block Class
 *
 * @category    RicardoMartins
 * @package     RicardoMartins_PagSeguro
 * @author      Ricardo Martins
 * @copyright   Copyright (c) 2015 Ricardo Martins (http://r-martins.github.io/PagSeguro-Magento-Transparente/)
 * @license     https://opensource.org/licenses/MIT MIT License
 */
class RicardoMartins_PagSeguro_Block_Form_Cc extends Mage_Payment_Block_Form_Cc
{
    /**
     * Set block template
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('ricardomartins_pagseguro/form/cc.phtml');

        if($this->helper("ricardomartins_pagseguro")->isMultiCcEnabled())
        {
            $this->setTemplate('ricardomartins_pagseguro/form/multi-cc.phtml');
        }
    }

    /**
     * Insert module's javascript on rendering, only if it wasn't inserted before
     * @return Mage_Core_Block_Abstract
     */
    protected function _prepareLayout()
    {
        //adicionaremos o JS do pagseguro na tela que usará o bloco de cartao logo após o <body>
        $head = Mage::app()->getLayout()->getBlock('after_body_start');

        if ($head && false == $head->getChild('js_pagseguro')) {
            $scriptBlock = Mage::helper('ricardomartins_pagseguro')->getPagSeguroScriptBlock();
            $head->append($scriptBlock);
        }

        return parent::_prepareLayout();
    }

    /**
     * Check if Date of Birthday should be visible
     * You can set up it on Payment Methods->PagSeguro Cartão de Crédito
     * @return bool
     */
    public function isDobVisible()
    {
        $ownerDobAttribute = Mage::getStoreConfig('payment/rm_pagseguro_cc/owner_dob_attribute');
        return empty($ownerDobAttribute);
    }

    /**
     * Retrieve credit card expire years
     *
     * @return array
     */
    public function getCcYears()
    {
        $years = $this->getData('cc_years');
        if (null === $years) {
            $years = Mage::helper('ricardomartins_pagseguro/params')->getYears();
            $years = array(0=>$this->__('Year'))+$years;
            $this->setData('cc_years', $years);
        }

        return $years;
    }

    /**
     * Create card form block and retrieve its HTML
     */
    public function createCardFieldsBlock($cardIndex, $params = array())
    {
        $showSummary = (is_array($params) && isset($params["_show_summary"])) 
                        ? $params["_show_summary"] 
                        : true;

        return $this->getLayout()
                    ->createBlock('ricardomartins_pagseguro/form_cc_cardFields')
                    ->setTemplate('ricardomartins_pagseguro/form/cc/card-fields.phtml')
                    ->setCardIndex($cardIndex)
                    ->setShowSummary($showSummary)
                    ->setParentFormBlock($this);
    }

    /**
     * Retrieve grand total value
     * @return float
     */
    public function getGrandTotal()
    {
        if(!$this->getData("grand_total"))
        {
            $this->setData("grand_total", $this->helper('checkout/cart')->getQuote()->getGrandTotal());
        }

        return $this->getData("grand_total");
    }
}
