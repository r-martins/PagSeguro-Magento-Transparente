<?php

/**
 * Payment Method Codes
 *
 * @author    Ricardo Martins <ricardo@ricardomartins.net.br>
 */
class RicardoMartins_PagSeguro_Model_Source_Customer_Address_Attributes
{
    /**
     * Retorna os atributos de endereco
     * @author Gabriela D'Ávila (http://davila.blog.br)
     * @return array
     */
    public function toOptionArray() {
        $fields = Mage::helper('ricardomartins_pagseguro/internal')->getFields('customer_address');
        $options = array();

        foreach($fields as $key => $value) {
            if(!is_null($value['frontend_label'])) {
                //caso esteja sendo usado a propriedade multilinha do endereco, ele aceita indicar o que cada linha faz
                if($value['attribute_code'] == 'street') {
                    $street_lines = Mage::getStoreConfig('customer/address/street_lines');
                    for($i = 1; $i <= $street_lines; $i++){
                        $options[] = array('value' => 'street_'.$i, 'label' => 'Street Line '.$i);
                    }
                } else {
                    $options[] = array('value' => $value['attribute_code'], 'label' => $value['frontend_label'] . ' (' . $value['attribute_code'] . ')');
                }
            }
        }
        return $options;
    }
}