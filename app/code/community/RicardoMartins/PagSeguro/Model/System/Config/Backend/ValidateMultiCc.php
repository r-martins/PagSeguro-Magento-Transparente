<?php

class RicardoMartins_PagSeguro_Model_System_Config_Backend_ValidateMultiCc 
    extends Mage_Core_Model_Config_Data
{
    /**
     * Verifies if the app key is configured to enable 2 cards payment
     */
    public function _beforeSave()
    {
        $helper = Mage::helper("ricardomartins_pagseguro");
        $key = $this->_getSavingConfigValue("pagseguropro/key");
        $sandboxKey = $this->_getSavingConfigValue("rm_pagseguro/sandbox_appkey");
        $isSandbox = $this->_getSavingConfigValue("rm_pagseguro/sandbox");

        if( $this->getValue() &&
            (
                (!$isSandbox && strlen($key) <= 6) ||
                ($isSandbox && strlen($sandboxKey) <= 6)
            )
        ) {
            Mage::getSingleton('core/session')->addError($helper->__('This functionality only works if the application mode is enabled.'));
            $this->setValue(0);
        }

        return parent::_afterSave();
    }

    /**
     * Searches for payment configuration being saved in the request
     * @param String $path
     * @return String
     */
    private function _getSavingConfigValue($path)
    {
        list($group, $field) = explode("/", $path);
        $allData = $this->_getData("groups");

        if(isset($allData[$group]["fields"][$field]["value"]))
        {
            return $allData[$group]["fields"][$field]["value"];
        }

        return "";
    }
}