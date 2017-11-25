<?php
/**
 * Class RicardoMartins_PagSeguro_Model_Resource_Kiosk
 *
 * @author    Ricardo Martins <ricardo@magenteiro.com>
 */
class RicardoMartins_PagSeguro_Model_Resource_Kiosk extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct()
    {
        $this->_init('ricardomartins_pagseguro/kiosk', 'temporary_order_id');
    }
}