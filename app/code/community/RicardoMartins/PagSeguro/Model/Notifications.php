<?php
 
class RicardoMartins_PagSeguro_Model_Notifications extends Mage_Core_Model_Abstract
{

    protected function _construct()
    {
        $this->_init('ricardomartins_pagseguro/notifications');
    }

    /**
     * Adds notificationCode to the table to be reprocessed later
     * @param      $notificationCode
     * @param null $incrementId
     *
     * @return false|RicardoMartins_PagSeguro_Model_Notifications
     * @throws Exception
     */
    public function addToQueue($notificationCode, $incrementId = null)
    {
        $exists = $this->getCollection()->addFieldToFilter('notification_code', $notificationCode)->count() > 0;
        if ($exists || (null === $notificationCode && null === $incrementId)) {
            return false;
        }

        $this->setData('notification_code', $notificationCode)
            ->setData('order_increment_id', $incrementId);
        return $this->save();
    }

    /**
     * Retry to process failed notifications to update orders
     */
    public function retryNotifications()
    {
        $items = $this->getCollection()->addFieldToFilter('processed_at', array('null' => true));

        $model =  Mage::getModel('ricardomartins_pagseguro/abstract');
        $helper = Mage::helper('ricardomartins_pagseguro');
        foreach ($items as $notification) {
            $orderIncrementId = $notification->getOrderIncrementId();
            if (!$orderIncrementId &&
                false === $orderIncrementId = $this->getOrderIncrement($notification->getNotificationCode())) {
                $notification->delete();
                continue;
            }

            $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
            if (!$order->getId()) {
                $helper->writeLog(sprintf('Retentativa de notificação para o pedido %s falhou. Pedido não encontrado.',
                    $orderIncrementId));
                $notification->delete();
                continue;
            }

            $resultXml = $helper->getOrderStatus($order);
            if (false == $resultXml) {
                continue;
            }

            $processed = $model->proccessNotificatonResult($resultXml);

            if (false !== $processed) {
                $notification->setProcessedAt(Mage::getModel('core/date')->gmtDate('Y-m-d H:i:s'))
                    ->save();
                $helper->writeLog('Atualização de pedido processada via retentativa para o pedido ' . $orderIncrementId);
            }
        }
    }

    /**
     * Returns the order reference (increment id) from a notificationCode in PagSeguro
     * @param string $notificationCode
     */
    protected function getOrderIncrement($notificationCode)
    {
        $model =  Mage::getModel('ricardomartins_pagseguro/abstract');
        $xml = $model->getNotificationStatus($notificationCode);
        if (false == $xml || !isset($xml->reference)) {
            return false;
        }

        return (string)$xml->reference;
    }

}