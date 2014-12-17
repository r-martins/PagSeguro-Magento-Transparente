<?php

/**
 * Payment Method Codes
 *
 * @author    Ricardo Martins <ricardo@ricardomartins.net.br>
 */
class RicardoMartins_PagSeguro_Model_Source_Customer_Cpf
{
    public function toOptionArray() {
        $fields = Mage::helper('ricardomartins_pagseguro/internal')->getFields('customer');
        $options = array();
        $options[] = array('value'=>'','label'=>'Solicitar junto com os outros dados do pagamento');

        foreach($fields as $key => $value) {
            if(!is_null($value['frontend_label'])) {
                $options['customer|'.$value['frontend_label']] = array('value' => 'customer|'.$value['attribute_code'], 'label' => 'Customer: '.$value['frontend_label'] . ' (' . $value['attribute_code'] . ')');
            }
        }

        $address_fields = Mage::helper('ricardomartins_pagseguro/internal')->getFields('customer_address');
        foreach($address_fields as $key => $value) {
            if(!is_null($value['frontend_label'])) {
                $options['address|'.$value['frontend_label']] = array('value' => 'billing|'.$value['attribute_code'], 'label' => 'Billing: '.$value['frontend_label'] . ' (' . $value['attribute_code'] . ')');
            }
        }

        return $options;
    }
}