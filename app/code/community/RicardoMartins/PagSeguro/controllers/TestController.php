<?php
/**
 * PagSeguro Transparente Magento
 * Test Controller responsible for diagnostics, usually when you ask for support
 * It helps our team to detect misconfiguration and other problems when you ask for help
 *
 * @category    RicardoMartins
 * @package     RicardoMartins_PagSeguro
 * @author      Ricardo Martins
 * @copyright   Copyright (c) 2017 Ricardo Martins (http://r-martins.github.io/PagSeguro-Magento-Transparente/)
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
        $helper = Mage::helper('ricardomartins_pagseguro');
        $pretty = ($this->getRequest()->getParam('pretty', true) && version_compare(PHP_VERSION, '5.4', '>='))?128:0;

        $info['RicardoMartins_PagSeguro']['version'] = (string)Mage::getConfig()
                                                        ->getModuleConfig('RicardoMartins_PagSeguro')->version;
        $info['RicardoMartins_PagSeguro']['debug'] = Mage::getStoreConfigFlag('payment/rm_pagseguro/debug');
        $info['RicardoMartins_PagSeguro']['sandbox'] = Mage::getStoreConfigFlag('payment/rm_pagseguro/sandbox');
        $info['configJs'] = json_decode($helper->getConfigJs());

        if (Mage::getConfig()->getModuleConfig('RicardoMartins_PagSeguroPro')) {
            $info['RicardoMartins_PagSeguroPro']['version'] = (string)Mage::getConfig()
                                                        ->getModuleConfig('RicardoMartins_PagSeguroPro')->version;

            $keyType = $helper->getLicenseType();
            $info['RicardoMartins_PagSeguroPro']['key_type'] = ($keyType)==''?'assinatura':$keyType;
            $info['RicardoMartins_PagSeguroPro']['key_validation'] = $this->_validateKey();

        }

        $info['compilation'] = $this->_getCompilerState();

        $info['token_consistency'] = $this->_getTokenConsistency();
        $info['session_id'] = $helper->getSessionId();
        $info['retry_active'] = $helper->isRetryActive();

        $modules = array_keys((array)Mage::getConfig()->getNode('modules')->children());
        $coreHelper = Mage::helper('core');
        foreach ($modules as $module) {
            if (false !== strpos(strtolower($module), 'pagseguro') && $coreHelper->isModuleEnabled($module)) {
                $info['pagseguro_modules'][] = $module;
            }
        }
        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody(json_encode($info, $pretty));

    }

    /**
     * Validates your PRO key. Only for tests purposes.
     * @return mixed|string
     */
    private function _validateKey()
    {
        $key = Mage::getStoreConfig('payment/pagseguropro/key');
        if (empty($key)) {
            return 'KEY IS EMPTY';
        }
            $url = 'http://ws.ricardomartins.net.br/pspro/v6/auth/' . $key;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        libxml_use_internal_errors(true);

        return curl_exec($ch);
    }

    /**
     * Get compilation config details
     * @return array
     */
    private function _getCompilerState()
    {
        $compiler = Mage::getModel('compiler/process');
        $compilerConfig = MAGENTO_ROOT . '/includes/config.php';

        if (file_exists($compilerConfig) && !(defined('COMPILER_INCLUDE_PATH') || defined('COMPILER_COLLECT_PATH'))) {
            include $compilerConfig;
        }
        $status = defined('COMPILER_INCLUDE_PATH') ? 'Enabled' : 'Disabled';
        $state  = $compiler->getCollectedFilesCount() > 0 ? 'Compiled' : 'Not Compiled';
        return array(
          'status' => $status,
          'state'  => $state,
          'files_count' => $compiler->getCollectedFilesCount(),
          'scopes_count' => $compiler->getCompiledFilesCount()
        );
    }

    /**
     * @return string
     */
    private function _getTokenConsistency()
    {
        $token = Mage::helper('ricardomartins_pagseguro')->getToken();
        return (strlen($token)!=32 && strlen($token)!=100)?'Wrong size':'Good';
    }

    public function testSenderHashAction()
    {
//        $paymentPost = $this->getRequest()->getPost('payment');
//        $isAdmin = isset($paymentPost['is_admin']) && $paymentPost['is_admin']=="true";
//        $session = 'checkout/session';
//        if ($isAdmin) {
//            $session = 'core/cookie';
//            Mage::getSingleton($session)->set('PsPayment', serialize($paymentPost));
//        } else {
//            Mage::getSingleton($session)->setData('PsPayment', serialize($paymentPost));
//        }
//        Mage::log(var_export($paymentPost, true), null, 'martins.log', true);
//
//
//        $this->getResponse()->setHttpResponseCode(200);



        // pegando sender hash
        $isAdmin = Mage::app()->getStore()->isAdmin();
        $session = ($isAdmin)?'core/cookie':'checkout/session';
        $registry = Mage::getSingleton($session);

        $registry = ($isAdmin)?$registry->get('PsPayment'):$registry->getData('PsPayment');

        $registry = Zend_Serializer::unserialize($registry);

        Mage::log('Registry:' . var_export($registry, true), null, 'martins.log', true);



    }

}
