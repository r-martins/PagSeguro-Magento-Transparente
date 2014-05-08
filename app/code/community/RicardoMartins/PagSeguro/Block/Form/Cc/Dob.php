<?php

class RicardoMartins_PagSeguro_Block_Form_Cc_Dob extends Mage_Customer_Block_Widget_Dob
{
    public function _construct()
    {
        parent::_construct();

        // default template location | caminho do template de data de nascimento
        $this->setTemplate('ricardomartins_pagseguro/form/cc/dob.phtml');
        $this->setFieldNameFormat('payment[ps_cc_owner_birthday_%s]');
        $this->setFieldIdFormat('pagseguro_ps_cc_owner_birthday_%s');
    }
}
