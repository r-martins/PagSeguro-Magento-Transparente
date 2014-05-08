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
            Mage::throwException($this->_getHelper()->__('Problemas ao processar seu pagamento. %s(%s)', (string)$resultXML->error->message, (string)$resultXML->error->code));
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
                if($order->canCancel())
                {
                    $payment->registerRefundNotification(floatval($resultXML->grossAmount));
                    $order->cancel();
                    $order->save();
                }else{
                    $order->addStatusHistoryComment('Devolvido: o valor foi devolvido ao comprador, mas o pedido encontra-se em um estado que não pode ser cancelado.')->save();
                }
            }

            if($processedState->getStateChanged())
            {
                $order->setState($processedState->getState(),false,$processedState->getIsCustomerNotified())->save();
            }

            if((int)$resultXML->status == 3) //Quando o pedido foi dado como Pago
            {
                $payment->registerCaptureNotification(floatval($resultXML->grossAmount));
            }

            if((int)$resultXML->status == 7 && isset($resultXML->cancellationSource)) //Especificamos a fonte do cancelamento do pedido
            {
                switch((string)$resultXML->cancellationSource)
                {
                    case 'INTERNAL':
                        $mssage .= ' O próprio PagSeguro negou ou cancelou a transação.';
                        break;
                    case 'EXTERNAL':
                        $mssage .= ' A transação foi negada ou cancelada pela instituição bancária.';
                        break;
                }
            }

            $order->addStatusHistoryComment($processedState->getMessage());
            $payment->save();
            $order->save();
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
        $helper->writeLog(sprintf('Retorno do Pagseguro para notificationCode %s: %s', $notificationCode, $client->getLastResponse()->getBody()));
        return simplexml_load_string($client->getLastResponse()->getBody());
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
}