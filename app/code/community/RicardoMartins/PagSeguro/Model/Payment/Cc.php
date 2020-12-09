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
    protected $_code = 'rm_pagseguro_cc';
    protected $_formBlockType = 'ricardomartins_pagseguro/form_cc';
    protected $_infoBlockType = 'ricardomartins_pagseguro/form_info_cc';
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
        $configPaths = preg_split('/\r\n|[\r\n]/', $configPaths);

        //Valida token e hash se a request atual se encontra na lista de
        //exceções do admin ou se a requisição vem de placeOrder
        if ((!$creditCardToken || !$senderHash) && !in_array($pathRequest, $configPaths)) {
            $missingInfo = sprintf('Token do cartão: %s', var_export($creditCardToken, true));
            $missingInfo .= sprintf('/ Sender_hash: %s', var_export($senderHash, true));
            $missingInfo .= '/ URL desta requisição: ' . $pathRequest;
            $helper->writeLog(
                "Falha ao obter o token do cartao ou sender_hash.
                    Ative o modo debug e observe o console de erros do seu navegador.
                    Se esta for uma atualização via Ajax, ignore esta mensagem até a finalização do pedido, ou adicione
                    a URL desta requisição como exceção nas configurações do módulo.
                    $missingInfo"
            );
            if (!$helper->isRetryActive()) {
                Mage::throwException(
                    'Falha ao processar seu pagamento. Por favor, entre em contato com nossa equipe.'
                );
            } else {
                $helper->writeLog(
                    'Apesar da transação ter falhado, o pedido poderá continuar pois a retentativa está ativa.'
                );
            }
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
        /** @var Mage_Sales_Model_Order $order */
        $order = $payment->getOrder();

        //will grab data to be send via POST to API inside $params
        $params = Mage::helper('ricardomartins_pagseguro/internal')->getCreditCardApiCallParams($order, $payment);
        $rmHelper = Mage::helper('ricardomartins_pagseguro');

        //call API
        $returnXml = $this->callApi($params, $payment);

        try {
            $this->proccessNotificatonResult($returnXml);
        } catch (Mage_Core_Exception $e) {
            //retry if error is related to installment value
            if ($this->getIsInvalidInstallmentValueError()
                && !$payment->getAdditionalInformation(
                    'retried_installments'
                )) {
                return $this->recalculateInstallmentsAndPlaceOrder($payment, $amount);
            }

            $resultXMLErrorCodes = $this->getData('resultXMLErrorCodes');
            if ($rmHelper->canRetryOrder($order) && !$this->containsUserError($resultXMLErrorCodes)) {
                $order->addStatusHistoryComment(
                    'A retentativa de pedido está ativa. O pedido foi concluído mesmo com o seguite erro: '
                    . $e->getMessage()
                );
            }

            //only throws exception if payment retry is disabled
            //read more at https://bit.ly/3b2onpo
            if (!$rmHelper->isRetryActive() || $this->containsUserError($resultXMLErrorCodes)) {
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
     * Make an API call to PagSeguro to retrieve the installment value
     * @param float     $amount Order amount
     * @param string    $creditCardBrand visa, mastercard, etc. returned from Pagseguro Api
     * @param int      $selectedInstallment
     * @param int     $maxInstallmentNoInterest
     *
     * @return bool|double
     */
    public function getInstallmentValue(
        $amount,
        $creditCardBrand,
        $selectedInstallment,
        $maxInstallmentNoInterest = null
    ) {
        $amount = number_format($amount, 2, '.', '');
        $helper = Mage::helper('ricardomartins_pagseguro');
        $sessionId = $helper->getSessionId();
        $url = "https://pagseguro.uol.com.br/checkout/v2/installments.json?sessionId=$sessionId&amount=$amount";
        $url .= "&creditCardBrand=$creditCardBrand";
        $url .= ($maxInstallmentNoInterest) ? "&maxInstallmentNoInterest=$maxInstallmentNoInterest" : "";

        $ch = curl_init($url);

        curl_setopt_array(
            $ch,
            array(
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_RETURNTRANSFER  => 1,
                CURLOPT_TIMEOUT         => 45,
                CURLOPT_SSL_VERIFYPEER  => false,
                CURLOPT_SSL_VERIFYHOST  => false,
                CURLOPT_MAXREDIRS => 10,
            )
        );

        $response = null;

        try{
            $response = curl_exec($ch);
            return json_decode($response)->installments->{$creditCardBrand}[$selectedInstallment-1]->installmentAmount;
        }catch(Exception $e){
            Mage::logException($e);
            return false;
        }

    }

    /**
     * Recalculate installment value and try to place the order again with the new amount
     * @param $payment Mage_Sales_Model_Order_Payment
     * @param $amount
     */
    public function recalculateInstallmentsAndPlaceOrder($payment, $amount)
    {
        //avoid being fired twice due to error.
        if ($payment->getAdditionalInformation('retried_installments')) {
            return;
        }

        $payment->setAdditionalInformation('retried_installments', true);
        Mage::log(
            'Houve uma inconsistência no valor dar parcelas. '
            . 'As parcelas serão recalculadas e uma nova tentativa será realizada.',
            null, 'pagseguro.log', true
        );

        $selectedMaxInstallmentNoInterest = null; //not implemented
        $installmentValue = $this->getInstallmentValue(
            $amount, $payment->getCcType(), $payment->getAdditionalInformation('installment_quantity'),
            $selectedMaxInstallmentNoInterest
        );
        $payment->setAdditionalInformation('installment_value', $installmentValue);
        $payment->setAdditionalInformation('retried_installments', true);
        Mage::unregister('sales_order_invoice_save_after_event_triggered');

        try {
            $this->order($payment, $amount);
        } catch (Exception $e) {
            Mage::throwException($e->getMessage());
        }
    }

    /**
     * Checks if one of the error was caused by the user (i.e.: invalid address data)
     *
     * @param $errorCodes array
     *
     * @return bool
     */
    public function containsUserError($errorCodes)
    {
        if (!is_array($errorCodes)) {
            return true;
        }
        // Errors that are not fixed by the user
        // See https://dev.pagseguro.uol.com.br/reference/checkout-transparente#transparente-apendice
        $nonUserErrors = array (53005, 53008, 53009, 53010, 53011, 53012, 53017, 53037, 53039, 53040, 53041,
                                53045, 53046);
        return array_intersect($errorCodes, $nonUserErrors) == false;
    }

}
