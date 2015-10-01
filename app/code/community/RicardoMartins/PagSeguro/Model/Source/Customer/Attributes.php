<?php
/**
 * PagSeguro Transparente Magento
 * Customer Attributes source, used for config
 *
 * @category    RicardoMartins
 * @package     RicardoMartins_PagSeguro
 * @author      Ricardo Martins
 * @copyright   Copyright (c) 2015 Ricardo Martins (http://r-martins.github.io/PagSeguro-Magento-Transparente/)
 * @license     https://opensource.org/licenses/MIT MIT License
 */
class RicardoMartins_PagSeguro_Model_Source_Customer_Attributes
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $fields = Mage::helper('ricardomartins_pagseguro/internal')->getFields('customer');
        $options = array();

        foreach ($fields as $key => $value) {
            if (!is_null($value['frontend_label'])) {
                $options[$value['frontend_label']] = array(
                    'value' => $value['attribute_code'],
                    'label' => $value['frontend_label'] . ' (' . $value['attribute_code'] . ')'
                );
            }
        }

        return $options;
    }
}