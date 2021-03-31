<?php
/**
 * Class Updater - Performs automatic updates on pending orders
 *
 * @author    Ricardo Martins <ricardo@magenteiro.com> and Fillipe Dutra
 * @copyright 2021 Magenteiro
 */
class RicardoMartins_PagSeguro_Model_Updater extends RicardoMartins_PagSeguro_Model_Abstract
{
    /**
     * @var Mage_Core_Helper_Abstract|RicardoMartins_PagSeguro_Helper_Data|null
     */
    protected $helper;

    protected $statusToUpdate = array(
        Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
        Mage_Sales_Model_Order::STATE_HOLDED,
        Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW,
        Mage_Sales_Model_Order::STATE_NEW,
        'pending'
    );

    //interval between updates (hours)
    protected $interval = 6;

    public function __construct()
    {
        $this->helper = Mage::helper('ricardomartins_pagseguro');
    }

    public function updateOrders()
    {
        if (!$this->isUpdaterEnabled()) {
            return;
        }

        $payments = $this->listPendingPayments();

        //avoids triggering order retry
        Mage::register('is_pagseguro_updater_session', true);

        /** @var Mage_Sales_Model_Order_Payment $payment */
        foreach ($payments as $payment) {
            $currentNextUpdate = $payment->getAdditionalInformation('next_update');
            $now = Mage::getModel('core/date')->gmtDate('Y-m-d H:i:s');

            $nextUpdate = new Zend_Date(Mage::getModel('core/date')->gmtTimestamp());
            $nextUpdate = $nextUpdate->addHour($this->interval)->toString('Y-MM-dd HH:mm:ss');
            $transactionCode = $payment->getAdditionalInformation('transaction_id');
            $order = Mage::getModel('sales/order')->load($payment->getParentId());
            $currentState = $order->getState();

            if (($currentNextUpdate && strtotime($currentNextUpdate) > strtotime($now)) || !$transactionCode) {
                continue;
            }
            
            $payment->setAdditionalInformation('next_update', $nextUpdate)->save();
            $isSandbox = strpos($order->getCustomerEmail(), '@sandbox.pagseguro') !== false;

            $paymentMethod = $payment->getMethodInstance();
            $transactionIds = array($transactionCode);

            if ($paymentMethod->getCode() == "rm_pagseguro_cc"
                && $paymentMethod->isMultiCardPayment($payment)) {
                $transactionIds = array();
                $payment->setOrder($order);

                foreach ($paymentMethod->getOrderTransactions($payment) as $transaction) {
                    $transactionIds[] = $transaction->getTxnId();
                }
            }

            foreach ($transactionIds as $transactionId) {
                $response = $this->helper->getOrderStatusXML($transactionId, $isSandbox);

                $this->helper->writeLog(
                    sprintf(
                        "Retorno do Pagseguro para a consulta da transacao %s via updater: %s", $transactionId,
                        Mage::helper('core/string')->truncate(
                            @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $response), 400, '...(continua)'
                        )
                    )
                );

                libxml_use_internal_errors(true);
                $responseXml = simplexml_load_string($response);
                if (!isset($responseXml->status)) {
                    continue;
                }

                try {
                    //if nothing has changed... continue
                    if ($paymentMethod->getTransactionStatus($transactionId) == (string)$responseXml->status) {
                        continue;
                    }

                    $processedState = $payment->getMethodInstance()
                                              ->processStatus((int)$responseXml->status);

                    $this->helper->writeLog(
                        sprintf(
                            'Updater: Processando atualização da transacao %s - pedido %s (%s).', 
                            $transactionId,
                            $order->getIncrementId(),
                            $processedState->getState()
                        )
                    );

        //            \RicardoMartins_PagSeguro_Model_Abstract::proccessNotificatonResult
                    $paymentMethod->proccessNotificatonResult($responseXml);

                    //see \RicardoMartins_PagSeguro_Model_Abstract::proccessNotificatonResult
                    Mage::unregister('sales_order_invoice_save_after_event_triggered');
                } catch (Exception $e) {
                    $this->helper->writeLog('Nao foi possivel concluir a atualizacao. Erro: ' . $e->getMessage());
                }
            }
        }

    }

    /**
     * List pagseguro orders that have an status that may suffer update in a near future
     * @return Mage_Eav_Model_Entity_Collection_Abstract
     */
    public function listPendingPayments()
    {
        return Mage::getModel('sales/order_payment')->getCollection()
            ->join('order', 'main_table.parent_id = order.entity_id', 'state')
            ->addFieldToFilter('method', array(array('like'=>'rm_pagseguro%'), array('like'=>'pagseguropro%')))
            ->addFieldToFilter('status', array('in' => $this->statusToUpdate))
            ->addFieldToFilter('additional_information', array('like' => '%transaction_id%'));
    }

    /**
     * Checks if the updater option is enabled in the module configuration
     * @return bool
     */
    public function isUpdaterEnabled()
    {
        return Mage::getStoreConfigFlag('payment/rm_pagseguro/updater_enabled');
    }
}