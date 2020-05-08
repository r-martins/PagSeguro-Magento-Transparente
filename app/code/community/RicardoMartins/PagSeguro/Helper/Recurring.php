<?php
class RicardoMartins_PagSeguro_Helper_Recurring extends Mage_Core_Helper_Abstract
{
    /**
     * Generates a unique product hash based on product information to be used on plan creation as part of product's
     * reference
     *
     * @param Mage_Catalog_Model_Product $product
     *
     * @return bool|string
     */
    public function getProductReferenceHash($product)
    {
        if (!$product->isRecurring() || !is_array($product->getRecurringProfile())) {
            return false;
        }


        $profile = $product->getRecurringProfile();

        $sku = $product->getSku();
        $periodUnit = $profile['period_unit'];
        $periodFrequency = $profile['period_frequency'];
        $trialPeriodUnit = $profile['trial_period_unit'];
        $trialPeriodFrequency = $profile['trial_period_frequency'];
        $initAmount = $profile['init_amount'];

        return crypt(
            $sku . $periodUnit . $periodFrequency .$trialPeriodUnit . $trialPeriodFrequency . $initAmount
        );
    }

    /**
     * Create a product reference to be used in PagSeguro
     * @param Mage_Catalog_Model_Product $product
     *
     * @return bool|string
     */
    public function getProductReference($product)
    {
        if (!$product->isRecurring) {
            return false;
        }

        return $product->getSku() . '-' . $this->getProductReferenceHash();
    }
}