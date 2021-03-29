<?php

class RicardoMartins_PagSeguro_Model_Carrier_Kiosk
	extends Mage_Shipping_Model_Carrier_Abstract
	// implements Mage_Shipping_Model_Carrier_Interface
{
	protected $_code = "rm_pagseguro";
	
	public function collectRates(Mage_Shipping_Model_Rate_Request $request)
	{
		if(!$this->isAvailable())
        {
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
	 *
	 **/
	public function isAvailable()
	{
		return Mage::registry("rm_pagseguro_kiosk_order_creation_shipping_data")
                    ? true
                    : false;
	}
}
