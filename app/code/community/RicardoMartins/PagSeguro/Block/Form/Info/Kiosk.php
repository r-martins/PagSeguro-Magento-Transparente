<?php
/**
 * Class RicardoMartins_PagSeguro_Block_Form_Info_Kiosk
 *
 * @author    Ricardo Martins
 */
class RicardoMartins_PagSeguro_Block_Form_Info_Kiosk extends Mage_Payment_Block_Info
{
    /**
     * Set block template
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('ricardomartins_pagseguro/form/info/kiosk.phtml');
    }

}
