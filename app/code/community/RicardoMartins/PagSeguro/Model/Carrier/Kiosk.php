<?php

/**
 * Class RicardoMartins_PagSeguro_Model_Carrier_Kiosk
 *
 * @author    Fillipe Dutra
 * @copyright 2021 Magenteiro
 */
class RicardoMartins_PagSeguro_Model_Carrier_Kiosk
    extends Mage_Shipping_Model_Carrier_Abstract
    implements Mage_Shipping_Model_Carrier_Interface
{
    protected $_code = "rm_pagseguro";

    /**
     * Collects the rate based on shipping data informed on registry
     *
     * @param Mage_Shipping_Model_Rate_Request $request
     *
     * @return Mage_Shipping_Model_Rate_Result
     **/
    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        if (!$this->isAvailable()) {
            return false;
        }

        $helper = Mage::helper("ricardomartins_pagseguro");

        $shippingData = Mage::registry("kiosk_order_creation_shipping_data");
        $cost = isset($shippingData->cost) ? (float) $shippingData->cost : 0.0;
        $type = isset($shippingData->type) ? (int) $shippingData->type : 3;
        
        $result = Mage::getModel("shipping/rate_result");
        $rate = Mage::getModel("shipping/rate_result_method");
        $rate->setCarrier($this->_code);
        $rate->setCarrierTitle($type == 3 ? $helper->__("Not applicable") : "Correios");
        $rate->setMethod("kiosk");
        $rate->setMethodTitle("");
        $rate->setCost($cost);
        $rate->setPrice($cost);
        $result->append($rate);

		return $result;
	}

    /**
     * Enables shipping method only if the specific registry is setted
     *
     * @return Boolean
     **/
    public function isAvailable()
    {
        return Mage::registry("rm_pagseguro_kiosk_order_creation_shipping_data") ? true : false;
    }

    /**
     * Get allowed shipping methods
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        return array();
    }

    /**
     * Check if carrier has shipping tracking option available
     *
     * @return boolean
     */
    public function isTrackingAvailable()
    {
        return false;
    }
}
