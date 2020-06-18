<?php
class RicardoMartins_PagSeguro_Helper_Recurring extends Mage_Core_Helper_Abstract
{

    /**
     * Create a product name to be used and displayed in PagSeguro dashboard
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     *
     * @return string
     * @throws Mage_Core_Exception
     */
    public function getProductName($profile)
    {
        $product = Mage::getSingleton('catalog/product')->load($profile->getOrderItemInfo()['product_id']);
        if (!$product->isRecurring) {
            Mage::throwException('O produto não contém um perfil de recorrência.');
        }

        $name = $product->getName();
        $reference = $profile->getAdditionalInfo('reference') . '|';
        $name = ($profile->getScheduleDescription()) ? $profile->getScheduleDescription() : $name;
        $sku = $product->getSku();
        $suffix = '|' . $sku . ' - ';

        return substr($reference . $suffix . $name, 0, 100);
    }

    /**
     * Convert Magento's Recurring Profile Period Unit and frequency to PagSeguro's supported standard.
     * Return false if not supported.
     *
     * @param Mage_Payment_Model_Recurring_Profile|array $profile
     *
     * @return bool|string
     */
    public function getPagSeguroPeriod($profile)
    {
        if (is_array($profile)) {
            $obj = new Varien_Object();
            $profile = $obj->addData($profile);
        }

        $unit = $profile->getPeriodUnit();
        $period = $profile->getPeriodFrequency();

        if ($period <= 0) {
            return false;
        }

        switch ($unit) {
            case \Mage_Payment_Model_Recurring_Profile::PERIOD_UNIT_DAY:
                if ($period % 7 == 0) {
                    return \RicardoMartins_PagSeguro_Model_Recurring::PREAPPROVAL_PERIOD_WEEKLY;
                }
                break;
            case Mage_Payment_Model_Recurring_Profile::PERIOD_UNIT_WEEK:
                if ($period == 1) {
                    return \RicardoMartins_PagSeguro_Model_Recurring::PREAPPROVAL_PERIOD_WEEKLY;
                }
                break;
            case Mage_Payment_Model_Recurring_Profile::PERIOD_UNIT_SEMI_MONTH:
                return false;
            case Mage_Payment_Model_Recurring_Profile::PERIOD_UNIT_MONTH:
                switch ($period) {
                    case 1:
                        return \RicardoMartins_PagSeguro_Model_Recurring::PREAPPROVAL_PERIOD_MONTHLY;
                    case 2:
                        return \RicardoMartins_PagSeguro_Model_Recurring::PREAPPROVAL_PERIOD_BIMONTHLY;
                    case 3:
                        return \RicardoMartins_PagSeguro_Model_Recurring::PREAPPROVAL_PERIOD_TRIMONTHLY;
                    case 6:
                        return \RicardoMartins_PagSeguro_Model_Recurring::PREAPPROVAL_PERIOD_SEMIANNUALLY;
                    case 12:
                        return \RicardoMartins_PagSeguro_Model_Recurring::PREAPPROVAL_PERIOD_YEARLY;
                }
                break;
                case Mage_Payment_Model_Recurring_Profile::PERIOD_UNIT_YEAR:
                    if ($period == 1) {
                        return \RicardoMartins_PagSeguro_Model_Recurring::PREAPPROVAL_PERIOD_YEARLY;
                    }
        }

        return false;
    }

    /**
     * Convert trial period to days
     * @param $profile
     *
     * @return float|int
     */
    public function getTrialPeriodDuration($profile)
    {
        $unit = $profile->getTrialPeriodUnit();
        $frequency = $profile->getTrialPeriodFrequency();
        switch ($unit) {
            case Mage_Payment_Model_Recurring_Profile::PERIOD_UNIT_DAY:
                return $frequency;
            case Mage_Payment_Model_Recurring_Profile::PERIOD_UNIT_WEEK:
                return $frequency * 7;
            case Mage_Payment_Model_Recurring_Profile::PERIOD_UNIT_SEMI_MONTH:
                return $frequency * 14;
            case Mage_Payment_Model_Recurring_Profile::PERIOD_UNIT_MONTH:
                return $frequency * 30;
            case Mage_Payment_Model_Recurring_Profile::PERIOD_UNIT_YEAR:
                return $frequency * 365;
        }

        return 0;
    }

    /**
     * @param Mage_Payment_Model_Recurring_Profile $profile
     */
    public function getCreatePlanParams($profile)
    {
        $params = array();

        $params['reference'] = $profile->getAdditionalInfo('reference');
        $params['preApprovalName'] = $this->getProductName($profile);
        $params['preApprovalCharge'] = 'AUTO';
        $params['preApprovalPeriod'] = $this->getPagSeguroPeriod($profile);

        $params['preApprovalAmountPerPayment'] = $profile->getBillingAmount() + $profile->getShippingAmount();
        $params['preApprovalAmountPerPayment'] = number_format($params['preApprovalAmountPerPayment'], 2, '.', '');

        $params['preApprovalMembershipFee'] = $profile->getInitAmount();
        $params['preApprovalMembershipFee'] = number_format($params['preApprovalMembershipFee'], 2, '.', '');


        if ($profile->getTrialPeriodUnit()) {
            $params['preApprovalTrialPeriodDuration'] = $this->getTrialPeriodDuration($profile);
        }

        $params['preApprovalMaxTotalAmount'] = min($params['preApprovalAmountPerPayment'] * 3, 35000);
        $params['preApprovalMaxTotalAmount'] = number_format($params['preApprovalMaxTotalAmount'], 2, '.', '');

        return $params;
    }
    
    public function writeLog($obj)
    {
        if (is_string($obj)) {
            Mage::log($obj, Zend_Log::DEBUG, 'pagseguro_recurring.log', true);
        } else {
            Mage::log(var_export($obj, true), Zend_Log::DEBUG, 'pagseguro_recurring.log', true);
        }
    }
}