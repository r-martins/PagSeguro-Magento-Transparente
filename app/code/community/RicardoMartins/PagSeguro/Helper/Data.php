<?php

class RicardoMartins_PagSeguro_Helper_Data extends Mage_Core_Helper_Abstract
{
    const XML_PATH_PAYMENT_PAGSEGURO_EMAIL          = 'payment/pagseguro/merchant_email';
    const XML_PATH_PAYMENT_PAGSEGURO_TOKEN          = 'payment/pagseguro/token';
    const XML_PATH_PAYMENT_PAGSEGURO_DEBUG          = 'payment/pagseguro/debug';
    const XML_PATH_PAUMENT_PAGSEGURO_SANDBOX        = 'payment/pagseguro/sandbox';
    const XML_PATH_PAYMENT_PAGSEGURO_SANDBOX_EMAIL  = 'payment/pagseguro/sandbox_merchant_email';
    const XML_PATH_PAYMENT_PAGSEGURO_SANDBOX_TOKEN  = 'payment/pagseguro/sandbox_token';
    const XML_PATH_PAYMENT_PAGSEGURO_WS_URL         = 'payment/pagseguro/ws_url';
    const XML_PATH_PAYMENT_PAGSEGURO_WS_URL_APP     = 'payment/pagseguro/ws_url_app';
    const XML_PATH_PAYMENT_PAGSEGURO_JS_URL         = 'payment/pagseguro/js_url';
    const XML_PATH_PAYMENT_PAGSEGURO_SANDBOX_WS_URL = 'payment/pagseguro/sandbox_ws_url';
    const XML_PATH_PAYMENT_PAGSEGURO_SANDBOX_WS_URL_APP = 'payment/pagseguro/sandbox_ws_url_app';
    const XML_PATH_PAYMENT_PAGSEGURO_SANDBOX_JS_URL = 'payment/pagseguro/sandbox_js_url';
    const XML_PATH_PAYMENT_PAGSEGURO_KEY_TYPE       = 'payment/pagseguropro/key_type';
    const XML_PATH_PAYMENT_PAGSEGURO_KEY       = 'payment/pagseguropro/key';


    /**
     * Retorna o ID da sessao para ser usado nas chamadas JavaScript do Checkout Transparente
     * ou FALSE no caso de erro
     * @return bool|string
     */
    public function getSessionId()
    {
        $useapp = $this->getLicenseType() == 'app';

        $url = $this->getWsUrl('sessions',$useapp);

        $ch = curl_init($url);
        $params['email'] = $this->getMerchantEmail();
        $params['token'] = $this->getToken();
        if($useapp){
            $params['public_key'] = $this->getPagSeguroProKey();
        }

        curl_setopt_array($ch, array(
            CURLOPT_POSTFIELDS  => http_build_query($params),
            CURLOPT_POST        => count($params),
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT     => 45,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,

        ));

        $response = null;

        try{
            $response = curl_exec($ch);
        }catch(Exception $e){
            Mage::logException($e);
            return false;
        }


        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response);
        if(false === $xml){
            $this->writeLog('Falha na autenticação com API do PagSeguro. Verifique email e token cadastrados. Retorno pagseguro: ' . $response);
            return false;
        }
        return (string)$xml->id;
    }

    /**
     * Retorna o email do lojista
     * @return string
     */
    public function getMerchantEmail()
    {
        if($this->isSandbox())
        {
            return Mage::getStoreConfig(self::XML_PATH_PAYMENT_PAGSEGURO_SANDBOX_EMAIL);
        }
        return Mage::getStoreConfig(self::XML_PATH_PAYMENT_PAGSEGURO_EMAIL);
    }

    /**
     * Retorna URL do Webservice do Pagseguro de acordo com o ambiente selecionado
     * @param string $amend acrescenta algo no final
     * @param bool $useapp usa modelo de aplicacao
     *
     * @return string
     */
    public function getWsUrl($amend='', $useapp = false)
    {
        if($this->isSandbox())
        {
            if($this->getLicenseType()=='app' && $useapp){
                return Mage::getStoreConfig(self::XML_PATH_PAYMENT_PAGSEGURO_SANDBOX_WS_URL_APP) . $amend;;
            }else{
                return Mage::getStoreConfig(self::XML_PATH_PAYMENT_PAGSEGURO_SANDBOX_WS_URL) . $amend;
            }
        }

        if($this->getLicenseType()=='app' && $useapp){
            return Mage::getStoreConfig(self::XML_PATH_PAYMENT_PAGSEGURO_WS_URL_APP) . $amend;
        }

        return Mage::getStoreConfig(self::XML_PATH_PAYMENT_PAGSEGURO_WS_URL) . $amend;
    }

    /**
     * Retorna o url do JavaScript da lib do Pagseguro de acordo com o ambiente selecionado
     * @return string
     */
    public function getJsUrl()
    {
        if($this->isSandbox())
        {
            return Mage::getStoreConfig(self::XML_PATH_PAYMENT_PAGSEGURO_SANDBOX_JS_URL);
        }
        return Mage::getStoreConfig(self::XML_PATH_PAYMENT_PAGSEGURO_JS_URL);
    }

    /**
     * Verifica se o debug está ativado
     * @return bool
     */
    public function isDebugActive()
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_PAYMENT_PAGSEGURO_DEBUG);
    }

    /**
     * Está no modo SandBox?
     * @return bool
     */
    public function isSandbox()
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_PAUMENT_PAGSEGURO_SANDBOX);
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
        }
    }

    /**
     * Retorna o TOKEN configurado pro ambiente selecionado. Retorna false caso não tenha sido preenchido.
     * @return string | false
     */
    public function getToken()
    {
        $token = Mage::getStoreConfig(self::XML_PATH_PAYMENT_PAGSEGURO_TOKEN);
        if($this->isSandbox())
        {
            $token = Mage::getStoreConfig(self::XML_PATH_PAYMENT_PAGSEGURO_SANDBOX_TOKEN);
        }
        if(empty($token))
        {
            return false;
        }

        return Mage::helper('core')->decrypt($token);
    }

    /**
     * Verifica se o campo CPF deve ser exibido junto com os dados de pagamento
     * @return bool
     */
    public function isCpfVisible()
    {
        $customer_cpf_attribute = Mage::getStoreConfig('payment/pagseguro/customer_cpf_attribute');
        return empty($customer_cpf_attribute);
    }

    /**
     * Retorna o tipo de licença (se houver)
     * @return string
     */
    public function getLicenseType()
    {
        return Mage::getStoreConfig(self::XML_PATH_PAYMENT_PAGSEGURO_KEY_TYPE);
    }

    /**
     * Retorna chave do PagSeguro PRO (se houver)
     * @return string
     */
    public function getPagSeguroProKey()
    {
        return Mage::getStoreConfig(self::XML_PATH_PAYMENT_PAGSEGURO_KEY);
    }

    /**
     * Faz a tradução dos termos dinamicos do PagSeguro
     * @author Ricardo Martins
     * @return string
     */
    public function __(){
        $args = func_get_args();
        $expr = new Mage_Core_Model_Translate_Expr(array_shift($args), $this->_getModuleName());
        array_unshift($args, $expr);

        $text = $args[0]->getText();
        preg_match('/(.*)\:(.*)/',$text, $matches);
        if($matches!==false && isset($matches[1])){
            array_shift($matches);
            $matches[0] .= ': %s';
            $args = $matches;
        }
        return Mage::app()->getTranslator()->translate($args);
    }
}
