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
    const XML_PATH_PAYMENT_PAGSEGURO_JS_URL         = 'payment/pagseguro/js_url';
    const XML_PATH_PAYMENT_PAGSEGURO_SANDBOX_WS_URL = 'payment/pagseguro/sandbox_ws_url';
    const XML_PATH_PAYMENT_PAGSEGURO_SANDBOX_JS_URL = 'payment/pagseguro/sandbox_js_url';


    /**
     * Retorna o ID da sessao para ser usado nas chamadas JavaScript do Checkout Transparente
     * ou FALSE no caso de erro
     * @return bool|string
     */
    public function getSessionId()
    {
        $params = array(
            'token'=> urlencode($this->getToken()),
            'email'=> urlencode($this->getMerchantEmail()),
        );

        $fields_string = '';
        foreach($params as $key=>$value) {
            $fields_string .= $key.'='.$value.'&';
        }
        $fields_string = rtrim($fields_string, '&');
        
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, $this->getWsUrl('sessions'));
        curl_setopt($ch,CURLOPT_POST, count($params));
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
        
        $response = curl_exec($ch);
        curl_close($ch);

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string(trim($response));
        if(false === $xml){
            $this->writeLog('Falha na autenticação com API do PagSeguro. Verifique email e token cadastrados. Retorno pagseguro: '. $response);
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
     *
     * @return string
     */
    public function getWsUrl($amend='')
    {
        if($this->isSandbox())
        {
            return Mage::getStoreConfig(self::XML_PATH_PAYMENT_PAGSEGURO_SANDBOX_WS_URL) . $amend;
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
