<?php
/**
 * Class RicardoMartins_PagSeguro_Model_Recurring - Responsible for Recurring payments operations with PagSeguro
 *
 * @author    Ricardo Martins
 * @copyright 2020 Magenteiro
 */
class RicardoMartins_PagSeguro_Model_Recurring extends RicardoMartins_PagSeguro_Model_Abstract
{
    /** @var RicardoMartins_PagSeguro_Helper_Recurring $_helper */
    protected $_helper;

    public function __construct()
    {
        $this->_helper = Mage::helper('ricardomartins_pagseguro/recurring');
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     */
    public function createPlanFromProduct($product)
    {
       if (!$product->isRecurring()) {
           return false;
       }

       $params = array(
         'reference' => $this->_helper->getProductReference($product),
         'preApprovalName' => $product->getName(),
         'preApprovalCharge' => '',
         'preApprovalPeriod' => '',
         'preApprovalAmountPerPayment' => '',
         'preApprovalMembershipFee' => '',
         'preApprovalTrialPeriodDuration' => '',
         'preApprovalExpirationValue' => '',
         'preApprovalExpirationUnit' => '',
         'preApprovalDetails' => $product->getShortDescription(),
         'preApprovalMaxTotalAmount' =>'',
         'preApprovalCancelURL' =>'',
         'reviewURL' =>'',
         'maxUses' =>'',
         'receiver[email]' => '',
       );
    }

}