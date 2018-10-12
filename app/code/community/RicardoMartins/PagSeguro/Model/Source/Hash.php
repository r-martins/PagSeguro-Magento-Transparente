<?php
/**
 * PagSeguro Transparente Magento
 * Check if hash is available and give a only "no" option. Otherwise, yesno is available.
 *
 * @category    RicardoMartins
 * @package     RicardoMartins_PagSeguro
 * @author      Ricardo Martins
 * @copyright   Copyright (c) 2015 Ricardo Martins (http://r-martins.github.io/PagSeguro-Magento-Transparente/)
 * @license     https://opensource.org/licenses/MIT MIT License
 */
class RicardoMartins_PagSeguro_Model_Source_Hash
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = array();
        if(!function_exists('hash') || !in_array('sha256',hash_algos()) || !in_array('md5',hash_algos())){
            $options[] = array('value'=>0, 'label'=>'Não suportado no seu ambiente.');
            return $options;
        }

        return Mage::getModel('adminhtml/system_config_source_yesno')->toOptionArray();
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        if(!function_exists('hash') || !in_array('sha256',hash_algos()) || !in_array('md5',hash_algos())){
            return array(
                0 => Mage::helper('adminhtml')->__('No')
            );
        }

        return Mage::getModel('adminhtml/system_config_source_yesno')->toArray();
    }
}
