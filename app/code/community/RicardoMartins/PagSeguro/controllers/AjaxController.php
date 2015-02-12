<?php
class RicardoMartins_PagSeguro_AjaxController extends Mage_Core_Controller_Front_Action
{
    /**
     * Metodo auxiliar para obter o total do pedido
     */
    public function getGrandTotalAction()
    {
        $total = Mage::helper('checkout/cart')->getQuote()->getGrandTotal();

        $this->getResponse()->setHeader('Content-type','application/json', true);
        $this->getResponse()->setBody(json_encode(array('total'=>$total)));
    }

    public function getSessionIdAction()
    {
        $_helper = Mage::helper('ricardomartins_pagseguro');
        $session_id = $_helper->getSessionId();

        $this->getResponse()->setHeader('Content-type','application/json', true);
        $this->getResponse()->setBody(json_encode(array('session_id'=>$session_id)));
    }
}
