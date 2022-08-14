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

    protected $_helper;
    protected $_pHelper;

    public function __construct()
    {
        $this->_helper = Mage::helper('ricardomartins_pagseguro');
        $this->_pHelper = Mage::helper('ricardomartins_pagseguro/params');

        parent::__construct();
    }

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

        $session = Mage::getSingleton('api/session');

        if ($session->isLoggedIn()) {
            $session->setData('PsPayment', serialize($data->getData('PsPayment')));
        }

        $info = $this->getInfoInstance();

        $info->setAdditionalInformation('sender_hash', $this->_pHelper->getPaymentHash('sender_hash'));

        // treat multi credit card data before, 
        // if funcionality is enabled
        if ($this->_helper->isMultiCcEnabled()) {
            $info->setAdditionalInformation("cc1", $this->_extractMultiCcDataFromForm($info, $data, 1));
            $info->setAdditionalInformation("cc2", $this->_extractMultiCcDataFromForm($info, $data, 2));
            $info->setAdditionalInformation("use_two_cards", ($data->getData("use_two_cards") ? 1 : 0));

            if ($data->getData("use_two_cards")) {
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
        } else {
            $info->setAdditionalInformation('credit_card_token', $this->_pHelper->getPaymentHash('credit_card_token'))
                 ->setCcType($this->_pHelper->getPaymentHash('cc_type'))
                 ->setCcLast4(substr($data->getPsCcNumber(), -4));
        }
        
        $info->setAdditionalInformation('credit_card_owner', $data->getPsCcOwner());

        //cpf
        if ($data->getData($this->getCode() . '_cpf')) {
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
     * @param Integer $cardIndex
     */
    public function _extractMultiCcDataFromForm($paymentInfo, $formData, $cardIndex)
    {
        $installments = explode("|", $formData->getData("ps_multicc{$cardIndex}_installments"));

        if ($installments !== false && count($installments) == 2) {
            $installmentsQty = $installments[0];
            $installmentsValue = $installments[1];
        } else {
            $installmentsQty = "";
            $installmentsValue = "";
        }

        $total =  str_replace(".", "", $formData->getData("ps_multicc{$cardIndex}_total"));
        $total = floatval(str_replace(",", ".", $total));

        $cardData = array
        (
            "last4"     => substr(
                $this->_pHelper->removeNonNumbericChars($formData->getData("ps_multicc{$cardIndex}_number")), - 4
            ),
            "token"     => $formData->getData("ps_multicc{$cardIndex}_token"),
            "total"     => $total,
            "brand"     => $formData->getData("ps_multicc{$cardIndex}_brand"),
            "owner"     => $formData->getData("ps_multicc{$cardIndex}_owner"),
            "owner_doc" => $this->_pHelper
                ->removeNonNumbericChars($formData->getData("ps_multicc{$cardIndex}_owner_document")),
            "installments_qty" => $installmentsQty,
            "installments_value" => $installmentsValue,
        );

        if (empty(Mage::getStoreConfig('payment/rm_pagseguro_cc/owner_dob_attribute'))) {
            $dob = str_pad($formData->getData("ps_multicc{$cardIndex}_dob_day"), 2, "0", STR_PAD_LEFT) . "/" . 
                   str_pad($formData->getData("ps_multicc{$cardIndex}_dob_month"), 2, "0", STR_PAD_LEFT) . "/" .
                   $formData->getData("ps_multicc{$cardIndex}_dob_year");
                
            $cardData["owner_dob"] = $dob;
        }

        return $cardData;
    }

    /**
     * Validate payment method information object
     *
     * @return RicardoMartins_PagSeguro_Model_Payment_Cc | false
     */
    public function validate()
    {
        parent::validate();

        $paymentInfo = $this->getInfoInstance();
        $shippingMethod = Mage::getSingleton('checkout/session')->getQuote()->getShippingAddress()->getShippingMethod();

        // verifica se não há método de envio selecionado antes de exibir o erro de falha no cartão de crédito - Weber
        if (empty($shippingMethod)) {
            return false;
        }

        $senderHash = $this->_pHelper->getPaymentHash('sender_hash');

        if ($this->isMultiCardPayment($paymentInfo)) {
            $cc1 = $paymentInfo->getAdditionalInformation("cc1");
            $this->_validateCardAndSenderHashes($cc1["token"], $senderHash, " 1");

            $cc2 = $paymentInfo->getAdditionalInformation("cc2");
            $this->_validateCardAndSenderHashes($cc2["token"], $senderHash, " 2");
        } else {
            $creditCardToken = $this->_helper->isMultiCcEnabled()
                                    ? $paymentInfo->getAdditionalInformation("credit_card_token")
                                    : $this->_pHelper->getPaymentHash("credit_card_token");

            $this->_validateCardAndSenderHashes($creditCardToken, $senderHash);
        }

        return $this;
    }

    /**
     * @param        $creditCardToken
     * @param        $senderHash
     * @param string $cardSuffix
     *
     * @throws Mage_Core_Exception
     */
    protected function _validateCardAndSenderHashes($creditCardToken, $senderHash, $cardSuffix = "")
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
        if ((!$creditCardToken || !$senderHash) && !in_array($pathRequest, $configPaths)) {
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
        if ($this->isMultiCardPayment($payment)) {
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
        } else {
            $transaction = $this->_order($payment, $amount, 1);
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
    protected function _order($payment, $amount, $ccIdx)
    {
        $order = $payment->getOrder();

        $order->setState(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, true);

        if ($this->isMultiCardPayment($payment)) {
            $cardData = $payment->getAdditionalInformation("cc" . $ccIdx);
            $payment->setData("_current_card_index", $ccIdx);
            $payment->setData("_current_card_total_multiplier", ($cardData["total"] / $amount));
            $amount = (float) $cardData["total"];
        }

        $params = Mage::helper('ricardomartins_pagseguro/internal')->getCreditCardApiCallParams($order, $payment);
        
        // call API
        $returnXml = $this->callApi($params, $payment);
        
        try {
            // creates Magento transactions
            $transaction = $this->_createOrderTransaction($payment, $amount, $ccIdx, $returnXml);
            $this->proccessNotificatonResult($returnXml);
        } catch (Mage_Core_Exception $e) {
            //retry if error is related to installment value
            if ($this->getIsInvalidInstallmentValueError()
                && !$payment->getAdditionalInformation(
                    'retried_installments_' . $ccIdx
                )) {
                return $this->recalculateInstallmentsAndPlaceOrder($payment, $amount);
            }

            if ($this->_helper->canRetryOrder($order)) {
                $order->addStatusHistoryComment(
                    'A retentativa de pedido está ativa. O pedido foi concluído mesmo com o seguite erro: '
                    . $e->getMessage()
                );
            }

            //only throws exception if payment retry is disabled
            //read more at https://bit.ly/3b2onpo
            if (!$this->_helper->isRetryActive()) {
                Mage::throwException($e->getMessage());
            }
        }

        return $transaction;
    }

    /**
     * Creates an order transaction interpreting the XML returned by PagSeguro
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param SimpleXMLElement $returnXml
     * 
     * @return Mage_Sales_Model_Order_Payment_Transaction
     */
    protected function _createOrderTransaction($payment, $amount, $ccIdx, $returnXml)
    {
        $notification = Mage::getModel(
            "ricardomartins_pagseguro/payment_notification", array("document" => $returnXml)
        );

        if (!$notification->getTransactionId() || $notification->hasErrors()) {
            $this->_helper->writeLog(
                "Could not determine the transaction ID of the WS returned XML: " . $notification->returnXMLasString()
            );
            $this->setIsInvalidInstallmentValueError($notification->hasInstallmentsError());
            Mage::throwException($notification->getErrorsDescription());
        }

        // avoid Magento transaction automatic creation  to use our 
        // own logic
        $payment->setSkipOrderProcessing(true);
        
        // legacy code: store transaction ID on additional information
        $additional = array('transaction_id'=> $notification->getTransactionId());
        if ($existing = $payment->getAdditionalInformation()) {
            if (is_array($existing)) {
                $additional = array_merge($additional, $existing);
            }
        }

        $payment->setAdditionalInformation($additional);

        // new approach: use transaction from pagseguro to 
        // generate a magento transaction
        $payment->setTransactionId($notification->getTransactionId());
        $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_ORDER);
        
        switch($notification->getStatus())
        {
            case self::PS_TRANSACTION_STATUS_PENDING_PAYMENT:
            case self::PS_TRANSACTION_STATUS_REVIEW:
                $transaction->setIsClosed(false);
                break;
            
            case self::PS_TRANSACTION_STATUS_PAID:
            case self::PS_TRANSACTION_STATUS_AVAILABLE:
                $transaction->setIsClosed(true);
                break;
            
            default:
                Mage::throwException("Falha ao processar seu pagamento. Por favor, entre em contato com nossa equipe.");
        }

        $transactionDetails = array
        (
            "reference"       => $notification->getReference(),
            "status"          => $notification->getStatus(),
            "last_event_date" => $notification->getLastEventDate(),
            "remote_value"    => $notification->getGrossAmount(),
            "frontend_value"  => $amount,
        );

        $transaction->setAdditionalInformation("status", $notification->getStatus());
        $transaction->setAdditionalInformation("last_event_date", $notification->getLastEventDate());
        $transaction->setAdditionalInformation("remote_value", $notification->getGrossAmount());
        $transaction->setAdditionalInformation("frontend_value", $amount);
        
        if (isset($returnXml->gatewaySystem)) {
            if($notification->getAuthorizationCode()) $notification->getAuthorizationCode();
            if($notification->getNsu()) $notification->getNsu();
            if($notification->getTid()) $notification->getTid();
        }
        
        // update references to transactions on card data
        $cardData = $payment->getAdditionalInformation("cc" . $ccIdx);
        $cardData["transaction_id"] = $transaction ? $transaction->getTxnId() : "";
        $payment->setAdditionalInformation("cc" . $ccIdx, $cardData);

        $payment->setAdditionalInformation("transaction_id", $cardData["transaction_id"]); // legacy flag

        $transaction->setAdditionalInformation(
            Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $transactionDetails
        );

        return $transaction;
    }

    /**
     * Iterates through objects associated with the order to find and 
     * refund successfully order transactions stored in memory
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param float $amount
     */
    protected function _refundNotPersistedTransactions($payment, $amount)
    {
        // considers all objects associated to order
        foreach ($payment->getOrder()->getRelatedObjects() as $object) {
            // filters objects to consider only order transactions
            if ($object instanceof Mage_Sales_Model_Order_Payment_Transaction &&
                $object->getTxnType() == Mage_Sales_Model_Order_Payment_Transaction::TYPE_ORDER) {
                continue;
            }
            
            try
            {
                $this->_refundOrderTransaction($payment, $amount, $object);
            }
            catch(Exception $e)
            {
                $this->_helper->writeLog("Transaction could not be automatic refunded: " . $object->getTxnId());
                Mage::logException($e);
            }
        }
    }

    /**
     * Refund payment
     *
     * @param Varien_Object $payment
     * @param float $amount
     *
     * @return RicardoMartins_PagSeguro_Model_Payment_Abstract
     */
    public function refund(Varien_Object $payment, $amount)
    {
        $orderTransactions = $this->getOrderTransactions($payment);

        // necessary verification to maintain retro compatibility
        if (!$orderTransactions || count($orderTransactions) == 0) {
            return parent::refund($payment, $amount);
        }

        foreach ($orderTransactions as $transaction) {
            $this->_consultOrderTransactionStatus($payment, $transaction);

            if (!$this->_canRefundTransaction($transaction)) {
                continue;
            }
                
            try
            {
                $transactionAmount = $amount * floatval($transaction->getAdditionalInformation("remote_value"))
                    / $payment->getOrder()->getGrandTotal();

                $this->_refundOrderTransaction($payment, $transactionAmount, $transaction->getTxnId());
            }
            catch(Exception $e)
            {
                if (!Mage::registry("rm_pagseguro_force_refund_order")) {
                    throw $e;
                }
                
                $payment->getOrder()->addStatusHistoryComment($e->getMessage());
            }
        }

        return $this;
    }

    /**
     * Iterates through objects associated with the order to find and 
     * refund successfully order transactions stored in memory
     * @param Varien_Object $payment
     * @return Mage_Payment_Model_Abstract
     */
    public function void(Varien_Object $payment)
    {
        foreach ($this->getOrderTransactions($payment) as $transaction) {
            $this->_consultOrderTransactionStatus($payment, $transaction);

            if (!$this->_canRefundTransaction($transaction)) {
                continue;
            }

            try {
                $this->_refundOrderTransaction(
                    $payment, (float)$transaction->getAdditionalInformation("remote_value"), $transaction
                );
            } catch (Exception $e) {
                if (!Mage::registry("rm_pagseguro_force_refund_order")) {
                    throw $e;
                }
                
                $payment->getOrder()->addStatusHistoryComment($e->getMessage());
            }
        }

        return $this;
    }

    /**
     * Comunicates with PagSeguro web service and creates a Magento 
     * refund transacation object
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param string $parentTransactionId
     * @param string $transactionType
     * 
     * @return Mage_Sales_Model_Order_Payment_Transaction
     */
    protected function _refundOrderTransaction($payment, $amount, $parentTransaction)
    {
        if (is_string($parentTransaction)) {
            $parentTransaction = $payment->lookupTransaction($parentTransaction);
        }

        if (!($parentTransaction instanceof Mage_Sales_Model_Order_Payment_Transaction)) {
            Mage::throwException("Transaction could not be refunded: invalid parent transaction.");
        }

        $transactionType = null;
        // determine the type of refund transaction, based 
        // on parent transaction status on PagSeguro
        switch($parentTransaction->getAdditionalInformation("status"))
        {
            case self::PS_TRANSACTION_STATUS_PENDING_PAYMENT:
            case self::PS_TRANSACTION_STATUS_REVIEW:
                $transactionType = Mage_Sales_Model_Order_Payment_Transaction::TYPE_VOID;
                $successStatus = self::PS_TRANSACTION_STATUS_CANCELED;
                $uri = 'transactions/cancels';
                break;
            
            case self::PS_TRANSACTION_STATUS_PAID:
            case self::PS_TRANSACTION_STATUS_AVAILABLE:
            case self::PS_TRANSACTION_STATUS_CONTESTED:
                $transactionType = Mage_Sales_Model_Order_Payment_Transaction::TYPE_REFUND;
                $successStatus = self::PS_TRANSACTION_STATUS_REFUNDED;
                $uri = 'transactions/refunds';
                break;
            
            case self::PS_TRANSACTION_STATUS_REFUNDED:
            case self::PS_TRANSACTION_STATUS_CANCELED:
            case self::PS_TRANSACTION_STATUS_DEBITED:
                return;
            
            default:
                // the transaction status doest not permit refund
                Mage::throwException(
                    sprintf(
                        "Transaction %s could not be refunded, due to its status: %s",
                        $parentTransaction->getTxnId(),
                        $parentTransaction->getAdditionalInformation("status")
                    ));
        }

        // comunicates with PagSeguro
        $params = array('transactionCode' => $parentTransaction->getTxnId());

        if ($this->_helper->getLicenseType() != 'app') {
            $params['token'] = $this->_helper->getToken();
            $params['email'] = $this->_helper->getMerchantEmail();
        }
        
        if (!$this->isMultiCardPayment($payment) &&
            $transactionType == Mage_Sales_Model_Order_Payment_Transaction::TYPE_REFUND) {
            $params['refundValue'] = number_format($amount, 2, '.', '');
        }

        $returnXml = $this->callApi($params, $payment, $uri);
        $notification = Mage::getModel(
            "ricardomartins_pagseguro/payment_notification", array("document" => $returnXml)
        );

        $message = sprintf(
            "[Transação %s] Comunicando solicitação de cancelamento à PagSeguro. %s",
            $parentTransaction->getTxnId(),
            $notification->hasErrors() ? $notification->getErrorsDescription() : ""
        );
        $payment->getOrder()->addStatusHistoryComment($message);

        if ($notification->hasErrors()) {
            $message = sprintf
            (
                "Erro ao solicitar o reembolso para a transação %s: %s", 
                $parentTransaction->getTxnId(),
                $parentTransaction->getErrorsDescription()
            );
            Mage::throwException($message);
        }

        // update the parent transaction status
        $this->_updateOrderTransactionStatus
        (
            $payment, 
            $parentTransaction, 
            $successStatus
        );

        $payment->setTransactionId($parentTransaction->getTxnId() . "-" . $transactionType);
        $payment->setParentTransactionId($parentTransaction->getTxnId());
        $payment->setShouldCloseParentTransaction(true);
        
        return $payment->addTransaction($transactionType);
    }

    /**
     * Consults transaction status on PagSeguro web service
     * @param Mage_Sales_Model_Order_Payment_Transaction $transaction
     * 
     * @return Boolean
     */
    protected function _consultOrderTransactionStatus($payment, & $transaction)
    {
        $response = $this->_helper->getOrderStatusXML($transaction->getTxnId(), $this->_helper->isSandbox());
        $responseXml = simplexml_load_string($response);

        if (!$responseXml || !isset($responseXml->status)) {
            return false;
        }

        $newStatus = (string) $responseXml->status;
        
        // checks if the status hasnt changed, everything its 
        // ok and lets consider updated
        if ($newStatus == $transaction->getAdditionalInformation("status")) {
            return true;
        }

        // otherwise, registers the new status data
        $transactionDetails = $transaction
            ->getAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS);
        $transaction->setAdditionalInformation("status", $newStatus);
        $transactionDetails["status"] = $newStatus;

        if (isset($responseXml->last_event_date)) {
            $transaction->setAdditionalInformation("last_event_date", (string) $responseXml->last_event_date);
            $transactionDetails["last_event_date"] = (string) $responseXml->last_event_date;
        }
        
        $transaction
            ->setAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $transactionDetails);
        $transaction->setOrderPaymentObject($payment);
        $transaction->save();

        // if was remotely refunded, registers locally the remote transaction
        $refundedStatus = array
        (
            self::PS_TRANSACTION_STATUS_REFUNDED,
            self::PS_TRANSACTION_STATUS_DEBITED,
        );
        $cancelledStatus = array
        (
            self::PS_TRANSACTION_STATUS_CANCELED,
        );

        $wasRefunded = in_array($newStatus, $refundedStatus);
        $wasCancelled = in_array($newStatus, $cancelledStatus);

        if ($wasRefunded || $wasCancelled) {
            $refundTransaction = $this->_registerRemoteRefundTransaction
            (
                $payment, 
                $transaction, 
                $wasRefunded 
                    ? Mage_Sales_Model_Order_Payment_Transaction::TYPE_REFUND
                    : Mage_Sales_Model_Order_Payment_Transaction::TYPE_VOID
            );

            $refundTransaction->save();
        }

        return true;
    }

    /**
     * Closes transactions and create the order invoice, besides the specific
     * verifications for payments using credit card
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param RicardoMartins_PagSeguro_Model_Payment_Notification $notification
     */
    protected function _confirmPayment($payment, $notification)
    {
        // update transaction data
        $transactionId = $notification->getTransactionId();
        $orderTransaction = $payment->lookupTransaction(
            $transactionId, Mage_Sales_Model_Order_Payment_Transaction::TYPE_ORDER
        );

        if ($orderTransaction) {
            $transactionDetails = $orderTransaction->getAdditionalInformation(
                Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS
            );
            $transactionDetails["status"] = $notification->getStatus();
            $transactionDetails["closed_on"] = Zend_Date::now()->toString("YYYY-MM-DD HH:mm:ss");
            $orderTransaction->setAdditionalInformation(
                Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $transactionDetails
            );
            $orderTransaction->setAdditionalInformation("status", $notification->getStatus());
            $orderTransaction->setIsClosed(true);
            $orderTransaction->save();
        }
        
        // if its two cards payment, verifies if the other transaction allows invoice
        if ($this->isMultiCardPayment($payment)) {
            $notAllowedStatus = array
            (
                //self::PS_TRANSACTION_STATUS_CONTESTED,
                self::PS_TRANSACTION_STATUS_REFUNDED,
                self::PS_TRANSACTION_STATUS_CANCELED,
                self::PS_TRANSACTION_STATUS_DEBITED,
                //self::PS_TRANSACTION_STATUS_TEMPORARY_RETENTION,
            );
            
            $anotherTransaction = $this->getAnotherOrderTransaction($payment, $transactionId);
                
            if (in_array($anotherTransaction->getAdditionalInformation("status"), $notAllowedStatus)) {
                Mage::throwException(sprintf
                (
                    "Could not confirm payment of the order #%s. Transaction %s: status %s.", 
                    $payment->getOrder()->getIncrementId(),
                    $anotherTransaction->getTxnId(),
                    $anotherTransaction->getAdditionalInformation("status")
                ));
            }
        }

        parent::_confirmPayment($payment, $notification);
    }

    /**
     * {@inheritdoc }. Additionally, update the transaction data on Magento.
     */
    protected function _refundOrder($payment, $notification)
    {
        // registers the transaction status informed by the current notification
        $orderTransaction = $this->_updateOrderTransactionStatus
        (
            $payment, 
            $notification->getTransactionId(), 
            $notification->getStatus()
        );

        // registers the new refund transaction, based on the current notification
        if ($orderTransaction) {
            $refundTransaction = $this->_registerRemoteRefundTransaction
            (
                $payment, 
                $orderTransaction, 
                Mage_Sales_Model_Order_Payment_Transaction::TYPE_REFUND
            );
        }

        // prevents the refund action on pagseguro to throw an exception
        if ($this->isMultiCardPayment($payment)) {
            Mage::register("rm_pagseguro_force_refund_order", true);
        }

        $order = $payment->getOrder();

        // unhold order, to allow further actions
        if ($order->canUnhold()) {
            $order->unhold();
        }

        // in cases that there arent invoices and the order
        // can be canceled
        if ($order->canCancel()) {
            $order->cancel();
        } else if ($order->canCreditmemo()) {
        // in cases that there are invoices and the order
        // must be refunded
            foreach ($order->getInvoiceCollection() as $invoice) {
                $service = Mage::getModel('sales/service_order', $order);

                $creditmemo = $service->prepareInvoiceCreditmemo($invoice);
                $creditmemo->setRefundRequested(true)
                        ->setOfflineRequested(false)
                        ->register();

                Mage::getModel('core/resource_transaction')
                    ->addObject($creditmemo)
                    ->addObject($creditmemo->getOrder())
                    ->addObject($creditmemo->getInvoice())
                    ->save();
            }
        } else {
        // for unpredictable situations: if cannot cancel or refund, must 
        // force the Closed status
            $payment->registerRefundNotification($notification->getGrossAmount());
            $order->addStatusHistoryComment('Devolvido: o valor foi devolvido ao comprador.');
        }
    }

    /**
     * {@inheritdoc }. Additionally, update the transaction data on Magento.
     */
    protected function _cancelOrder($payment, $notification)
    {
        // register the transaction status informed by the current notification
        $orderTransaction = $this->_updateOrderTransactionStatus
        (
            $payment, 
            $notification->getTransactionId(), 
            $notification->getStatus()
        );

        // register the new cancel transaction, based on the current notification
        if ($orderTransaction) {
            $cancelTransaction = $this->_registerRemoteRefundTransaction
            (
                $payment, 
                $orderTransaction, 
                Mage_Sales_Model_Order_Payment_Transaction::TYPE_VOID
            );
        }

        // prevents the refund action on pagseguro to throw an exception
        if ($this->isMultiCardPayment($payment)) {
            Mage::register("rm_pagseguro_force_refund_order", true);
        }

        parent::_cancelOrder($payment, $notification);
    }

    /**
     * {@inheritdoc }. Additionally, update the transaction data on Magento.
     */
    protected function _holdOrder($payment, $notification)
    {
        $orderTransaction = $payment->lookupTransaction(
            $notification->getTransactionId(), Mage_Sales_Model_Order_Payment_Transaction::TYPE_ORDER
        );

        if ($orderTransaction) {
            $transactionDetails = $orderTransaction->getAdditionalInformation(
                Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS
            );
            $transactionDetails["status"] = $notification->getStatus();
            $orderTransaction->setAdditionalInformation(
                Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $transactionDetails
            );
            $orderTransaction->setAdditionalInformation("status", $notification->getStatus());
            $orderTransaction->save();
        }

        parent::_holdOrder($payment, $notification);
    }

    /**
     * Stores transaction status on Magento database
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param mixed $orderTransaction
     * @param String $status
     * 
     * @return Mage_Sales_Model_Order_Payment_Transaction
     */
    protected function _updateOrderTransactionStatus($payment, $orderTransaction, $status)
    {
        if (!($orderTransaction instanceof Mage_Sales_Model_Order_Payment_Transaction)) {
            $orderTransaction = $payment->lookupTransaction(
                $orderTransaction, Mage_Sales_Model_Order_Payment_Transaction::TYPE_ORDER
            );
        }

        if (!$orderTransaction) {
            return false;
        }

        // avoid unnecessary update
        if ($orderTransaction->getAdditionalInformation("status") == $status) {
            return $orderTransaction;
        }

        $transactionDetails = $orderTransaction->getAdditionalInformation(
            Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS
        );
        $transactionDetails["status"] = $status;
        $orderTransaction->setAdditionalInformation("status", $status);
        $orderTransaction->setAdditionalInformation(
            Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $transactionDetails
        );
        $orderTransaction->setOrderPaymentObject($payment);
        $orderTransaction->save();

        return $orderTransaction;
    }

    /**
     * Creates a transaction on Magento that represents a remote refund transaction
     * alreay created on PagSeguro environment
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param Mage_Sales_Model_Order_Payment_Transaction $orderTransaction
     * @param String $transactionType
     * 
     * @return Mage_Sales_Model_Order_Payment_Transaction
     */
    protected function _registerRemoteRefundTransaction($payment, $parentTransaction, $transactionType)
    {
        $newTransactionId = $parentTransaction->getTxnId() . "-" . $transactionType;
        
        // avoid duplication of the transaction
        if ($newTransaction = $payment->lookupTransaction($newTransactionId, $transactionType)) {
            return $newTransaction;
        }

        $payment->setTransactionId($newTransactionId);
        $payment->setParentTransactionId($parentTransaction->getTxnId());
        $payment->setShouldCloseParentTransaction(true);
        
        return $payment->addTransaction($transactionType);
    }

    /**
     * Creates a transaction on Magento that represents a remote refund transaction
     * alreay created on PagSeguro environment
     * @param Mage_Sales_Model_Order_Payment_Transaction $transaction
     * 
     * @return Boolean
     */
    protected function _canRefundTransaction($transaction)
    {
        if (!($transaction instanceof Mage_Sales_Model_Order_Payment_Transaction)) {
            return false;
        }

        if ($transaction->getTxnType() != Mage_Sales_Model_Order_Payment_Transaction::TYPE_ORDER) {
            return false;
        }

        switch ($transaction->getAdditionalInformation("status")) {
            case self::PS_TRANSACTION_STATUS_REFUNDED:
            case self::PS_TRANSACTION_STATUS_CANCELED:
            case self::PS_TRANSACTION_STATUS_DEBITED:
                return false;
        }

        return true;
    }

    /**
     * Retrieves the order transactions associated with the payment
     */
    public function getOrderTransactions($payment)
    {
        return Mage::getModel('sales/order_payment_transaction')->getCollection()
                            ->setOrderFilter($payment->getOrder())
                            ->addPaymentIdFilter($payment->getId())
                            ->addTxnTypeFilter(Mage_Sales_Model_Order_Payment_Transaction::TYPE_ORDER)
                            ->setOrder('created_at', Varien_Data_Collection::SORT_ORDER_DESC)
                            ->setOrder('transaction_id', Varien_Data_Collection::SORT_ORDER_DESC);
    }

    /**
     * Retrieves the order transaction that isnt the one passed as parameter
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param mixed $oneTransaction
     * 
     * @return Mage_Sales_Model_Order_Payment_Transaction
     */
    public function getAnotherOrderTransaction($payment, $oneTransaction)
    {
        if ($oneTransaction instanceof Mage_Sales_Model_Order_Payment_Transaction) {
            $oneTransaction = $oneTransaction->getTxnId();
        }

        foreach ($this->getOrderTransactions($payment) as $anotherTransaction) {
            if ($anotherTransaction->getTxnId() != $oneTransaction) {
                return $anotherTransaction;
            }
        }

        return null;
    }

    /**
     * Retrieves the order transactions associated with the payment
     * @param Mage_Sales_Model_Order_Payment $payment
     * 
     * @return Boolean
     */
    public function isMultiCardPayment($payment)
    {
        return $this->_helper->isMultiCcEnabled() && $payment->getAdditionalInformation("use_two_cards");
    }

    /**
     * {@inheritdoc }
     */
    public function canRefundPartialPerInvoice()
    {
        return !$this->isMultiCardPayment($this->getInfoInstance());
    }

    /**
     * Checks if order can be invoiced, verifying its transactions
     * @param Mage_Sales_Model_Order_Payment $payment
     * 
     * @return Boolean
     */
    protected function _methodAllowsOrderInvoice($payment)
    {
        foreach ($this->getOrderTransactions($payment) as $transaction) {
            if (!$this->_isOrderTransactionConfirmed($transaction)) {
                return false;
            }
        }

        return parent::_methodAllowsOrderInvoice($payment);
    }

    /**
     * Checks if a transaction can be invoiced
     * @param Mage_Sales_Model_Order_Payment_Transaction $transaction
     * 
     * @return Boolean
     */
    protected function _isOrderTransactionConfirmed($transaction)
    {
        if (!$transaction->getIsClosed()) {
            return false;
        }
        
        switch ($transaction->getAdditionalInformation("status")) {
            case self::PS_TRANSACTION_STATUS_PAID:
            case self::PS_TRANSACTION_STATUS_AVAILABLE:
                return true;
        }

        return false;
    }

    /**
     * {@inheritdoc }
     */
    public function processStatus($statusCode, $notification = null)
    {
        $processedState = parent::processStatus($statusCode, $notification);
        $payment = $this->getInfoInstance();

        // avoids status change when there is one pending transaction
        $confirmedStatus = array
        (
            self::PS_TRANSACTION_STATUS_PAID,
            self::PS_TRANSACTION_STATUS_AVAILABLE,
        );

        $isMultiCard = $this->isMultiCardPayment($payment);
        if ($isMultiCard &&
            $notification && 
            in_array($notification->getStatus(), $confirmedStatus)
        ) {
            $anotherTransaction = $this->getAnotherOrderTransaction($payment, $notification->getTransactionId());
            
            if ($anotherTransaction &&
                !in_array($anotherTransaction->getAdditionalInformation("status"), $confirmedStatus)) {
                $processedState->setStateChanged(false);
                $processedState->setIsCustomerNotified(false);
            }
        }

        // if is refunding, unsets the 'state changed' flag, because the
        // change of state must be realized by the credit memo creation
        if ($notification && $notification->getStatus() == self::PS_TRANSACTION_STATUS_REFUNDED) {
            $processedState->setStateChanged(false);
            $processedState->setIsCustomerNotified(true);
        }

        // adds card last 4 digits on comment, to helps on its identification
        if ($notification) {
            $last4 = $payment->getCcLast4();
            
            if ($isMultiCard) {
                $cc1Data = $payment->getAdditionalInformation("cc1");

                if ($cc1Data
                    && isset($cc1Data["transaction_id"])
                    && $cc1Data["transaction_id"] == $notification->getTransactionId()) {
                    $last4 = isset($cc1Data["last4"]) ? $cc1Data["last4"] : "";
                }

                $cc2Data = $payment->getAdditionalInformation("cc2");

                if ($cc2Data
                    && isset($cc2Data["transaction_id"])
                    && $cc2Data["transaction_id"] == $notification->getTransactionId()) {
                    $last4 = isset($cc2Data["last4"]) ? $cc2Data["last4"] : "";
                }
            }

            $processedState->setMessage(sprintf("[Cartão de final %s] %s", $last4, $processedState->getMessage()));
        }

        return $processedState;
    }

    /**
     * Searches for transaction and retrieves its status. If is not multi card
     * payment, just returns the cached info on payment additional information
     * @param $transactionId
     * @return String
     */
    public function getTransactionStatus($transactionId = null)
    {
        if ($this->isMultiCardPayment($this->getInfoInstance())) {
            if (!$transactionId) {
                throw new Exception("Transaction ID must be informed to consult status of multi card payments");
            }

            $transaction = $this->getInfoInstance()->lookupTransaction(
                $transactionId, Mage_Sales_Model_Order_Payment_Transaction::TYPE_ORDER
            );

            if (!$transaction) {
                Mage::throwException("Could not load transaction " . $transactionId);
            }

            return $transaction->getAdditionalInformation("status");
        }

        return parent::getTransactionStatus($transactionId);
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
        $sandbox = $this->_helper->isSandbox() ? 'sandbox.' : '';
        $sessionId = $this->_helper->getSessionId();
        $url = "https://{$sandbox}pagseguro.uol.com.br/checkout/v2/installments.json?sessionId=$sessionId&amount=$amount";
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
        $ccIdx = $payment->getData("_current_card_index");
        $retriedInstallmentsFlag = 'retried_installments_' . ($ccIdx ? $ccIdx : 1);

        //avoid being fired twice due to error.
        if ($payment->getAdditionalInformation($retriedInstallmentsFlag)) {
            return;
        }

        $payment->setAdditionalInformation($retriedInstallmentsFlag, true);
        Mage::log(
            'Houve uma inconsistência no valor dar parcelas. '
            . 'As parcelas serão recalculadas e uma nova tentativa será realizada.',
            null, 'pagseguro.log', true
        );
        
        $selectedMaxInstallmentNoInterest = $this->_helper->getMaxInstallmentsNoInterest($amount);
        $installmentsQty = $payment->getAdditionalInformation('installment_quantity');
        $ccType = $payment->getCcType();
        
        // if it's a multi card transaction, updates the card data used on consult
        if ($ccIdx) {
            $cardData = $payment->getAdditionalInformation("cc" . $ccIdx);
            
            if (is_array($cardData)) {
                $installmentsQty = isset($cardData["installments_qty"]) ? $cardData["installments_qty"] : '';
                $ccType = isset($cardData["brand"]) ? $cardData["brand"] : '';
            }
        }
        
        $installmentValue = number_format($this->getInstallmentValue(
            $amount,
            $ccType,
            $installmentsQty,
            $selectedMaxInstallmentNoInterest
        ), 2, '.', '');

        // if it's a multi card transaction, updates the installments value for the card
        if ($ccIdx) {
            $cardData = $payment->getAdditionalInformation("cc" . $ccIdx);
            
            if (is_array($cardData) && isset($cardData["installments_value"])) {
                $cardData["installments_value"] = $installmentValue;
                $payment->setAdditionalInformation("cc" . $ccIdx, $cardData);
            }
        }

        $payment->setAdditionalInformation('installment_value', $installmentValue);
        $payment->setAdditionalInformation($retriedInstallmentsFlag, true);
        Mage::unregister('sales_order_invoice_save_after_event_triggered');

        try {
            // if its a multi card transaction, resets the total amount so that the calculations
            // can be done again on the _order action
            if ($ccIdx) {
                $amount = $payment->getOrder()->getGrandTotal();
            }

            $this->_order($payment, $amount, $ccIdx ? $ccIdx : 1);

        } catch (Exception $e) {
            Mage::throwException($e->getMessage());
        }
    }

    public function getInstructions()
    {
        return trim($this->getConfigData('instructions'));
    }
}
