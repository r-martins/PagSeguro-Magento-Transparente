<?php
/**
 * PagSeguro Transparente Magento
 * Ajax Controller responsible for module's ajax requests
 *
 * @category    RicardoMartins
 * @package     RicardoMartins_PagSeguro
 * @author      Ricardo Martins
 * @copyright   Copyright (c) 2015 Ricardo Martins (http://r-martins.github.io/PagSeguro-Magento-Transparente/)
 * @license     https://opensource.org/licenses/MIT MIT License
 */
class RicardoMartins_PagSeguro_AjaxController extends Mage_Core_Controller_Front_Action
{

    /**
     * Returns the order grand total, used to get installments of CC method
     */
    public function getGrandTotalAction()
    {
        $total = Mage::helper('checkout/cart')->getQuote()->getGrandTotal();

        $this->getResponse()->setHeader('Content-type', 'application/json', true);
        $this->getResponse()->setBody(json_encode(array('total'=>$total)));
    }

    /**
     * Return session Id from PagSeguro, based on merchant e-mail and token
     * Double check your e-mail and token at http://r-martins.github.io/PagSeguro-Magento-Transparente/#faq
     */
    public function getSessionIdAction()
    {
        $_helper = Mage::helper('ricardomartins_pagseguro');
        $sessionId = $_helper->getSessionId();

        $this->getResponse()->setHeader('Content-type', 'application/json', true);
        $this->getResponse()->setBody(json_encode(array('session_id' => $sessionId)));
    }
}
