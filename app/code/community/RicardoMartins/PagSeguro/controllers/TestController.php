<?php
/**
 * PagSeguro Transparente Magento
 * Test Controller responsible for diagnostics, usually when you ask for support
 * It helps our team to detect misconfiguration and other problems when you ask for help
 *
 * @category    RicardoMartins
 * @package     RicardoMartins_PagSeguro
 * @author      Ricardo Martins
 * @copyright   Copyright (c) 2015 Ricardo Martins (http://r-martins.github.io/PagSeguro-Magento-Transparente/)
 * @license     https://opensource.org/licenses/MIT MIT License
 */
class RicardoMartins_PagSeguro_TestController extends Mage_Core_Controller_Front_Action
{
    /**
     * Bring us some information about the module configuration and version info.
     * You can remove it, but can make our team to misjudge your configuration or problem.
     */
    public function getConfigAction()
    {
        $info = array();
        $info['RicardoMartins_PagSeguro']['version'] = (string)Mage::getConfig()
                                                        ->getModuleConfig('RicardoMartins_PagSeguro')->version;
        $info['RicardoMartins_PagSeguro']['debug'] = Mage::getStoreConfigFlag('payment/pagseguro/debug');
        $info['RicardoMartins_PagSeguro']['sandbox'] = Mage::getStoreConfigFlag('payment/pagseguro/sandbox');

        if (Mage::getConfig()->getModuleConfig('RicardoMartins_PagSeguroPro')) {
            $info['RicardoMartins_PagSeguroPro']['version'] = (string)Mage::getConfig()
                                                        ->getModuleConfig('RicardoMartins_PagSeguroPro')->version;
            $info['RicardoMartins_PagSeguroPro']['key_type'] =
                (string)Mage::getStoreConfig('payment/pagseguropro/key_type');
        }

        $helper = Mage::helper('ricardomartins_pagseguro');
        $info['session_id'] = $helper->getSessionId();

        $modules = array_keys((array)Mage::getConfig()->getNode('modules')->children());
        $coreHelper = Mage::helper('core');
        foreach ($modules as $module) {
            if (false !== strpos(strtolower($module), 'pagseguro') && $coreHelper->isModuleEnabled($module)) {
                $info['pagseguro_modules'][] = $module;
            }
        }

        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody(json_encode($info));
    }
}