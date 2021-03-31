<?php

/**
 * Class RicardoMartins_PagSeguro_Model_System_Config_Backend_ValidateMultiCc
 *
 * @author    Fillipe Dutra
 * @copyright 2021 Magenteiro
 */
class RicardoMartins_PagSeguro_Model_System_Config_Backend_ValidateMultiCc
    extends Mage_Core_Model_Config_Data
{
    /**
     * Verifies if the app key is configured to enable 2 cards payment
     */
    protected function _beforeSave()
    {
        $key = $this->_getSavingConfigValue("pagseguropro/key");
        $sandboxKey = $this->_getSavingConfigValue("rm_pagseguro/sandbox_appkey");
        $isSandbox = $this->_getSavingConfigValue("rm_pagseguro/sandbox");

        if ($this->getValue()
            && ((!$isSandbox && strlen($key) <= 6)
                || ($isSandbox && strlen($sandboxKey) <= 6))) {
            Mage::getSingleton('adminhtml/session')->addNotice(
                __(
                    'PagSeguro: Payment with two credit cards is only '
                    . 'available in the application model. Authorize your account for free to enable it.'
                )
            );
            $this->setValue(0);
        }

        return parent::_afterSave();
    }

    /**
     * Searches for payment configuration being saved in the request
     * @param String $path
     * @return String
     */
    protected function _getSavingConfigValue($path)
    {
        list($group, $field) = explode("/", $path);
        $allData = $this->_getData("groups");

        if (isset($allData[$group]["fields"][$field]["value"])) {
            return $allData[$group]["fields"][$field]["value"];
        }

        return "";
    }
}