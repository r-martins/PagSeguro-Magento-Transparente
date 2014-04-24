<?php
/**
 * Created by PhpStorm.
 * User: martins
 * Date: 4/7/14
 * Time: 5:28 PM
 */ 
class RicardoMartins_PagSeguro_Helper_Data extends Mage_Core_Helper_Abstract
{
    const WS_URL = 'https://ws.pagseguro.uol.com.br/v2/';
    const JS_URL = 'https://stc.pagseguro.uol.com.br/pagseguro/api/v2/checkout/pagseguro.directpayment.js';
//    const JS_URL = 'http://magento.local.com.br/js/pagseguro.js';

    const XML_PATH_PAYMENT_PAGSEGURO_EMAIL = 'payment/pagseguro/merchant_email';
    const XML_PATH_PAYMENT_PAGSEGURO_TOKEN = 'payment/pagseguro/token';
    const XML_PATH_PAYMENT_PAGSEGURO_DEBUG = 'payment/pagseguro/debug';


    /**
     * Retorna o ID da sessao para ser usado nas chamadas JavaScript do Checkout Transparente
     * ou FALSE no caso de erro
     * @return bool|string
     */
    public function getSessionId()
    {
        $client = new Zend_Http_Client($this->getWsUrl('sessions'));
        $client->setMethod(Zend_Http_Client::POST);
        $client->setParameterGet('email',Mage::getStoreConfig($this::XML_PATH_PAYMENT_PAGSEGURO_EMAIL));
        $client->setParameterGet('token',$this->_getToken());
        try{
            $response = $client->request();
        }catch(Exception $e){
            Mage::logException($e);
            return false;
        }

        $response = $client->getLastResponse()->getBody();

        $xml = simplexml_load_string($response);
        if(false === $xml){
            $this->writeLog('Falha na autenticação com API do PagSeguro. Verifique email e token cadastrados.');
            return false;
        }
        return (string)$xml->id;
    }

    public function getWsUrl($amend='')
    {
        return self::WS_URL . $amend;
    }

    public function getJsUrl()
    {
        return self::JS_URL;
    }

    public function getFullJsScriptString()
    {
        return sprintf('<script type="text/javascript" src="%s"></script>', self::JS_URL);
    }

    public function isDebugActive()
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_PAYMENT_PAGSEGURO_DEBUG);
    }

    /**
     * Grava algo no pagseguro.log
     * @param $obj mixed|string
     */
    public function writeLog($obj)
    {
        if ($this->isDebugActive()) {
            if(is_string($obj)){
                Mage::log($obj, Zend_Log::DEBUG, 'pagseguro.log', true);
            }else{
                Mage::log(var_export($obj, true), Zend_Log::DEBUG, 'pagseguro.log', true);
            }
        }else{
            Mage::log(var_export('debug inativo', true), null, 'martins.log', true);
        }
    }

    protected function _getToken()
    {
        $token = Mage::getStoreConfig(self::XML_PATH_PAYMENT_PAGSEGURO_TOKEN);
        if(empty($token))
        {
            return false;
        }

        return Mage::helper('core')->decrypt($token);
    }
}