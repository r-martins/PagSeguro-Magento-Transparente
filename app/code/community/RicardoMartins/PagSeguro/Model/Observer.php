<?php
class RicardoMartins_PagSeguro_Model_Observer
{
    /**
     * Adiciona o bloco do direct payment logo após um dos forms do pagseguro ter sido inserido.
     * @param $observer
     *
     * @return $this
     */
    public function addDirectPaymentBlock($observer)
    {
        $pagseguroBlocks = array('ricardomartins_pagseguropro/form_boleto','ricardomartins_pagseguro/form_cc');
        $blockType = $observer->getBlock()->getType();
        if(in_array($blockType,$pagseguroBlocks)){
            $output = $observer->getTransport()->getHtml();
            $directpayment = Mage::app()->getLayout()->createBlock('ricardomartins_pagseguro/form_directpayment')->toHtml();
            $observer->getTransport()->setHtml($output . $directpayment);

        }
        return $this;
    }
}