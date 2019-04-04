<?php
class RicardoMartins_PagSeguro_Model_Healthcheck extends Mage_Core_Model_Abstract
{
    protected $_errors = array();

    /**
     * @param Varien_Event_Observer $observer
     */
    public function check(Varien_Event_Observer $observer)
    {

        $this->_checkSessionId();
        $this->basicCheck($observer);
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function basicCheck(Varien_Event_Observer $observer)
    {
        $this->_checkToken();
        $this->_checkCurl();
        $this->_checkSandbox();
        $this->_checkVersions();
        $this->_processCheckResults();
    }

    protected function _processCheckResults()
    {
        if (count($this->_errors) > 0) {
            $msg = 'Os seguintes erros nas configurações do PagSeguro foram encontrados: ';
            $msg .= '<br/>- '. implode("<br/>- ", $this->_errors);
            Mage::getSingleton('adminhtml/session')->addError($msg);
        }
    }

    protected function _checkToken()
    {
        $token = Mage::helper('ricardomartins_pagseguro')->getToken();

        if(strlen($token) != 32 && strlen($token) != 100)
            $this->_errors[] = 'O token PagSeguro digitado não é válido.';
    }

    protected function _checkSandbox()
    {
        $helper = Mage::helper('ricardomartins_pagseguro');
        $keyType = $helper->getLicenseType();

        if (Mage::getStoreConfigFlag('payment/rm_pagseguro/sandbox') && $keyType == 'app') {
            $this->_errors[] = 'Ambiente de testes (sandbox) não disponível no modelo de aplicação.';
        }
    }

    protected function _checkVersions()
    {
        $mainModuleVersion = (string)Mage::getConfig()->getModuleConfig('RicardoMartins_PagSeguro')->version;

        if (Mage::getConfig()->getModuleConfig('RicardoMartins_PagSeguroPro')) {
            $proVersion = (string)Mage::getConfig()->getModuleConfig('RicardoMartins_PagSeguroPro')->version;
        }

        if (!isset($proVersion) || empty($proVersion)) {
            return;
        }

       $majorVersionNumberPro = substr($proVersion, 0, 1);
       $majorVersionNumberMain = substr($mainModuleVersion, 0, 1);
       if ($majorVersionNumberPro != $majorVersionNumberMain) {
           $this->_errors[] = 'Módulo PRO e módulo principal são de versões incompatíveis. Atualize ambos os mdulos.';
       }
    }

    protected function _checkSessionId()
    {
        $helper = Mage::helper('ricardomartins_pagseguro');

        if (!$helper->getSessionId()) {
            $this->_errors[] = 'Não foi possível obter o sessionId. Verifique e-mail e chave digitados.';
        }
    }

    protected function _checkCurl()
    {
        if (!function_exists('curl_exec')) {
            $this->_errors[] = 'Não foi possível usar o método curl_exec. Verifique se a biblioteca PHP libcurl está habilitada e instalada.';
        }
    }
}
