<?php

class RicardoMartins_PagSeguro_Model_Cron
{
    public function updateRecurringPayments()
    {
        $statesToUpdate = array(Mage_Sales_Model_Recurring_Profile::STATE_PENDING,
                                Mage_Sales_Model_Recurring_Profile::STATE_UNKNOWN,
                                Mage_Sales_Model_Recurring_Profile::STATE_ACTIVE,
                                Mage_Sales_Model_Recurring_Profile::STATE_SUSPENDED);
        $subscriptionsToUpdate = Mage::getModel('sales/recurring_profile')->getCollection()
            ->addFieldToFilter('state', array('in' => $statesToUpdate))
            ->addExpressionFieldToSelect('now', 'NOW()', array())
            ->addFieldToFilter('reference_id', array('neq' => ''))
            ->addFieldToFilter('method_code', 'rm_pagseguro_recurring');
        $subscriptionsToUpdate->getSelect()->where("updated_at <= DATE_SUB(NOW(), INTERVAL 6 HOUR)");

        if (!$subscriptionsToUpdate->getAllIds()) {
            return;
        }
        $recurringModel = Mage::getModel('ricardomartins_pagseguro/recurring');
        foreach ($subscriptionsToUpdate as $subscription) {
            $a = 1;
            $subscription->setUpdatedAt($subscription->getNow())->save();

            try{
                $currentStatus = $recurringModel->getPreApprovalDetails($subscription->getReferenceId());
            } catch (Exception $e) {
                continue;
            }
        }

        $a = 1;
    }
}