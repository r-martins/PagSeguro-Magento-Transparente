<?php
/**
 * PagSeguro Transparente Magento
 *
 * @category    RicardoMartins
 * @package     RicardoMartins_PagSeguro
 * @author      Ricardo Martins
 * @copyright   Copyright (c) 2015 Ricardo Martins (http://r-martins.github.io/PagSeguro-Magento-Transparente/)
 * @license     https://opensource.org/licenses/MIT MIT License
 */
class RicardoMartins_PagSeguro_Model_Source_Ccbrand
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = array();
        $options[] = array('value'=>'42x20','label'=>'42x20 px');
        $options[] = array('value'=>'68x30','label'=>'68x30 px');
        $options[] = array('value'=>'','label'=>'Exibir apenas texto');


        return $options;
    }
}