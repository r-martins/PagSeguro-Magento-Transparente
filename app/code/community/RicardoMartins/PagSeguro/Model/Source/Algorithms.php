<?php
/**
 * PagSeguro Transparente Magento
 *
 * @package     RicardoMartins_PagSeguro
 * @copyright   Copyright (c) 2018 Gamuza Technologies (http://www.gamuza.com.br/)
 * @author      Eneias Ramos de Melo <eneias@gamuza.com.br>
 * @license     https://opensource.org/licenses/GPL-2.0
 */
class RicardoMartins_PagSeguro_Model_Source_Algorithms
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = array(
            array('value' => 'md5',    'label' => 'MD5'),
            array('value' => 'sha256', 'label' => 'SHA256')
        );

        return $options;
    }
}

