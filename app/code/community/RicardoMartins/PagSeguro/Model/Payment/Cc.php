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
        $helper = Mage::helper('ricardomartins_pagseguro');
        $pHelper = Mage::helper('ricardomartins_pagseguro/params');

        $info->setAdditionalInformation('sender_hash', $pHelper->getPaymentHash('sender_hash'));

        // treat multi credit card data before, 
        // if funcionality is enabled
        if($helper->isMultiCcEnabled())
        {
            $info->setAdditionalInformation("cc1", $this->_extractMultiCcDataFromForm($info, $data, 1, $pHelper));
            $info->setAdditionalInformation("cc2", $this->_extractMultiCcDataFromForm($info, $data, 2, $pHelper));
            $info->setAdditionalInformation("use_two_cards", ($data->getData("use_two_cards") ? 1 : 0)); 
            
            if($data->getData("use_two_cards"))
            {
                return;
            }

            $cc1Data = $info->getAdditionalInformation("cc1");

            $info->setAdditionalInformation('credit_card_token', $cc1Data["token"]);
            $info->setCcType($cc1Data["brand"]);
            $info->setCcLast4($cc1Data["last4"]);
            $data->setPsCcOwner($cc1Data["owner"]);
            $data->setPsCcOwnerBirthdayDay(str_pad($data->getData("ps_multicc1_dob_day"), 2, "0", STR_PAD_LEFT));
            $data->setPsCcOwnerBirthdayMonth(str_pad($data->getData("ps_multicc1_dob_month"), 2, "0", STR_PAD_LEFT));
            $data->setPsCcOwnerBirthdayYear($data->getData("ps_multicc1_dob_year"));
            $data->setPsCcInstallments($data->getData("ps_multicc1_installments"));
            $data->setData($this->getCode() . "_cpf", $cc1Data["owner_doc"]);
        }
        else
        {
            $info->setAdditionalInformation('credit_card_token', $pHelper->getPaymentHash('credit_card_token'))
                 ->setCcType($pHelper->getPaymentHash('cc_type'))
                 ->setCcLast4(substr($data->getPsCcNumber(), -4));
        }
        
        $info->setAdditionalInformation('credit_card_owner', $data->getPsCcOwner());

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
     * Assign multi credit card form data to payment info object
     *
     * @param Mage_Payment_Model_Info $paymentInfo
     * @param mixed $formData
     */
    public function _extractMultiCcDataFromForm($paymentInfo, $formData, $cardIndex, $pHelper)
    {
        $installments = explode("|", $formData->getData("ps_multicc{$cardIndex}_installments"));

        if($installments !== false && count($installments) == 2)
        {
            $installmentsQty = $installments[0];
            $installmentsValue = $installments[1];
        }
        else
        {
            $installmentsQty = "";
            $installmentsValue = "";
        }

        $total =  str_replace(".", "", $formData->getData("ps_multicc{$cardIndex}_total"));
        $total = floatval(str_replace(",", ".", $total));

        $cardData = array
        (
            "last4"     => substr($pHelper->removeNonNumbericChars($formData->getData("ps_multicc{$cardIndex}_number")), - 4),
            "token"     => $formData->getData("ps_multicc{$cardIndex}_token"),
            "total"     => $total,
            "brand"     => $formData->getData("ps_multicc{$cardIndex}_brand"),
            "owner"     => $formData->getData("ps_multicc{$cardIndex}_owner"),
            "owner_doc" => $pHelper->removeNonNumbericChars($formData->getData("ps_multicc{$cardIndex}_owner_document")),
            "installments_qty" => $installmentsQty,
            "installments_value" => $installmentsValue,
        );

        if (empty(Mage::getStoreConfig('payment/rm_pagseguro_cc/owner_dob_attribute')))
        {
            $dob = str_pad($formData->getData("ps_multicc{$cardIndex}_dob_day"), 2, "0", STR_PAD_LEFT) . "/" . 
                   str_pad($formData->getData("ps_multicc{$cardIndex}_dob_month"), 2, "0", STR_PAD_LEFT) . "/" .
                   $formData->getData("ps_multicc{$cardIndex}_dob_year");
                
            $cardData["dob"] = $dob;
        }

        return $cardData;
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

        $paymentInfo = $this->getInfoInstance();
        $shippingMethod = Mage::getSingleton('checkout/session')->getQuote()->getShippingAddress()->getShippingMethod();

        // verifica se não há método de envio selecionado antes de exibir o erro de falha no cartão de crédito - Weber
        if (empty($shippingMethod)) {
            return false;
        }

        $senderHash = $pHelper->getPaymentHash('sender_hash');
        $helper->isMultiCcEnabled();

        if($helper->isMultiCcEnabled() && $paymentInfo->getAdditionalInformation("use_two_cards"))
        {
            $cc1 = $paymentInfo->getAdditionalInformation("cc1");
            $this->_validateCardAndSenderHashes($cc1["token"], $senderHash, " 1");
            
            $cc2 = $paymentInfo->getAdditionalInformation("cc2");
            $this->_validateCardAndSenderHashes($cc2["token"], $senderHash, " 2");
        }
        else
        {
            $creditCardToken = $helper->isMultiCcEnabled()
                                    ? $paymentInfo->getAdditionalInformation("credit_card_token")
                                    : $pHelper->getPaymentHash("credit_card_token");

            $this->_validateCardAndSenderHashes($creditCardToken, $senderHash);
        }

        return $this;
    }

    private function _validateCardAndSenderHashes($creditCardToken, $senderHash, $cardSuffix = "")
    {
        $helper = Mage::helper('ricardomartins_pagseguro');

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
        if ((!$creditCardToken || !$senderHash) && !in_array($pathRequest, $configPaths))
        {
            $missingInfo = sprintf('Token do cartão%s: %s', $cardSuffix, var_export($creditCardToken, true));
            $missingInfo .= sprintf('/ Sender_hash: %s', var_export($senderHash, true));
            $missingInfo .= '/ URL desta requisição: ' . $pathRequest;
            $helper->writeLog(
                "Falha ao obter o token do cartao ou sender_hash.
                    Ative o modo debug e observe o console de erros do seu navegador.
                    Se esta for uma atualização via Ajax, ignore esta mensagem até a finalização do pedido, ou configure
                    a url de exceção.
                    $missingInfo"
            );
            if (!$helper->isRetryActive())
            {
                Mage::throwException(
                    'Falha ao processar seu pagamento. Por favor, entre em contato com nossa equipe.'
                );
            }
            else
            {
                $helper->writeLog(
                    'Apesar da transação ter falhado, o pedido poderá continuar pois a retentativa está ativa.'
                );
            }
        }
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
        $helper = Mage::helper('ricardomartins_pagseguro');

        if($helper->isMulticcEnabled() && $payment->getAdditionalInformation("use_two_cards"))
        {
            try
            {
                $this->_order($payment, $amount, 1);
                $this->_order($payment, $amount, 2);
            }
            catch(Exception $e)
            {
                $this->_refundNotPersistedTransactions($payment, $amount);
                
                throw $e;
            }
        }
        else
        {
            $transaction = $this->_order($payment, $amount);
        }

        return $this;
    }

    /**
     * Comunicate with PagSeguro web service and create a Magento 
     * order transacation object
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param float $amount
     * @param integer $ccIdx
     * 
     * @return Mage_Sales_Model_Order_Payment_Transaction
     */
    private function _order($payment, $amount, $ccIdx = null)
    {
        $order = $payment->getOrder();

        if($ccIdx)
        {
            $carData = $payment->getAdditionalInformation("cc" . $ccIdx);
            $payment->setData("_current_card_index", $ccIdx);
            $payment->setData("_current_card_total_multiplier", ($carData["total"] / $amount));
        }
        else
        {
            $carData = false;
        }

        //will grab data to be send via POST to API inside $params
        $params = Mage::helper('ricardomartins_pagseguro/internal')->getCreditCardApiCallParams($order, $payment);
        $rmHelper = Mage::helper('ricardomartins_pagseguro');

        //call API
        $returnXml = $this->callApi($params, $payment);

        // creates Magento transactions
        $transaction = $this->_createOrderTransaction($payment, $returnXml);

        // update references to transactions on card data
        if($ccIdx)
        {
            $ccCard = $payment->getAdditionalInformation("cc" . $ccIdx);
            $ccCard["transaction_id"] = $transaction->getTxnId();
            $payment->setAdditionalInformation("cc" . $ccIdx, $ccCard);
        }
        else
        {
            $cc1 = $payment->getAdditionalInformation("cc1");
            $cc1["transaction_id"] = $transaction ? $transaction->getTxnId() : "";
            $payment->setAdditionalInformation("cc1", $cc1);
        }

        try
        {
            $this->proccessNotificatonResult($returnXml);
        }
        catch (Mage_Core_Exception $e)
        {
            //retry if error is related to installment value
            if ($this->getIsInvalidInstallmentValueError()
                && !$payment->getAdditionalInformation(
                    'retried_installments'
                ))
            {
                    // !!! TO DO: adapt this logic for multi cc
                return $this->recalculateInstallmentsAndPlaceOrder($payment, $amount);
            }

            if ($rmHelper->canRetryOrder($order))
            {
                $order->addStatusHistoryComment(
                    'A retentativa de pedido está ativa. O pedido foi concluído mesmo com o seguite erro: '
                    . $e->getMessage()
                );
            }

            //only throws exception if payment retry is disabled
            //read more at https://bit.ly/3b2onpo
            if (!$rmHelper->isRetryActive())
            {
                Mage::throwException($e->getMessage());
            }
        }

        return $transaction;
    }


    protected function _createOrderTransaction($payment, $returnXml)
    {
        // avoid Magento transaction automatic creation  to use our 
        // own logic
        $payment->setSkipOrderProcessing(true);
        
        if(isset($returnXml->code))
        {
            // legacy code: store transaction ID on additional information
            $additional = array('transaction_id'=>(string)$returnXml->code);
            if ($existing = $payment->getAdditionalInformation()){
                if (is_array($existing)) {
                    $additional = array_merge($additional, $existing);
                }
            }

            $payment->setAdditionalInformation($additional);

            // new approach: use transaction from pagseguro to 
            // generate a magento transaction
            $payment->setTransactionId((string) $returnXml->code);
            $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_ORDER);
            
            $transaction->setIsClosed(false);
            $transactionStatus = (string) $returnXml->status;
            if($transactionStatus == "3" || $transactionStatus == "4")
            {
                $transaction->setIsClosed(true);
            }

            if($carData && isset($carData["total"]))
            {
                $transaction->setAdditionalInformation("frontend_value", $carData["total"]);
            }

            $transactionDetails = array
            (
                "reference"       => isset($returnXml->reference) ? (string) $returnXml->reference : "",
                "status"          => isset($returnXml->status) ? (string) $returnXml->status : "",
                "last_event_date" => isset($returnXml->lastEventDate) ? (string) $returnXml->lastEventDate : "",
                "remote_value"    => isset($returnXml->grossAmount) ? (string) $returnXml->grossAmount : "",
            );

            $transaction->setAdditionalInformation("status", $transactionDetails["status"]);
            $transaction->setAdditionalInformation("last_event_date", $transactionDetails["last_event_date"]);
            
            if(isset($returnXml->gatewaySystem))
            {
                if(isset($returnXml->gatewaySystem->authorizationCode)) $transactionDetails["authorization_code"] = (string) $returnXml->gatewaySystem->authorizationCode;
                if(isset($returnXml->gatewaySystem->nsu)) $transactionDetails["nsu"] = (string) $returnXml->gatewaySystem->nsu;
                if(isset($returnXml->gatewaySystem->tid)) $transactionDetails["tid"] = (string) $returnXml->gatewaySystem->tid;
            }

            $transaction->setAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $transactionDetails);
        }
        else
        {
            $xmlStr = is_object($returnXml) && method_exists($returnXml, "asXML")
                        ? $returnXml->asXML()
                        : strval($returnXml);
            $rmHelper->writeLog("Could not determine the transaction ID of the WS returned XML: " . $xmlStr);
            Mage::throwException("Falha ao processar seu pagamento. Por favor, entre em contato com nossa equipe.");
        }

        return $transaction;
    }

    private function _refundNotPersistedTransactions($payment, $amount)
    {
        // collect the successfully registered transactions
        // and refund them
        foreach($payment->getOrder()->getRelatedObjects() as $object)
        {
            if( $object instanceof Mage_Sales_Model_Order_Payment_Transaction && 
                $object->getTxnType() == Mage_Sales_Model_Order_Payment_Transaction::TYPE_ORDER )
            {
                if(in_array($object->getAdditionalInformation("status"), array("1", "2")))
                {
                    $transactionType = Mage_Sales_Model_Order_Payment_Transaction::TYPE_VOID;
                }
                else if(in_array($object->getAdditionalInformation("status"), array("3", "4", "5")))
                {
                    $transactionType = Mage_Sales_Model_Order_Payment_Transaction::TYPE_REFUND;
                }
                else
                {
                    continue;
                }

                try
                {
                    $this->_createRefundTransaction($payment, $amount, $object->getTxnId(), $transactionType);
                }
                catch(Exception $e)
                {
                    $helper->writeLog("Transaction could not be automatic refunded: " . $object->getTxnId());
                    Mage::logException($e);
                }
            }
        }
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

        return false;
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

}
