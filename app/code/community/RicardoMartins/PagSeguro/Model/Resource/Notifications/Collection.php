<?php
 
class RicardoMartins_PagSeguro_Model_Resource_Notifications_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{

    protected function _construct()
    {
        $this->_init('ricardomartins_pagseguro/notifications');
    }

}