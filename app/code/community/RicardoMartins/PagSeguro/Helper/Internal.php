<?php
/**
 * PagSeguro Transparente Magento
 * Internal Helper Class - responsible for some internal requests
 *
 * @category    RicardoMartins
 * @package     RicardoMartins_PagSeguro
 * @author      Ricardo Martins
 * @copyright   Copyright (c) 2015 Ricardo Martins (http://r-martins.github.io/PagSeguro-Magento-Transparente/)
 * @license     https://opensource.org/licenses/MIT MIT License
 */
class RicardoMartins_PagSeguro_Helper_Internal extends Mage_Core_Helper_Abstract
{
    /**
     * Get fields from a given entity
     * @author Gabriela D'Ávila (http://davila.blog.br)
     * @param $type
     * @return mixed
     */
    public static function getFields($type = 'customer_address')
    {
        $entityType = Mage::getModel('eav/config')->getEntityType($type);
        $entityTypeId = $entityType->getEntityTypeId();
        $attributes = Mage::getResourceModel('eav/entity_attribute_collection')->setEntityTypeFilter($entityTypeId);

        return $attributes->getData();
    }

    /**
     * Returns associative array with required parameters to API, used on CC method calls
     * @param Mage_Sales_Model_Order $order
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return array
     */
    public function getCreditCardApiCallParams(Mage_Sales_Model_Order $order, $payment)
    {
        /** @var RicardoMartins_PagSeguro_Helper_Data $helper */
        $helper = Mage::helper('ricardomartins_pagseguro');

        $noSID = $helper->isNoSidUrlEnabled();

        /** @var RicardoMartins_PagSeguro_Helper_Params $pHelper */
        $pHelper = Mage::helper('ricardomartins_pagseguro/params'); //params helper - helper auxiliar de parametrização

        $creditCardToken = $helper->isMulticcEnabled() 
                                ? $payment->getAdditionalInformation("credit_card_token")
                                : $pHelper->getPaymentHash('credit_card_token');
        $orderReference = $order->getIncrementId();
        $extraAmount = $pHelper->getExtraAmount($order);

        // update reference and values, if its multi card transaction
        if ($ccIdx = $payment->getData("_current_card_index")) {
            $cardData = $payment->getAdditionalInformation("cc" . $ccIdx);
            $creditCardToken = $cardData["token"];
            $orderReference = $order->getIncrementId() . "-cc" . $ccIdx;
            $extraAmount = ($extraAmount = $pHelper->getExtraAmount($order))
                                ? ($payment->getData("_current_card_total_multiplier") * $extraAmount)
                                : 0.00;
            $extraAmount += $pHelper->getMultiCcRoundedAmountError($order);
            $extraAmount = number_format($extraAmount, 2, ".", "");
        }

        $params = array(
        'email'                 => $helper->getMerchantEmail(),
            'token'             => $helper->getToken(),
            'paymentMode'       => 'default',
            'paymentMethod'     =>  'creditCard',
            'receiverEmail'     =>  $helper->getMerchantEmail(),
            'currency'          => 'BRL',
            'creditCardToken'   => $creditCardToken,
            'reference'         => $orderReference,
            'extraAmount'       => $extraAmount,
            'notificationURL'   => Mage::getUrl(
                'ricardomartins_pagseguro/notification',
                array('_store' => $order->getStoreId(), '_secure' => true, '_nosid' => $noSID)
            ),
        );
        $params = array_merge($params, $pHelper->getItemsParams($order));
        $params = array_merge($params, $pHelper->getSenderParams($order, $payment));
        $params = array_merge($params, $pHelper->getAddressParams($order, 'shipping'));
        $params = array_merge($params, $pHelper->getAddressParams($order, 'billing'));
        $params = array_merge($params, $pHelper->getCreditCardHolderParams($order, $payment));
        $params = array_merge($params, $pHelper->getCreditCardInstallmentsParams($order, $payment));

        return $params;
    }
}
