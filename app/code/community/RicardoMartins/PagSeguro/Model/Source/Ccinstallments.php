<?php
/**
 * PagSeguro Transparente Magento
 *
 * @category    RicardoMartins
 * @package     RicardoMartins_PagSeguro
 * @author      Ricardo Martins
 * @copyright   Copyright (c) 2017 Ricardo Martins (http://r-martins.github.io/PagSeguro-Magento-Transparente/)
 * @license     https://opensource.org/licenses/MIT MIT License
 */
class RicardoMartins_PagSeguro_Model_Source_Ccinstallments
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = array();
        $options[] = array('value'=>'','label'=>'Usar configurações definidas na conta PagSeguro (recomendável)');
        $options[] = array('value'=>'1','label'=>'Apenas pagamentos à vista (1x)');

        for ($x=2; $x <= 18; $x++) {
            $options[] = array('value'=>$x,'label'=>$x . ' vezes');
        }

        return $options;
    }
}