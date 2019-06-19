<?php
/**
 * PagSeguro Transparente Magento
 * Model Kiosk Class - responsible for kiosk payment processing
 *
 * @category    RicardoMartins
 * @package     RicardoMartins_PagSeguro
 * @author      Ricardo Martins
 * @copyright   Copyright (c) 2017 Ricardo Martins (http://r-martins.github.io/PagSeguro-Magento-Transparente/)
 * @license     https://opensource.org/licenses/MIT MIT License
 */
class RicardoMartins_PagSeguro_Model_Payment_Kiosk extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'rm_pagseguro_kiosk';
    protected $_formBlockType = 'ricardomartins_pagseguro/form_kiosk';
    protected $_infoBlockType = 'ricardomartins_pagseguro/form_info_kiosk';
    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid = true;
    protected $_canUseInternal = false;
    protected $_canUseCheckout = false;
    protected $_canUseForMultishipping = false;
    protected $_canSaveCc = false;

    /**
     * Check if module is available for current quote and customer group (if restriction is activated)
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return bool
     */
    public function isAvailable($quote = null)
    {
        $isAvailable = parent::isAvailable($quote);
        if (empty($quote)) {
            return $isAvailable;
        }

        if ($quote->getIsKiosk()) {
            return $isAvailable;
        }
        return false;
    }

}
