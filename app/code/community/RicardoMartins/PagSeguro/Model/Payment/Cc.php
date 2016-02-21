<?php
/**
 * PagSeguro Transparente Magento
 * Model CC Class - responsible for credit card payment processing
 *
 * @category    RicardoMartins
 * @package     RicardoMartins_PagSeguro
 * @author      Ricardo Martins
 * @copyright   Copyright (c) 2015 Ricardo Martins (http://r-martins.github.io/PagSeguro-Magento-Transparente/)
 * @license     https://opensource.org/licenses/MIT MIT License
 */
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
    protected $_canUseInternal = false;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = true;
    protected $_canSaveCc = false;

    /**
     * Check if module is available for current quote and customer group (if restriction is activated)
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return bool
     */
    public function isAvailable($quote = null)
    {
        $isAvailable = parent::isAvailable($quote);
        if (empty($quote)) {
            return $isAvailable;
        }
        if (Mage::getStoreConfigFlag("payment/pagseguro_cc/group_restriction") == false) {
            return $isAvailable;
        }

        $currentGroupId = $quote->getCustomerGroupId();
        $customerGroups = explode(',', $this->_getStoreConfig('customer_groups'));

        if ($isAvailable && in_array($currentGroupId, $customerGroups)) {
            return true;
        }

        return false;
    }

    /**
     * Assign data to info model instance
     *
     * @param   mixed $data
     * @return  Mage_Payment_Model_Info
     */
    public function assignData($data)
    {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }

        $info = $this->getInfoInstance();
        $info->setAdditionalInformation('sender_hash', $data->getSenderHash())
            ->setAdditionalInformation('credit_card_token', $data->getCreditCardToken())
            ->setAdditionalInformation('credit_card_owner', $data->getPsCcOwner())
            ->setCcType($data->getPsCardType())
            ->setCcLast4(substr($data->getPsCcNumber(), -4));

        //cpf
        if (Mage::helper('ricardomartins_pagseguro')->isCpfVisible()) {
            $info->setAdditionalInformation($this->getCode() . '_cpf', $data->getData($this->getCode() . '_cpf'));
        }

        //DOB
        $ownerDobAttribute = Mage::getStoreConfig('payment/pagseguro_cc/owner_dob_attribute');
        if (empty($ownerDobAttribute)) {
            $info->setAdditionalInformation(
                'credit_card_owner_birthdate',
                date(
                    'd/m/Y',
                    strtotime(
                        $data->getPsCcOwnerBirthdayYear().
                        '/'.
                        $data->getPsCcOwnerBirthdayMonth().
                        '/'.$data->getPsCcOwnerBirthdayDay()
                    )
                )
            );
        }

        //Installments
        if ($data->getPsCcInstallments()) {
            $installments = explode('|', $data->getPsCcInstallments());
            if (false !== $installments && count($installments)==2) {
                $info->setAdditionalInformation('installment_quantity', (int)$installments[0]);
                $info->setAdditionalInformation('installment_value', $installments[1]);
            }
        }

        return $this;
    }

    /**
     * Validate payment method information object
     *
     * @return Mage_Payment_Model_Abstract
     */
    public function validate()
    {
        parent::validate();
        $info = $this->getInfoInstance();

        $senderHash = $info->getAdditionalInformation('sender_hash');
        $creditCardToken = $info->getAdditionalInformation('credit_card_token');

        if (empty($creditCardToken) || empty($senderHash)) {
            Mage::helper('ricardomartins_pagseguro')
                ->writeLog(
                    'Falha ao obter o token do cartao ou sender_hash.
                    Veja se os dados "sender_hash" e "credit_card_token" foram enviados no formulário.
                    Um problema de JavaScript pode ter ocorrido.
                    Se esta for apenas uma atualização de blocos via ajax nao se preocupe.'
                );
            Mage::throwException(
                'Falha ao processar pagamento junto ao PagSeguro. Por favor, entre em contato com nossa equipe.'
            );
        }
        return $this;
    }

    /**
     * Order payment
     *
     * @param Varien_Object $payment
     * @param float $amount
     *
     * @return RicardoMartins_PagSeguro_Model_Payment_Cc
     */
    public function order(Varien_Object $payment, $amount)
    {
        $order = $payment->getOrder();

        //will grab data to be send via POST to API inside $params
        $params = Mage::helper('ricardomartins_pagseguro/internal')->getCreditCardApiCallParams($order, $payment);
        $rmHelper = Mage::helper('ricardomartins_pagseguro');

        //call API
        $returnXml = $this->callApi($params, $payment);
        $this->proccessNotificatonResult($returnXml);

        if (isset($returnXml->errors)) {
            $errMsg = array();
            foreach ($returnXml->errors as $error) {
                $errMsg[] = $rmHelper->__((string)$error->message) . '(' . $error->code . ')';
            }
            Mage::throwException('Um ou mais erros ocorreram no seu pagamento.' . PHP_EOL . implode(PHP_EOL, $errMsg));
        }
        if (isset($returnXml->error)) {
            $error = $returnXml->error;
            $errMsg[] = $rmHelper->__((string)$error->message) . ' (' . $error->code . ')';
            Mage::throwException('Um erro ocorreu em seu pagamento.' . PHP_EOL . implode(PHP_EOL, $errMsg));
        }

        $payment->setSkipOrderProcessing(true);

        if (isset($returnXml->code)) {

            $additional = array('transaction_id'=>(string)$returnXml->code);
            if ($existing = $payment->getAdditionalInformation()) {
                if (is_array($existing)) {
                    $additional = array_merge($additional, $existing);
                }
            }
            $payment->setAdditionalInformation($additional);
        }
        return $this;
    }

    /**
     * Generically get module's config field value
     * @param $field
     *
     * @return mixed
     */
    public function _getStoreConfig($field)
    {
        return Mage::getStoreConfig("payment/pagseguro_cc/{$field}");
    }

}
