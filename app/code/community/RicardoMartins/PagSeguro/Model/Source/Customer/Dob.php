<?php

/**
 * Payment Method Codes
 *
 * @author    Ricardo Martins <ricardo@ricardomartins.net.br>
 */
class RicardoMartins_PagSeguro_Model_Source_Customer_Dob
{
    /**
     * Retorna os atributos de endereco
     * @author Gabriela D'Ávila (http://davila.blog.br)
     * @return array
     */
    public function toOptionArray() {
        $fields = Mage::helper('ricardomartins_pagseguro/internal')->getFields('customer');
        $options = array();
        $options[] = array('value'=>'','label'=>'Solicitar ao cliente junto com dados do cartão');

        foreach($fields as $key => $value) {
            if(!is_null($value['frontend_label'])) {
                $options[] = array('value' => $value['attribute_code'], 'label' => $value['frontend_label'] . ' (' . $value['attribute_code'] . ')');
            }
        }

        return $options;
    }
}