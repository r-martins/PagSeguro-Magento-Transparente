<?php

class RicardoMartins_PagSeguro_Model_Cron
{
    public function updateRecurringPayments()
    {
        $statesToIgnore = array(Mage_Sales_Model_Recurring_Profile::STATE_EXPIRED,
                                Mage_Sales_Model_Recurring_Profile::STATE_CANCELED);
        $subscriptionsToUpdate = Mage::getModel('sales/recurring_profile')->getCollection()
            ->addFieldToFilter('state', array('nin' => $statesToIgnore))
            ->addExpressionFieldToSelect('now', 'CURRENT_TIMESTAMP()', array())
            ->addFieldToFilter('reference_id', array('neq' => ''))
            ->addFieldToFilter('method_code', 'rm_pagseguro_recurring');

        $fields = array('updated_at', 'created_at');
        $filters = array(array('to'=>date("Y-m-d H:i:s", time()-3600*6)), array('from'=>date("Y-m-d H:i:s", time()-60*5)));
        $filter1 = array('updated_at', array('to'=>date("Y-m-d H:i:s", time()-3600*6)));
        $filter2 = array('created_at', array('from'=>date("Y-m-d H:i:s", time()-60*5)));
        $subscriptionsToUpdate->addFieldToFilter($fields, $filters);

        if (!$subscriptionsToUpdate->getAllIds()) {
            return;
        }

        $recurringModel = Mage::getModel('ricardomartins_pagseguro/recurring');
        foreach ($subscriptionsToUpdate as $subscription) {
            $subscription->setUpdatedAt(date('Y-m-d H:i:s'))->save();

            try{
                $recurringModel->updateProfile($subscription);
                $recurringModel->createOrders($subscription);
            } catch (Exception $e) {
                Mage::helper('ricardomartins_pagseguro/recurring')->writeLog(
                    'Falha ao atualizar assinatura ' . $subscription->getId() . ': ' . $e->getMessage()
                );
                continue;
            }
        }
    }
}