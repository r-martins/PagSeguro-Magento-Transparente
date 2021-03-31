<?php
/**
 * PagSeguro Transparente Magento
 * Card Data Fields Block of Credit Card Form
 *
 * @category    RicardoMartins
 * @package     RicardoMartins_PagSeguro
 * @author      Fillipe Dutra
 * @copyright   Copyright (c) 2015 Ricardo Martins (http://r-martins.github.io/PagSeguro-Magento-Transparente/)
 * @license     https://opensource.org/licenses/MIT MIT License
 */
class RicardoMartins_PagSeguro_Block_Form_Cc_CardFields extends Mage_Core_Block_Template
{
    /**
     * Retrieve the formmated ID of a DOM element
     * @param string suffix
     * @param int|string cardIndex
     * @return string
     */
    public function getElementId($suffix)
    {
        return $this->getPaymentMethodCode() . "_cc" . $this->getCardIndex() . "_" . $suffix;
    }

    /**
     * Retrieve the formmated name of a form field
     * @param string suffix
     * @param int|string cardIndex
     * @return string
     */
    public function getFieldName($suffix)
    {
        return "payment[ps_multicc" . $this->getCardIndex() . "_" . $suffix . "]";
    }

    /**
     * Retrieve grand total value
     * @return float
     */
    public function getGrandTotal()
    {
        if (!$this->getData("grand_total")) {
            $this->setData("grand_total", $this->helper('checkout/cart')->getQuote()->getGrandTotal());
        }

        return $this->getData("grand_total");
    }

    /**
     * Format value with currency pattern
     * @param int|float value 
     * @return string
     */
    public function formatCurrency($value)
    {
        return $this->helper("core")->currency($value, true, false);
    }

    /**
     * Retrive date of birth block
     * @return RicardoMartins_PagSeguro_Block_Form_Cc_Dob
     */
    public function getDobBlockHtml()
    {
        return $this->getLayout()
                ->createBlock('ricardomartins_pagseguro/form_cc_dob')
                ->setTemplate('ricardomartins_pagseguro/form/cc/dob.phtml')
                ->setCardIndex($this->getCardIndex())
                ->setFieldIdFormat('rm_pagseguro_cc_cc' . $this->getCardIndex() . '_dob_%s')
                ->setFieldNameFormat("payment[ps_multicc" . $this->getCardIndex() . "_dob_%s]")
                ->setParentFormBlock($this)
                ->toHtml();
    }

    /**
     * Get info data from parent block
     * @param string data 
     * @return string
     */
    public function getInfoData($data)
    {
        return $this->getParentFormBlock()->getInfoData($data);
    }

    /**
     * Get payment method code from parent block
     * @return string
     */
    public function getPaymentMethodCode()
    {
        return $this->getParentFormBlock()->getMethodCode();
    }

    /**
     * Get 'is date of birth visible' flag from parent block
     * @return string
     */
    public function isDobVisible()
    {
        return $this->getParentFormBlock()->isDobVisible();
    }

    /**
     * Get months from parent block
     * @return string
     */
    public function getCcMonths()
    {
        return $this->getParentFormBlock()->getCcMonths();
    }

    /**
     * Get years from parent block
     * @return string
     */
    public function getCcYears()
    {
        return $this->getParentFormBlock()->getCcYears();
    }
}
