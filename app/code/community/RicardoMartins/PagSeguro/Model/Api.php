<?php
/**
 * @package     RicardoMartins_PagSeguro
 * @copyright   Copyright (c) 2022 Gamuza Technologies (http://www.gamuza.com.br/)
 * @author      Eneias Ramos de Melo <eneias@gamuza.com.br>
 */

class RicardoMartins_PagSeguro_Model_Api extends Mage_Api_Model_Resource_Abstract
{
    public function sessionId()
    {
        return Mage::helper('ricardomartins_pagseguro')->getSessionId();
    }
}

