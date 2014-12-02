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

    public function isAvailable($quote = null)
    {
        $is_available = parent::isAvailable ($quote);
        if (empty($quote)){
            return $is_available;
        }
        if (Mage::getStoreConfigFlag("payment/pagseguro_cc/group_restriction") == false) {
            return $is_available;
        }

        $current_group_id = $quote->getCustomerGroupId ();
        $customer_groups = explode (',', $this->_getStoreConfig('customer_groups'));

        if($is_available && in_array($current_group_id, $customer_groups)){
            return true;
        }

        return false;
    }

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

        //cpf
        if(Mage::helper('ricardomartins_pagseguro')->isCpfVisible()) {
            $info->setAdditionalInformation($this->getCode() . '_cpf', $data->getData($this->getCode() . '_cpf'));
        }

        //data de nascimento
        $owner_dob_attribute = Mage::getStoreConfig('payment/pagseguro_cc/owner_dob_attribute');
        if(empty($owner_dob_attribute)){// pegar o dob e salvar aí
            $info->setAdditionalInformation('credit_card_owner_birthdate', date('d/m/Y',strtotime(
                        $data->getPsCcOwnerBirthdayYear().'/'.$data->getPsCcOwnerBirthdayMonth().'/'.$data->getPsCcOwnerBirthdayDay()
                    )));
        }

        //parcelas
        if($data->getPsCcInstallments())
        {
            $installments = explode('|', $data->getPsCcInstallments());
            if(false !== $installments && count($installments)==2){
                $info->setAdditionalInformation('installment_quantity', (int)$installments[0]);
                $info->setAdditionalInformation('installment_value', $installments[1]);
            }
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
            Mage::helper('ricardomartins_pagseguro')->writeLog('Falha ao obter o token do cartao ou sender_hash. Veja se os dados "sender_hash" e "credit_card_token" foram enviados no formulário. Um problema de JavaScript pode ter ocorrido. Se esta for apenas uma atualização de blocos via ajax nao se preocupe.');
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
        $xmlRetorno = $this->callApi($params,$payment);
        $this->proccessNotificatonResult($xmlRetorno);

        if(isset($xmlRetorno->errors)){
            $errMsg = array();
            foreach($xmlRetorno->errors as $error){
                $errMsg[] = (string)$error->message . '(' . $error->code . ')';
            }
            Mage::throwException('Um ou mais erros ocorreram no seu pagamento.' . PHP_EOL . implode(PHP_EOL,$errMsg));
        }

        if(isset($xmlRetorno->code)){

            $additional = array('transaction_id'=>(string)$xmlRetorno->code);
            if($existing = $payment->getAdditionalInformation())
            {
                if(is_array($existing))
                {
                    $additional = array_merge($additional,$existing);
                }
            }
            $payment->setAdditionalInformation($additional);
        }
        return $this;
    }

    public function _getStoreConfig($field)
    {
        return Mage::getStoreConfig("payment/pagseguro_cc/{$field}");
    }

}
