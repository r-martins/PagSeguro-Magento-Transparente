<?php
class RicardoMartins_PagSeguro_Model_Payment_Cc extends RicardoMartins_PagSeguro_Model_Abstract
{
    protected $_code = 'pagseguro_cc';
    protected $_formBlockType = 'ricardomartins_pagseguro/form_cc';
    protected $_infoBlockType = 'ricardomartins_pagseguro/form_info_cc';
    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canRefund = false;
    protected $_canVoid = true;
    protected $_canUseInternal = true;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = true;
    protected $_canSaveCc = false;

    public function assignData($data)
    {
        if(!($data instanceof Varien_Object)){
            $data = new Varien_Object($data);
        }

        $info = $this->getInfoInstance();
        $info->setAdditionalInformation('sender_hash',$data->getSenderHash())
            ->setAdditionalInformation('credit_card_token',$data->getCreditCardToken())
            ->setAdditionalInformation('credit_card_owner', $data->getPsCcOwner())
            ->setCcType($data->getPsCardType())
            ->setCcLast4(substr($data->getPsCcNumber(), -4));

        $owner_dob_attribute = Mage::getStoreConfig('payment/pagseguro_cc/owner_dob_attribute');
        if(empty($owner_dob_attribute)){// pegar o dob e salvar aí
            $info->setAdditionalInformation('credit_card_owner_birthdate', date('d/m/Y',strtotime(
                        $data->getPsCcOwnerBirthdayYear().'/'.$data->getPsCcOwnerBirthdayMonth().'/'.$data->getPsCcOwnerBirthdayDay()
                    )));
        }

        return $this;
    }

    public function validate()
    {
        parent::validate();
        $info = $this->getInfoInstance();

        $sender_hash = $info->getAdditionalInformation('sender_hash');
        $credit_card_token = $info->getAdditionalInformation('credit_card_token');

        if(empty($credit_card_token) || empty($sender_hash))
        {
            Mage::helper('ricardomartins_pagseguro')->writeLog('Falha ao obter o token do cartao ou sender_hash. Veja se os dados "sender_hash" e "credit_card_token" foram enviados no formulário. Um problema de JavaScript pode ter ocorrido.');
            Mage::throwException('Falha ao processar pagamento junto ao PagSeguro. Por favor, entre em contato com nossa equipe.');
        }
        return $this;
    }

    public function order(Varien_Object $payment, $amount)
    {
        $order = $payment->getOrder();

        //montaremos os dados a ser enviados via POST pra api em $params
        $params = Mage::helper('ricardomartins_pagseguro/internal')->getCreditCardApiCallParams($order, $payment);

        //chamamos a API
        $xmlRetorno = $this->_callApi($params,$payment);
        $this->proccessNotificatonResult($xmlRetorno);

        if(isset($xmlRetorno->errors)){
            $errMsg = array();
            foreach($xmlRetorno->errors as $error){
                $errMsg[] = (string)$error->message . '(' . $error->code . ')';
            }
            Mage::throwException('Um ou mais erros ocorreram no seu pagamento.' . PHP_EOL . implode(PHP_EOL,$errMsg));
        }

        if(isset($xmlRetorno->code)){
            $payment->setAdditionalInformation(array('transaction_id'=>(string)$xmlRetorno->code));
        }
        return $this;
    }

    /**
     * Chama API pra realizar um pagamento
     * @param $params
     * @param $payment
     *
     * @return SimpleXMLElement
     */
    protected function _callApi($params, $payment)
    {
        $helper = Mage::helper('ricardomartins_pagseguro');
        $client = new Zend_Http_Client($helper->getWsUrl('transactions'));
        $client->setMethod(Zend_Http_Client::POST);
        $client->setParameterPost($params); //parametros enviados via POST
        $helper->writeLog('Parametros sendo enviados para API (/transactions): '. var_export($params,true));
        try{
            $response = $client->request(); //faz o request
        }catch(Exception $e){
            Mage::throwException('Falha na comunicação com Pagseguro (' . $e->getMessage() . ')');
        }

        $response = $client->getLastResponse()->getBody();
        $helper->writeLog('Retorno PagSeguro (/transactions): ' . var_export($response,true));

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response);
        if(false === $xml){
            switch($response){
                case 'Unauthorized':
                    $helper->writeLog('Token/email não autorizado pelo PagSeguro. Verifique suas configurações no painel.');
                    break;
                case 'Forbidden':
                    $helper->writeLog('Acesso não autorizado à Api Pagseguro. Verifique se você tem permissão para usar este serviço. Retorno: ' . var_export($response,true));
                    break;
                default:
                    $helper->writeLog('Retorno inesperado do PagSeguro. Retorno: ' . var_export($response,true));
            }
            Mage::throwException('Houve uma falha ao processar seu pedido/pagamento. Por favor entre em contato conosco.');
        }

        return $xml;
    }
}