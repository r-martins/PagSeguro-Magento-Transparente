<?php

class RicardoMartins_PagSeguro_Model_System_Config_Source_Customer_Groups
{
    public function toOptionArray ()
    {
        return Mage::getModel('customer/group')->getCollection()
            ->toOptionArray();
    }
}

