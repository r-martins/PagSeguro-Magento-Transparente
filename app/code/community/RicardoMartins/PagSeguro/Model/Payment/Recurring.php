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
class RicardoMartins_PagSeguro_Model_Payment_Recurring extends RicardoMartins_PagSeguro_Model_Abstract
    implements Mage_Payment_Model_Recurring_Profile_MethodInterface
{
    protected $_code = 'rm_pagseguro_recurring';
    protected $_formBlockType = 'ricardomartins_pagseguro/form_recurring';
    protected $_infoBlockType = 'ricardomartins_pagseguro/form_info_recurring';
    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid = true;
    protected $_canUseInternal = false;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;
    protected $_canSaveCc = false;
    protected $_canCreateBillingAgreement   = true;

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
        if (Mage::getStoreConfigFlag("payment/pagseguro_recurring/group_restriction") == false) {
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

        /** @var RicardoMartins_PagSeguro_Helper_Params $pHelper */
        $pHelper = Mage::helper('ricardomartins_pagseguro/params');

        $info->setAdditionalInformation('sender_hash', $pHelper->getPaymentHash('sender_hash'))
            ->setAdditionalInformation('credit_card_token', $pHelper->getPaymentHash('credit_card_token'))
            ->setAdditionalInformation('credit_card_owner', $data->getPsCcOwner())
            ->setCcType($pHelper->getPaymentHash('cc_type'))
            ->setCcLast4(substr($data->getPsCcNumber(), -4));

        //cpf
        if (Mage::helper('ricardomartins_pagseguro')->isCpfVisible()) {
            $info->setAdditionalInformation($this->getCode() . '_cpf', $data->getData($this->getCode() . '_cpf'));
        }

        //DOB
        $ownerDobAttribute = Mage::getStoreConfig('payment/rm_pagseguro_cc/owner_dob_attribute');
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

        /** @var RicardoMartins_PagSeguro_Helper_Data $helper */
        $helper = Mage::helper('ricardomartins_pagseguro');

        /** @var RicardoMartins_PagSeguro_Helper_Params $pHelper */
        $pHelper = Mage::helper('ricardomartins_pagseguro/params');

        $shippingMethod = Mage::getSingleton('checkout/session')->getQuote()->getShippingAddress()->getShippingMethod();

        // verifica se não há método de envio selecionado antes de exibir o erro de falha no cartão de crédito - Weber
        if (empty($shippingMethod)) {
            return false;
        }

        $senderHash = $pHelper->getPaymentHash('sender_hash');
        $creditCardToken = $pHelper->getPaymentHash('credit_card_token');

        //mapeia a request URL atual
        $controller = Mage::app()->getRequest()->getControllerName();
        $action = Mage::app()->getRequest()->getActionName();
        $route = Mage::app()->getRequest()->getRouteName();
        $pathRequest = $route.'/'.$controller.'/'.$action;

        //seta os paths para bloqueio de validação instantânea definidos no admin no array
        $configPaths = Mage::getStoreConfig('payment/rm_pagseguro/exception_request_validate');
//        $configPaths = explode(PHP_EOL, $configPaths);
        $configPaths = preg_split('/\r\n|[\r\n]/', $configPaths);

        //Valida token e hash se a request atual se encontra na lista de
        //exceções do admin ou se a requisição vem de placeOrder
        if ( (!$creditCardToken || !$senderHash) && !in_array($pathRequest, $configPaths)) {
            $missingInfo = sprintf('Token do cartão: %s', var_export($creditCardToken, true));
            $missingInfo .= sprintf('/ Sender_hash: %s', var_export($senderHash, true));
            $missingInfo .= '/ URL desta requisição: ' . $pathRequest;
            $helper->writeLog(
                    "Falha ao obter o token do cartao ou sender_hash.
                    Ative o modo debug e observe o console de erros do seu navegador.
                    Se esta for uma atualização via Ajax, ignore esta mensagem até a finalização do pedido, ou configure
                    a url de exceção.
                    $missingInfo"
                );
            if (!$helper->isRetryActive()){
                Mage::throwException(
                    'Falha ao processar seu pagamento. Por favor, entre em contato com nossa equipe.'
                );
            }else{
                $helper->writeLog(
                    'Apesar da transação ter falhado, o pedido poderá continuar pois a retentativa está ativa.'
                );
            }
        }
        return $this;
    }


    // public function processBeforeRefund($invoice, $payment){} //before refund
    // public function processCreditmemo($creditmemo, $payment){} //after refund

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
        /** @var Mage_Sales_Model_Order $order */
        $order = $payment->getOrder();

        //will grab data to be send via POST to API inside $params
        $params = Mage::helper('ricardomartins_pagseguro/internal')->getCreditCardApiCallParams($order, $payment);
        $rmHelper = Mage::helper('ricardomartins_pagseguro');

        //call API
        $returnXml = $this->callApi($params, $payment);

        try {
            $this->proccessNotificatonResult($returnXml);
            if (isset($returnXml->errors)) {
                foreach ($returnXml->errors as $error) {
                    $errMsg[] = $rmHelper->__((string)$error->message) . ' (' . $error->code . ')';
                }
                Mage::throwException('Um ou mais erros ocorreram no seu pagamento.' . PHP_EOL . implode(PHP_EOL, $errMsg));
            }

            if (isset($xmlRetorno->error)) {
                $error = $returnXml->error;
                $errMsg[] = $rmHelper->__((string)$error->message) . ' (' . $error->code . ')';

                if(count($returnXml->error) > 1){
                    unset($errMsg);
                    foreach ($returnXml->error as $error) {
                        $errMsg[] = $rmHelper->__((string)$error->message) . ' (' . $error->code . ')';
                    }
                }

                Mage::throwException('Um erro ocorreu em seu pagamento.' . PHP_EOL . implode(PHP_EOL, $errMsg));
            }
        } catch (Mage_Core_Exception $e) {
            if (!$rmHelper->isRetryActive() || !$rmHelper->canRetryOrder($order)) {
                $order->addStatusHistoryComment('A retentativa de pedido está ativa. O pedido foi concluído mesmo com o seguite erro: ' . $e->getMessage());
                Mage::throwException($e->getMessage());
            }
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

    /**
     * Validate data
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     *
     * @throws Mage_Core_Exception
     */
    public function validateRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile)
    {
        $a = 1;
        /*
         * Aqui temos os dados do plano no momento que o pedido é Placed
         * É hora de criar o plano
         * https://dev.pagseguro.uol.com.br/reference#criar-plano
         * $profile->getData()
         * result = {array} [19]
                 start_date_is_editable = "0"
                 period_unit = "day" //period
                 period_frequency = "1"
                 init_amount = "10.0000" //membershipFee
                 start_datetime = "2020-04-30 07:23:22" // initialDate
                 state = "unknown"
                 quote = {Mage_Sales_Model_Quote} [22]
                 order_info = {array} [93]
                 billing_address_info = {array} [121]
                 shipping_address_info = {array} [128]
                 currency_code = "BRL"
                 customer_id = null
                 store_id = "1"
                 quote_item_info = {Mage_Sales_Model_Quote_Item} [25]
                 billing_amount = "100.0000" //amountPerPayment + shipping_amount
                 shipping_amount = "10.7300"
                 schedule_description = "Assinatura Boletim Diario"
                 order_item_info = {array} [82]
                 method_code = "rm_pagseguro_recurring"
         */

        // TODO: Implement validateRecurringProfile() method.
    }

    /**
     * Submit to the gateway
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @param Mage_Payment_Model_Info              $paymentInfo
     */
    public function submitRecurringProfile(
        Mage_Payment_Model_Recurring_Profile $profile, Mage_Payment_Model_Info $paymentInfo
    ) {
        $a = 1;
        // TODO: Implement submitRecurringProfile() method.
    }

    /**
     * Fetch details
     *
     * @param string $referenceId
     * @param Varien_Object $result
     */
    public function getRecurringProfileDetails($referenceId, Varien_Object $result)
    {
        $a = 1;

        // TODO: Implement getRecurringProfileDetails() method.
    }

    /**
     * Check whether can get recurring profile details
     *
     * @return bool
     */
    public function canGetRecurringProfileDetails()
    {
        $a = 1;
        //chamado quando entramos no perfil recorrente em Vendas > Perfil recorrente > clicamos em um perfil
        // TODO: Implement canGetRecurringProfileDetails() method.
    }

    /**
     * Update data
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     */
    public function updateRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile)
    {
        $a = 1;

        // TODO: Implement updateRecurringProfile() method.
    }

    /**
     * Manage status
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     */
    public function updateRecurringProfileStatus(Mage_Payment_Model_Recurring_Profile $profile)
    {
        $a = 1;

        // TODO: Implement updateRecurringProfileStatus() method.
    }
}
