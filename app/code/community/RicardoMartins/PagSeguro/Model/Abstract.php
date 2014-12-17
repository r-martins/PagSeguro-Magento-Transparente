<?php
class RicardoMartins_PagSeguro_Model_Abstract extends Mage_Payment_Model_Method_Abstract
{
    /**
     * Processa o XML de retorno da notificação. O XML é enviado no ato do pedido, e depois nas consultas de atualizacao de pedido.
     * @see https://pagseguro.uol.com.br/v2/guia-de-integracao/api-de-notificacoes.html#v2-item-servico-de-notificacoes
     * @param SimpleXMLElement $resultXML
     */
    public function proccessNotificatonResult(SimpleXMLElement $resultXML)
    {
        if(isset($resultXML->error)){
            $errMsg = Mage::helper('ricardomartins_pagseguro')->__((string)$resultXML->error->message);
            Mage::throwException($this->_getHelper()->__('Problemas ao processar seu pagamento. %s(%s)', $errMsg, (string)$resultXML->error->code));
        }
        if(isset($resultXML->reference))
        {
            /** @var Mage_Sales_Model_Order $order */
            $order = Mage::getModel('sales/order')->loadByIncrementId((string)$resultXML->reference);
            $payment = $order->getPayment();
            $processedState = $this->processStatus((int)$resultXML->status);
            $message = $processedState->getMessage();

            if((int)$resultXML->status == 6) //valor devolvido (gera credit memo e tenta cancelar o pedido)
            {
                if ($order->canUnhold()) {
                    $order->unhold();
                }
                if($order->canCancel())
                {
                    $order->cancel();
                    $order->save();
                }else{
                    $payment->registerRefundNotification(floatval($resultXML->grossAmount));
                    $order->addStatusHistoryComment('Devolvido: o valor foi devolvido ao comprador, mas o pedido encontra-se em um estado que não pode ser cancelado.')
                        ->save();
                }
            }

            if((int)$resultXML->status == 7 && isset($resultXML->cancellationSource)) //Especificamos a fonte do cancelamento do pedido
            {
                switch((string)$resultXML->cancellationSource)
                {
                    case 'INTERNAL':
                        $message .= ' O próprio PagSeguro negou ou cancelou a transação.';
                        break;
                    case 'EXTERNAL':
                        $message .= ' A transação foi negada ou cancelada pela instituição bancária.';
                        break;
                }
                $order->cancel();
            }

            if($processedState->getStateChanged())
            {
                $order->setState($processedState->getState(),true,$processedState->getIsCustomerNotified())->save();
            }

            if((int)$resultXML->status == 3) //Quando o pedido foi dado como Pago
            {
                //cria fatura e envia email (se configurado)
//                $payment->registerCaptureNotification(floatval($resultXML->grossAmount));
                $invoice = $order->prepareInvoice();
                $invoice->register()->pay();
                $msg = sprintf('Pagamento capturado. Identificador da Transação: %s', (string)$resultXML->code);
                $invoice->addComment($msg);
                $invoice->sendEmail(Mage::getStoreConfigFlag('payment/pagseguro/send_invoice_email'),'Pagamento recebido com sucesso.');
                Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder())
                    ->save();
                $order->addStatusHistoryComment(sprintf('Fatura #%s criada com sucesso.', $invoice->getIncrementId()));
            }

            $order->addStatusHistoryComment($message);
            $payment->save();
            $order->save();
            Mage::dispatchEvent('pagseguro_proccess_notification_after',array(
                    'order' => $order,
                    'payment'=> $payment,
                    'result_xml' => $resultXML,
                ));
        }else{
            Mage::throwException('Retorno inválido. Referência do pedido não encontrada.');
        }
    }

    /**
     * Pega um codigo de notificacao (enviado pelo pagseguro quando algo acontece com o pedido) e consulta o que mudou no status
     * @param $notificationCode
     *
     * @return SimpleXMLElement
     */
    public function getNotificationStatus($notificationCode)
    {
        $helper =  Mage::helper('ricardomartins_pagseguro');
        $url =  $helper->getWsUrl('transactions/notifications/' . $notificationCode);
        $client = new Zend_Http_Client($url);
        $client->setParameterGet(array(
                'token'=>$helper->getToken(),
                'email'=> $helper->getMerchantEmail(),
            ));

        $client->request();
        $resposta = $client->getLastResponse()->getBody();
        
        $helper->writeLog(sprintf('Retorno do Pagseguro para notificationCode %s: %s', $notificationCode, $resposta));

        return simplexml_load_string(trim($resposta));
    }

    /**
     * Processa o status do pedido devolvendo informacoes como status e state do pedido
     * @param $statusCode
     * @return Varien_Object
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
                $return->setIsCustomerNotified(true);
                $return->setMessage('Aguardando pagamento: o comprador iniciou a transação, mas até o momento o PagSeguro não recebeu nenhuma informação sobre o pagamento.');
                break;
            case '2':
                $return->setState(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW);
                $return->setIsCustomerNotified(true);
                $return->setMessage('Em análise: o comprador optou por pagar com um cartão de crédito e o PagSeguro está analisando o risco da transação.');
                break;
            case '3':
                $return->setState(Mage_Sales_Model_Order::STATE_PROCESSING);
                $return->setIsCustomerNotified(true);
                $return->setMessage('Paga: a transação foi paga pelo comprador e o PagSeguro já recebeu uma confirmação da instituição financeira responsável pelo processamento.');
                $return->setIsTransactionPending(false);
                break;
            case '4':
                $return->setMessage('Disponível: a transação foi paga e chegou ao final de seu prazo de liberação sem ter sido retornada e sem que haja nenhuma disputa aberta.');
                $return->setIsCustomerNotified(false);
                $return->setStateChanged(false);
                $return->setIsTransactionPending(false);
                break;
            case '5':
                $return->setState(Mage_Sales_Model_Order::STATE_PROCESSING);
                $return->setIsCustomerNotified(false);
                $return->setIsTransactionPending(false);
                $return->setMessage('Em disputa: o comprador, dentro do prazo de liberação da transação, abriu uma disputa.');
                break;
            case '6':
                $return->setState(Mage_Sales_Model_Order::STATE_CLOSED);
                $return->setIsCustomerNotified(false);
                $return->setIsTransactionPending(false);
                $return->setMessage('Devolvida: o valor da transação foi devolvido para o comprador.');
                break;
            case '7':
                $return->setState(Mage_Sales_Model_Order::STATE_CANCELED);
                $return->setIsCustomerNotified(true);
                $return->setMessage('Cancelada: a transação foi cancelada sem ter sido finalizada.');
                break;
            default:
                $return->setIsCustomerNotified(false);
                $return->setStateChanged(false);
                $return->setMessage('Codigo de status inválido retornado pelo PagSeguro. (' . $statusCode . ')');
        }
        return $return;
    }

    /**
     * Chama API pra realizar um pagamento
     * @param $params
     * @param $payment
     *
     * @return SimpleXMLElement
     */
    public function callApi($params, $payment)
    {
        $helper = Mage::helper('ricardomartins_pagseguro');
        $params = $this->_convertEnconding($params);
        $params_string = $this->_convertToCURLString($params);
        
        $helper->writeLog('Parametros sendo enviados para API (/transactions): '. var_export($params,true));
        
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, $helper->getWsUrl('transactions'));
        curl_setopt($ch,CURLOPT_POST, count($params));
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch,CURLOPT_POSTFIELDS, $params_string);
        
        try{
            $response = curl_exec($ch);
            curl_close($ch);
        }catch(Exception $e){
            Mage::throwException('Falha na comunicação com Pagseguro (' . $e->getMessage() . ')');
        }

        $helper->writeLog('Retorno PagSeguro (/transactions): ' . var_export($response,true));

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string(trim($response));
        if(false === $xml){
            switch($response){
                case 'Unauthorized':
                    $helper->writeLog('Token/email não autorizado pelo PagSeguro. Verifique suas configurações no painel.');
                    break;
                case 'Forbidden':
                    $helper->writeLog('Acesso não autorizado à Api Pagseguro. Verifique se você tem permissão para usar este serviço. Retorno: ' . var_export($response,true));
                    break;
                default:
                    $helper->writeLog('Retorno inesperado do PagSeguro. Retorno: ' . $response);
            }
            Mage::throwException('Houve uma falha ao processar seu pedido/pagamento. Por favor entre em contato conosco.');
        }
        
        return $xml;
    }

    /**
     * Converte os valores enviados à api para ISO-8859-1
     * @param array $params
     *
     * @return array
     */
    protected function _convertEnconding(array $params)
    {
        foreach($params as $k => $v)
        {
            $params[$k] = utf8_decode($v);
        }
        return $params;
    }
    
    /**
     * Converte para um única string os valores a serem enviados à api (já convertidos para ISO-8859-1)
     * @param array $params
     *
     * @return string
     */
    protected function _convertToCURLString(array $params)
    {
        $fields_string = '';
        foreach($params as $k => $v)
        {
            $fields_string .= $k.'='.urlencode($v).'&';
        }
        
        return rtrim($fields_string, '&');
    }
}