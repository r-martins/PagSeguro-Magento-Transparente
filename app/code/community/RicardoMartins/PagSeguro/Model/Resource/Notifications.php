<?php
 
class RicardoMartins_PagSeguro_Model_Resource_Notifications extends Mage_Core_Model_Resource_Db_Abstract
{

    protected function _construct()
    {
        $this->_init('ricardomartins_pagseguro/notifications', 'notification_id');
    }

}