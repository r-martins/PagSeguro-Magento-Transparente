<?php

/**
 * Payment Method Codes
 *
 * @author    Ricardo Martins <ricardo@ricardomartins.net.br>
 */
class RicardoMartins_PagSeguro_Model_Source_Customer_Attributes
{
    public function toOptionArray() {
        $fields = Mage::helper('ricardomartins_pagseguro/internal')->getFields('customer');
        $options = array();

        foreach($fields as $key => $value) {
            if(!is_null($value['frontend_label'])) {
                $options[$value['frontend_label']] = array('value' => $value['attribute_code'], 'label' => $value['frontend_label'] . ' (' . $value['attribute_code'] . ')');
            }
        }

        return $options;
    }
}