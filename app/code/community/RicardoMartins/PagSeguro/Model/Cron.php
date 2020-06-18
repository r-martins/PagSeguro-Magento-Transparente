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
//            ->addFieldToFilter('profile_id', 57) //@TODO REMOVE FIXED FILTER
            ->addFieldToFilter('method_code', 'rm_pagseguro_recurring');
        $subscriptionsToUpdate->addFieldToFilter('updated_at', array('to'=>date("Y-m-d H:i:s", time()-3600*6)));
//        $subscriptionsToUpdate->getSelect()->where("updated_at >= DATE_SUB(NOW(), INTERVAL 6 HOUR)");

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