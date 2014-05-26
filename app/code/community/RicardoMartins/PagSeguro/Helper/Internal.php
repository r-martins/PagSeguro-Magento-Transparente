<?php
/**
 * Class RicardoMartins_PagSeguro_Helper_Internal
 * Trata chamadas internas da API
 *
 * @author    Ricardo Martins <ricardo@ricardomartins.net.br>
 */
class RicardoMartins_PagSeguro_Helper_Internal extends Mage_Core_Helper_Abstract
{
    /*
     * Retorna campos de uma dada entidade
     * @author Gabriela D'Ávila (http://davila.blog.br)
     */
    public static function getFields($type = 'customer_address') {
        $entityType = Mage::getModel('eav/config')->getEntityType($type);
        $entityTypeId = $entityType->getEntityTypeId();
        $attributes = Mage::getResourceModel('eav/entity_attribute_collection')->setEntityTypeFilter($entityTypeId);

        return $attributes->getData();
    }

    /*
     * Retorna array associativo com parametros necessarios pra uma chamada de API para pagamento com Cartao
     * @return array
     */
    public function getCreditCardApiCallParams(Mage_Sales_Model_Order $order, $payment)
    {
        $helper = Mage::helper('ricardomartins_pagseguro');
        $phelper = Mage::helper('ricardomartins_pagseguro/params'); //params helper - helper auxiliar de parametrização
        $params = array(
            'email' => $helper->getMerchantEmail(),
            'token' => $helper->getToken(),
            'paymentMode'   => 'default',
            'paymentMethod' =>  'creditCard',
            'receiverEmail' =>  $helper->getMerchantEmail(),
            'currency'  => 'BRL',
            'creditCardToken'   => $payment['additional_information']['credit_card_token'],
            'reference'     => $order->getIncrementId(),
            'extraAmount'=> $phelper->getExtraAmount($order),
            'notificationURL' => Mage::getUrl('ricardomartins_pagseguro/notification'),
        );
        $items = $phelper->getItemsParams($order);
        $params = array_merge($params, $phelper->getItemsParams($order));
        $params = array_merge($params, $phelper->getSenderParams($order,$payment));
        $params = array_merge($params, $phelper->getAddressParams($order,'shipping'));
        $params = array_merge($params, $phelper->getAddressParams($order,'billing'));
        $params = array_merge($params, $phelper->getCreditCardHolderParams($order,$payment));
        $params = array_merge($params, $phelper->getCreditCardInstallmentsParams($order,$payment));

//    Mage::log(var_export($params, true), null, 'pagseguro.log', true);

        return $params;
    }

}