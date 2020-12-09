<?php
/**
 * PagSeguro Transparente Magento
 * PagSeguro Abstract Model Class - Used on processing and sending information to/from PagSeguro
 *
 * @category    RicardoMartins
 * @package     RicardoMartins_PagSeguro
 * @author      Ricardo Martins
 * @copyright   Copyright (c) 2015 Ricardo Martins (http://r-martins.github.io/PagSeguro-Magento-Transparente/)
 * @license     https://opensource.org/licenses/MIT MIT License
 */
class RicardoMartins_PagSeguro_Model_Abstract extends Mage_Payment_Model_Method_Abstract
{

    /** @var Mage_Sales_Model_Order $_order */
    protected $_order;

    /**
     * Processes notification XML data. XML is sent right after order is sent to PagSeguro, and on order updates.
     *
     * @see https://pagseguro.uol.com.br/v2/guia-de-integracao/api-de-notificacoes.html#v2-item-servico-de-notificacoes
     *
     * @param SimpleXMLElement $resultXML
     *
     * @return $this
     * @throws Mage_Core_Exception
     * @throws Varien_Exception
     */
    public function proccessNotificatonResult(SimpleXMLElement $resultXML)
    {
        $helper = Mage::helper('ricardomartins_pagseguro');
        // prevent this event from firing twice
        if (Mage::registry('sales_order_invoice_save_after_event_triggered')) {
            return $this; // this method has already been executed once in this request
        }

        Mage::register('sales_order_invoice_save_after_event_triggered', true);

        $resultXMLErrorCodes = array();
        if (isset($resultXML->errors)) {
            foreach ($resultXML->errors as $error) {
                $errMsg[] = $this->_getHelper()->__((string)$error->message) . ' (' . $error->code . ')';
                $resultXMLErrorCodes[] = (int)$error->code;
            }

            if ($error->code == '53041') { //installment value invalid value
                $this->setIsInvalidInstallmentValueError(true);
            }
            $this->setData('resultXMLErrorCodes', $resultXMLErrorCodes);
            Mage::throwException('Um ou mais erros ocorreram no seu pagamento.' . PHP_EOL . implode(PHP_EOL, $errMsg));
        }

        if (isset($resultXML->error)) {
            $error = $resultXML->error;
            $errMsg[] = $this->_getHelper()->__((string)$error->message) . ' (' . $error->code . ')';
            $resultXMLErrorCodes[] = (int)$error->code;

            if ($error->code == '53041') { //installment value invalid value
                $this->setIsInvalidInstallmentValueError(true);
            }

            if (count($resultXML->error) > 1) {
                unset($errMsg);
                foreach ($resultXML->error as $error) {
                    $errMsg[] = $this->_getHelper()->__((string)$error->message) . ' (' . $error->code . ')';
                    $resultXMLErrorCodes[] = (int)$error->code;
                }
            }

            $this->setData('resultXMLErrorCodes', $resultXMLErrorCodes);
            Mage::throwException('Um erro ocorreu em seu pagamento.' . PHP_EOL . implode(PHP_EOL, $errMsg));
        }

        if (isset($resultXML->reference)) {
            /** @var Mage_Sales_Model_Order $order */
            $orderNo = (string)$resultXML->reference;
            if (strstr($orderNo, 'kiosk_') !== false) {
                $kioskNotification = new Varien_Object();
                $kioskNotification->setOrderNo($orderNo);
                $kioskNotification->setNotificationXml($resultXML);
                Mage::dispatchEvent(
                    'ricardomartins_pagseguro_kioskorder_notification_received',
                    array('kiosk_notification' => $kioskNotification)
                );
                $orderNo = $kioskNotification->getOrderNo();
            }

            $order = Mage::getModel('sales/order')->loadByIncrementId($orderNo);
            if (!$order->getId()) {
                $helper->writeLog(
                    sprintf(
                        'Pedido %s não encontrado no sistema. Impossível processar retorno. '
                        . 'Uma nova tentativa deverá feita em breve pelo PagSeguro.', $orderNo
                    )
                );

                return false;
            }

            $this->_order = $order;
            $payment = $order->getPayment();

            $this->_code = $payment->getMethod();
            $processedState = $this->processStatus((int)$resultXML->status);

            $message = $processedState->getMessage();

            if (in_array((int)$resultXML->status, array(6,8))) { //valor devolvido (gera credit memo e tenta cancelar o pedido)
                if ($order->canUnhold()) {
                    $order->unhold();
                }

                if ($order->canCancel()) {
                    $order->cancel();
                    $order->save();
                } else {
                    $payment->registerRefundNotification(floatval($resultXML->grossAmount));
                    $order->addStatusHistoryComment(
                        'Devolvido: o valor foi devolvido ao comprador.'
                    )->save();
                }
            }

            if ((int)$resultXML->status == 7 && isset($resultXML->cancellationSource)) {
                //Especificamos a fonte do cancelamento do pedido
                switch((string)$resultXML->cancellationSource)
                {
                    case 'INTERNAL':
                        $message .= ' O próprio PagSeguro negou ou cancelou a transação.';
                        break;
                    case 'EXTERNAL':
                        $message .= ' A transação foi negada ou cancelada pela instituição bancária.';
                        break;
                }

                $orderCancellation = new Varien_Object();
                $orderCancellation->setData(array(
                   'should_cancel' => true,
                   'cancellation_source' => (string)$resultXML->cancellationSource,
                   'order'        => $order,
                ));
                Mage::dispatchEvent('ricardomartins_pagseguro_before_cancel_order', array(
                    'order_cancellation' => $orderCancellation
                ));

                if ($orderCancellation->getShouldCancel()) {
                    $order->cancel();
                }
            }

            if ($processedState->getStateChanged()) {
                // somente para o status 6 que edita o status do pedido - Weber
                if ((int)$resultXML->status != 6) {
                    $order->setState(
                        $processedState->getState(),
                        true,
                        $message,
                        $processedState->getIsCustomerNotified()
                    )->save();
                }

            } else {
                $order->addStatusHistoryComment($message);
            }

            if ((int)$resultXML->status == 3) { //Quando o pedido foi dado como Pago
                // cria fatura e envia email (se configurado)
                // $payment->registerCaptureNotification(floatval($resultXML->grossAmount));
                if(!$order->hasInvoices()){
                    $invoice = $order->prepareInvoice();
                    $invoice->register()->pay();
                    $msg = sprintf('Pagamento capturado. Identificador da Transação: %s', (string)$resultXML->code);
                    $invoice->addComment($msg);
                    $invoice->sendEmail(
                        Mage::getStoreConfigFlag('payment/rm_pagseguro/send_invoice_email'),
                        'Pagamento recebido com sucesso.'
                    );

                    // salva o transaction id na invoice
                    if (isset($resultXML->code)) {
                        $invoice->setTransactionId((string)$resultXML->code)->save();
                    }

                    Mage::getModel('core/resource_transaction')
                        ->addObject($invoice)
                        ->addObject($invoice->getOrder())
                        ->save();
                    $order->addStatusHistoryComment(
                        sprintf('Fatura #%s criada com sucesso.', $invoice->getIncrementId())
                    );
                }
            }

            $payment->save();

            if (isset($resultXML->feeAmount) && isset($resultXML->netAmount)) {
                $payment
                    ->setAdditionalInformation('fee_amount', floatval($resultXML->feeAmount))
                    ->setAdditionalInformation('net_amount', floatval($resultXML->netAmount))
                    ->save();
            }

            $order->save();
            Mage::dispatchEvent(
                'pagseguro_proccess_notification_after',
                array(
                    'order' => $order,
                    'payment'=> $payment,
                    'result_xml' => $resultXML,
                )
            );
        } else {
            Mage::throwException('Retorno inválido. Referência do pedido não encontrada.');
        }
    }

    /**
     * Grab statuses changes when receiving a new notification code
     *
     * @param string $notificationCode
     *
     * @return SimpleXMLElement
     */
    public function getNotificationStatus($notificationCode)
    {
        $helper =  Mage::helper('ricardomartins_pagseguro');
        $useApp = $helper->getLicenseType() == 'app';
        $url =  $helper->getWsV3Url('transactions/notifications/' . $notificationCode, $useApp);

        $params = array('token' => $helper->getToken(), 'email' => $helper->getMerchantEmail());
        if ($useApp) {
            $params = array_merge(
                $params,
                array('public_key' => $helper->getPagSeguroProKey(), 'isSandbox' => $helper->isSandbox() ? 1 : 0)
            );
            unset($params['email'], $params['token']);
        }

        $url .= $helper->addUrlParam($url, $params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        $return = '';

        try {
            $return = curl_exec($ch);
        } catch (Exception $e) {
            $helper->writeLog(
                sprintf(
                    'Falha ao capturar retorno para notificationCode %s: %s(%d)', $notificationCode, curl_error($ch),
                    curl_errno($ch)
                )
            );
        }

        $helper->writeLog(sprintf('Retorno do Pagseguro para notificationCode %s: %s', $notificationCode, $return));

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string(trim($return));
        if (false === $xml) {
            $helper->writeLog('Retorno de notificacao XML PagSeguro em formato não esperado. Retorno: ' . $return);
        }

        curl_close($ch);
        return $xml;
    }

    /**
     * Processes order status and return information about order status and state
     * Doesn' change anything to the order. Just returns an object showing what to do.
     *
     * @param $statusCode
     * @return Varien_Object
     * @throws Varien_Exception
     */
    public function processStatus($statusCode)
    {
        $return = new Varien_Object();
        $return->setStateChanged(true);
        $return->setIsTransactionPending(true); //payment is pending?

        switch($statusCode)
        {
            case '1':
                $return->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
                $return->setIsCustomerNotified($this->getCode()!='pagseguro_cc');
                if ($this->getCode()=='rm_pagseguro_cc') {
                    $return->setStateChanged(false);
                }

                $return->setMessage(
                    'Aguardando pagamento: o comprador iniciou a transação,
                mas até o momento o PagSeguro não recebeu nenhuma informação sobre o pagamento.'
                );
                break;
            case '2':
                $return->setState(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW);
                $return->setIsCustomerNotified(true);
                $return->setMessage(
                    'Em análise: o comprador optou por pagar com um cartão de crédito e
                    o PagSeguro está analisando o risco da transação.'
                );
                break;
            case '3':
                $return->setState(Mage_Sales_Model_Order::STATE_PROCESSING);
                $return->setIsCustomerNotified(true);
                $return->setMessage(
                    'Paga: a transação foi paga pelo comprador e o PagSeguro já recebeu uma confirmação
                    da instituição financeira responsável pelo processamento.'
                );
                $return->setIsTransactionPending(false);
                break;
            case '4':
                $return->setMessage(
                    'Disponível: a transação foi paga e chegou ao final de seu prazo de liberação sem
                    ter sido retornada e sem que haja nenhuma disputa aberta.'
                );
                $return->setIsCustomerNotified(false);
                $return->setStateChanged(false);
                $return->setIsTransactionPending(false);
                break;
            case '5':
                $return->setState(Mage_Sales_Model_Order::STATE_PROCESSING);
                $return->setIsCustomerNotified(false);
                $return->setIsTransactionPending(false);
                $return->setMessage(
                    'Em disputa: o comprador, dentro do prazo de liberação da transação,
                    abriu uma disputa.'
                );
                break;
            case '6':
                $return->setData('state', Mage_Sales_Model_Order::STATE_CLOSED);
                $return->setIsCustomerNotified(false);
                $return->setIsTransactionPending(false);
                $return->setMessage('Devolvida: o valor da transação foi devolvido para o comprador.');
                break;
            case '7':
                $return->setState(Mage_Sales_Model_Order::STATE_CANCELED);
                $return->setIsCustomerNotified(true);
                $return->setMessage('Cancelada: a transação foi cancelada sem ter sido finalizada.');
                if ($this->_order && Mage::helper('ricardomartins_pagseguro')->canRetryOrder($this->_order)) {
                    $return->setState(Mage_Sales_Model_Order::STATE_HOLDED);
                    $return->setIsCustomerNotified(false);
                    $return->setMessage(
                        'Retentativa: a transação ia ser cancelada (status 7), mas '
                        . 'a opção de retentativa estava ativada. O pedido será cancelado posteriormente '
                        . 'caso o cliente não use o link de retentativa no prazo estabelecido.'
                    );
                }
                break;
            case '8':
                $return->setState(Mage_Sales_Model_Order::STATE_CANCELED);
                $return->setIsCustomerNotified(false);
                $return->setMessage('Debitado: o valor da transação foi devolvida ao comprador após processo'
                    . ' de disputa.');
                break;
            case '9':
                $return->setState(Mage_Sales_Model_Order::STATE_HOLDED);
                $return->setIsCustomerNotified(false);
                $return->setMessage('Retenção Temporária: O comprador abriu uma solicitação de chargeback junto à'
                    . ' operadora do cartão de crédito.');
                break;
            default:
                $return->setIsCustomerNotified(false);
                $return->setStateChanged(false);
                $return->setMessage('Codigo de status inválido retornado pelo PagSeguro. (' . $statusCode . ')');
        }

        return $return;
    }

    /**
     * Call PagSeguro API
     * @param $params
     * @param $payment
     * @param $type
     *
     * @return SimpleXMLElement
     */
    public function callApi($params, $payment, $type='transactions')
    {
        $helper = Mage::helper('ricardomartins_pagseguro');
        $useApp = $helper->getLicenseType() == 'app';
        if ($useApp) {
            $params['public_key'] = Mage::getStoreConfig('payment/pagseguropro/key');
            if ($helper->isSandbox()) {
                $params['public_key'] = Mage::getStoreConfig('payment/rm_pagseguro/sandbox_appkey');
                $params['isSandbox'] = '1';
                unset($params['token'], $params['email']);
            }
        }

        $params = $this->_convertEncoding($params);
        $paramsObj = new Varien_Object(array('params'=>$params));

        //you can create a module to modify some parameter using the following observer
        Mage::dispatchEvent(
            'ricardomartins_pagseguro_params_callapi_before_send',
            array(
                'params' => $params,
                'payment' => $payment,
                'type' => $type
            )
        );
        $params = $paramsObj->getParams();
        $paramsString = $this->_convertToCURLString($params);

        $helper->writeLog('Parametros sendo enviados para API (/'.$type.'): '. var_export($params, true));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $helper->getWsUrl($type, $useApp));
        curl_setopt($ch, CURLOPT_POST, count($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $paramsString);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $helper->getCustomHeaders());
        $response = '';

        try{
            $response = curl_exec($ch);
        }catch(Exception $e){
            Mage::throwException('Falha na comunicação com Pagseguro (' . $e->getMessage() . ')');
        }

        if (curl_error($ch)) {
            Mage::throwException(
                sprintf('Falha ao tentar enviar parametros ao PagSeguro: %s (%s)', curl_error($ch), curl_errno($ch))
            );
        }
        curl_close($ch);

        $helper->writeLog('Retorno PagSeguro (/'.$type.'): ' . var_export($response, true));

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string(trim($response));

        if (false === $xml) {
            switch($response){
                case 'Unauthorized':
                    $helper->writeLog(
                        'Token/email não autorizado pelo PagSeguro. Verifique suas configurações no painel.'
                    );
                    break;
                case 'Forbidden':
                    $helper->writeLog(
                        'Acesso não autorizado à Api Pagseguro. Verifique se você tem permissão para
                         usar este serviço. Retorno: ' . var_export($response, true)
                    );
                    break;
                default:
                    $helper->writeLog('Retorno inesperado do PagSeguro. Retorno: ' . $response);
            }

            Mage::throwException(
                'Houve uma falha ao processar seu pedido/pagamento. Por favor entre em contato conosco.'
            );
        }

        return $xml;
    }

    /**
     * Call PagSeguro API (POST) with JSON Content
     *
     * @param        $body
     * @param        $headers
     * @param string $type
     *
     * @param bool   $noV2 removes /v2/ from api endpoint (used to other api versions)
     *
     * @return string
     * @throws Mage_Core_Exception
     */
    public function callJsonApi($body, $headers, $type='pre-approvals', $noV2=false) //phpcs:ignore
    {
        $helper = Mage::helper('ricardomartins_pagseguro');
        $isSandbox = $helper->isSandbox();
        $useApp = $helper->getLicenseType() == 'app';
        if (!$useApp) {
            Mage::throwException('Autorize sua loja no modelo de aplicação antes de usar este método.');
        }

        $key = $helper->getPagSeguroProKey();
        $paramsObj = new Varien_Object(array('body'=>$body));

        //you can create a module to modify some parameter using the following observer
        Mage::dispatchEvent(
            'ricardomartins_pagseguro_params_calljsonapi_before_send',
            array(
                'body' => $body,
                'type' => $type
            )
        );
        $params = $paramsObj->getBody();


        $sandbox = $isSandbox ? '(sandbox)' : '';
        $helper->writeLog(
            'Parametros sendo enviados para API Json ' . $sandbox . '(/' . $type . '): ' . var_export($params, true)
        );

        $headers = array_merge($helper->getCustomHeaders(), $headers);

        $urlws = $helper->getWsUrl($type . "?public_key={$key}", true);
        if ($noV2) { //phpcs:ignore
            $urlws = str_replace('/v2/', '/', $urlws);
        }

        $urlws .= $isSandbox ? '&isSandbox=1' : '';


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $urlws);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = '';

        try{
            $response = curl_exec($ch);
        }catch(Exception $e){
            Mage::throwException('Falha na comunicação com Pagseguro (' . $e->getMessage() . ')');
        }

        if (curl_error($ch)) {
            Mage::throwException(
                sprintf('Falha ao tentar enviar parametros ao PagSeguro: %s (%s)', curl_error($ch), curl_errno($ch))
            );
        }
        curl_close($ch);

        $helper->writeLog('Retorno PagSeguro (/'.$type.'): ' . var_export($response, true));


        if (is_string($response)) {
            switch($response){
                case 'Unauthorized':
                    $helper->writeLog(
                        'Token/email não autorizado pelo PagSeguro. Verifique suas configurações no painel.'
                    );
                    break;
                case 'Forbidden':
                    $helper->writeLog(
                        'Acesso não autorizado à Api Pagseguro. Verifique se você tem permissão para
                         usar este serviço. Retorno: ' . var_export($response, true)
                    );
                    break;
            }
        }

        if (is_string($response) && json_decode($response) === null) {
            $helper->writeLog('Retorno inesperado do PagSeguro. Retorno: ' . $response);
            Mage::throwException(
                'Houve uma falha ao processar seu pedido/pagamento. Por favor entre em contato conosco.'
            );
        }

        return json_decode($response);
    }

    /**
     * Check if order total is zero making method unavailable
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return mixed
     */
    public function isAvailable($quote = null)
    {
        return parent::isAvailable($quote) && !empty($quote)
            && (Mage::app()->getStore()->roundPrice($quote->getGrandTotal()) > 0 || $quote->isNominal());
    }


    /**
     * Order payment
     *
     * @param Varien_Object $payment
     * @param float $amount
     *
     * @return RicardoMartins_PagSeguro_Model_Payment_Abstract
     */
    public function refund(Varien_Object $payment, $amount)
    {
        //will grab data to be send via POST to API inside $params
        $rmHelper   = Mage::helper('ricardomartins_pagseguro');

        // recupera a informação adicional do PagSeguro
        $info           = $this->getInfoInstance();
        $transactionId = $info->getAdditionalInformation('transaction_id');

        $params = array(
            'transactionCode'   => $transactionId,
            'refundValue'       => number_format($amount, 2, '.', ''),
        );

        if ($rmHelper->getLicenseType() != 'app') {
            $params['token'] = $rmHelper->getToken();
            $params['email'] = $rmHelper->getMerchantEmail();
        }

        // call API - refund
        $returnXml  = $this->callApi($params, $payment, 'transactions/refunds');

        if ($returnXml === null) {
            $errorMsg = $this->_getHelper()->__('Erro ao solicitar o reembolso.\n');
            Mage::throwException($errorMsg);
        }
        return $this;
    }

    /**
     * Convert array values to utf-8
     * @param array $params
     *
     * @return array
     */
    protected function _convertEncoding(array $params)
    {
        foreach ($params as $k => $v) {
            $params[$k] = utf8_decode($v);
        }
        return $params;
    }

    /**
     * Convert API params (already ISO-8859-1) to url format (curl string)
     * @param array $params
     *
     * @return string
     */
    protected function _convertToCURLString(array $params)
    {
        $fieldsString = '';
        foreach ($params as $k => $v) {
            $fieldsString .= $k.'='.urlencode($v).'&';
        }
        return rtrim($fieldsString, '&');
    }

    /**
     * Retrieve model helper
     *
     * @return RicardoMartins_PagSeguro_Helper_Data
     */
    protected function _getHelper()
    {
        return Mage::helper('ricardomartins_pagseguro');
    }

    /**
     * Merge $data array to existing additionalInformation
     * @param [] $data
     *
     * @return Mage_Payment_Model_Info
     * @throws Mage_Core_Exception
     */
    protected function mergeAdditionalInfo($data)
    {
        $infoInstance = $this->getInfoInstance();
        $current = $infoInstance->getAdditionalInformation();
        return $infoInstance->setAdditionalInformation(array_merge($current, $data));
    }


    /**
     * @param       $suffix
     * @param array $headers
     * @param bool  $noV2
     *
     * @return mixed
     * @throws Mage_Core_Exception
     */
    public function callGetAPI($suffix, $headers = array(), $noV2 = false) //phpcs:ignore
    {
        $helper = Mage::helper('ricardomartins_pagseguro');
//        $key = $helper->getPagSeguroProKey();

        $urlws = $helper->getWsUrl($suffix, true);
//        $urlws .= $helper->addUrlParam($urlws, array('public_key'=>$key));
        if ($noV2) { //phpcs:ignore
            $urlws = str_replace('/v2/', '/', $urlws);
        }

        $helper->writeLog('Chamando API GET (/'. $suffix .')');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $urlws);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = '';

        try{
            $response = curl_exec($ch);
            $helper->writeLog('Retorno PagSeguro API GET: ' . $response);

            if (json_decode($response) !== null) {
                return json_decode($response);
            }

            Mage::throwException(
                'Falha ao decodificar retorno das informações. Formato retornado inesperado. JSON esperado.'
            );
        }catch(Exception $e){
            Mage::throwException('Falha na comunicação com Pagseguro (' . $e->getMessage() . ')');
        }

        if (curl_error($ch)) {
            Mage::throwException(
                sprintf(
                    'A operação cURL falhou: %s (%s)',
                    curl_error($ch),
                    curl_errno($ch)
                )
            );
        }
    }

    public function callPutAPI($suffix, $headers = array(), $body, $noV2 = false) //phpcs:ignore
    {
        $helper = Mage::helper('ricardomartins_pagseguro');
//        $key = $helper->getPagSeguroProKey();

        $urlws = $helper->getWsUrl($suffix, true);
//        $urlws .= $helper->addUrlParam($urlws, array('public_key'=>$key));
        if ($noV2) { //phpcs:ignore
            $urlws = str_replace('/v2/', '/', $urlws);
        }

        $helper->writeLog('Chamando API PUT (/'. $suffix .') com body: ' . $body);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $urlws);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
//        curl_setopt($ch, CURLOPT_HEADER, true);
//        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = '';

        try{
            $response = curl_exec($ch);
            $helper->writeLog('Retorno PagSeguro API PUT: (' . curl_getinfo($ch, CURLINFO_HTTP_CODE) . ')' . $response );

            if (json_decode($response) !== null) {
                $response = json_decode($response);
                $this->validateJsonResponse($response);
                return $response;
            }

            if ($response === '') {
                return true;
            }

            if (curl_error($ch)) {
                Mage::throwException(
                    sprintf(
                        'A operação cURL falhou: %s (%s)',
                        curl_error($ch),
                        curl_errno($ch)
                    )
                );
            }

            Mage::throwException(
                'Falha ao decodificar retorno das informações. '
                . 'Formato retornado inesperado. JSON ou vazio era esperado.'
                . 'HTTP Status: ' . curl_getinfo($ch, CURLINFO_HTTP_CODE) //phpcs:ignore
            );
        }catch(Exception $e){
            Mage::throwException('Falha na comunicação com Pagseguro (' . $e->getMessage() . ')');
        }
    }

    /**
     * @param stdObject $response
     *
     * @throws Mage_Core_Exception
     */
    protected function validateJsonResponse($response)
    {
        if (isset($response->error) && $response->error) {
            $err = array();
            $rmHelper = Mage::helper('ricardomartins_pagseguro');
            foreach ($response->errors as $code => $msg) {
                $err[] = $rmHelper->__((string)$msg) . ' (' . $code . ')';
            }

            Mage::throwException(
                'Um erro ocorreu junto ao PagSeguro.' . PHP_EOL . implode(
                    PHP_EOL, $err
                )
            );
        }
    }

}


