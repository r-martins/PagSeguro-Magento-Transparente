<?php
/**
 * For viewing orders in older versions < 3.0
 * Class RicardoMartins_PagSeguro_Model_Payment_Ccview
 *
 * @author    Ricardo Martins <ricardo@ricardomartins.net.br>
 */
class RicardoMartins_PagSeguro_Model_Payment_Ccview extends RicardoMartins_PagSeguro_Model_Payment_Cc
{
    public function isAvailable($quote = null)
    {
        return false;
    }
}