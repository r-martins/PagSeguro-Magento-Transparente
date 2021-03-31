<?php

class RicardoMartins_PagSeguro_Adminhtml_UpdatePaymentController extends Mage_Adminhtml_Controller_Action
{
    /**
     * @var Mage_Core_Helper_Abstract|RicardoMartins_PagSeguro_Helper_Data|null
     */
    protected $helper;

    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/pagseguro_update');
    }

    public function __construct(
        Zend_Controller_Request_Abstract $request, Zend_Controller_Response_Abstract $response,
        array $invokeArgs = array()
    ) {
        $this->helper = Mage::helper('ricardomartins_pagseguro');
        parent::__construct($request, $response, $invokeArgs);
    }

    /** @OBSOLETE (?) */
    public function indexAction()
    {
        $paymentId = (int)$this->getRequest()->getParam('id');
        $payment = Mage::getModel('sales/order_payment')->load($paymentId);
        $transactionCode = $payment->getAdditionalInformation('transaction_id');
        $order = Mage::getModel('sales/order')->load($payment->getParentId());
        $currentState = $order->getState();

        $isSandbox = strpos($order->getCustomerEmail(), '@sandbox.pagseguro') !== false;
        $updatedXml = $this->helper->getOrderStatusXML($transactionCode, $isSandbox);
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($updatedXml);
        if (!isset($xml->status)) {
            Mage::getSingleton('adminhtml/session')->addError('Retorno inesperado do PagSeguro. Tente novamente mais tarde ou veja os logs para mais detalhes.');
            $this->helper->writeLog('Retorno inesperado para atualização manual de pedido. Retorno: ' . var_export($updatedXml, true));
            return $this->_redirectReferer();
        }

        $methodInstance = $payment->getMethodInstance();
        $processedState = $methodInstance
            ->processStatus((int)$xml->status);

        //if nothing has changed... continue
        if ($processedState->getState() == $currentState) {
            Mage::getSingleton('adminhtml/session')->addSuccess('A situação do pedido permanece a mesma. Nenhuma alteração foi realizada.');
            return $this->_redirectReferer();
        }


        Mage::unregister('sales_order_invoice_save_after_event_triggered');
        Mage::register('is_pagseguro_updater_session', true);

        $methodInstance->proccessNotificatonResult($xml);
        Mage::unregister('is_pagseguro_updater_session');

        Mage::getSingleton('adminhtml/session')->addSuccess('Pedido atualizado com sucesso. Veja o último comentário para mais detalhes.');
        return $this->_redirectReferer();
    }

    /** @noinspection PhpUnused */
    /**
     * Used when clicking on Force Update on some credit card or transaction in the admin
     * @return RicardoMartins_PagSeguro_Adminhtml_UpdatePaymentController
     */
    public function transactionAction()
    {
        try {
            $transactionId = $this->getRequest()->getParam('transaction_id');
            $orderId = $this->getRequest()->getParam('order_id');
            
            // verifies if its a credit card payment
            $order = Mage::getModel('sales/order')->load($orderId);

            if ($order->getPayment()->getMethod() != "rm_pagseguro_cc") {
                Mage::throwException('Somente transações via cartão de crédito devem utilizar este método.');
            }

            // consults PagSeguro web services
            $helper = Mage::helper("ricardomartins_pagseguro");
            $response = $helper->getOrderStatusXML($transactionId, $helper->isSandbox());

            $helper->writeLog(
                sprintf(
                    "Retorno do Pagseguro para a consulta da transacao %s via controlador da administracao: %s",
                    $transactionId, Mage::helper('core/string')->truncate(
                    @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $response), 400, '...(continua)'
                )
                )
            );

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($response);
            if (!isset($xml->status)) {
                Mage::getSingleton('adminhtml/session')->addError(
                    'Retorno inesperado do PagSeguro. Tente novamente mais tarde ou veja os logs para mais detalhes.'
                );
                $this->helper->writeLog(
                    'Retorno inesperado para atualização manual de pedido. Retorno: ' . var_export($response, true)
                );
                return $this->_redirectReferer();
            }

            $notification = Mage::getModel("ricardomartins_pagseguro/payment_notification", array("document" => $xml));

            if (!$notification->getStatus()) {
                $helper->writeLog(
                    "Retorno inesperado para atualização manual de pedido. Retorno: " . var_export(
                        $notification->getDocument(), true
                    )
                );
                Mage::throwException(
                    "Retorno inesperado do PagSeguro. Tente novamente mais tarde ou veja os logs para mais detalhes."
                );
            }

            $paymentMethodInstance = $order->getPayment()->getMethodInstance();

            // verifies if status changed
            if ($notification->getStatus() == $paymentMethodInstance->getTransactionStatus($transactionId)) {
                Mage::throwException("A situação do pedido permanece a mesma. Nenhuma alteração foi realizada.");
            }

            // proccess returned data
            Mage::unregister('sales_order_invoice_save_after_event_triggered');
            Mage::register('is_pagseguro_updater_session', true);
            $paymentMethodInstance->proccessNotificatonResult($notification->getDocument());
            Mage::unregister('is_pagseguro_updater_session');

            Mage::getSingleton('adminhtml/session')->addSuccess(
                'Pedido atualizado com sucesso. Veja o último comentário para mais detalhes.'
            );
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }

        return $this->_redirectReferer();
    }
}