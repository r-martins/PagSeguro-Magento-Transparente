<?php
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
        $params = array(
            'email' => Mage::getStoreConfig('payment/pagseguro/merchant_email'),
            'token' => Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/pagseguro/token')),
            'paymentMode'   => 'default',
            'paymentMethod' =>  'creditCard',
            'receiverEmail' =>  Mage::getStoreConfig('payment/pagseguro/merchant_email'),
            'currency'  => 'BRL',
            'senderName'    =>  sprintf('%s %s',$order->getCustomerFirstname(), $order->getCustomerLastname()),
            'senderEmail'   => $order->getCustomerEmail(),
            'senderHash'    => $payment['additional_information']['sender_hash'],
            'creditCardToken'   => $payment['additional_information']['credit_card_token'],
            'reference'     => $order->getIncrementId(),

            //parametros falsos abaixo

        );

        return $params;
    }

}