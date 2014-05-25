<?php
class RicardoMartins_PagSeguro_AjaxController extends Mage_Core_Controller_Front_Action
{
    /**
     * Metodo auxiliar para obter o total do pedido
     */
    public function getGrandTotalAction()
    {
        $total = Mage::helper('checkout/cart')->getQuote()->getGrandTotal();

        $this->getResponse()->setHeader('Content-type','application/json');
        $this->getResponse()->setBody(json_encode(array('total'=>$total)));
    }
}