<?php
class RicardoMartins_PagSeguro_TestController extends Mage_Core_Controller_Front_Action
{
    public function indexAction(){
        $this->getResponse()->setBody('works');
    }

    /**
     * Devolve algumas informações básicas sobre os modulos e suas configurações.
     * Apenas para fim de suporte e auxílio ao lojista.
     * Pode ser removido se assim preferir.
     */
    public function getConfigAction(){
        $info = array();
        $info['RicardoMartins_PagSeguro']['version'] = (string)Mage::getConfig()->getModuleConfig('RicardoMartins_PagSeguro')->version;
        $info['RicardoMartins_PagSeguro']['debug'] = Mage::getStoreConfigFlag('payment/pagseguro/debug');
        $info['RicardoMartins_PagSeguro']['sandbox'] = Mage::getStoreConfigFlag('payment/pagseguro/sandbox');

        if(Mage::getConfig()->getModuleConfig('RicardoMartins_PagSeguroPro')){
            $info['RicardoMartins_PagSeguroPro']['version'] = (string)Mage::getConfig()->getModuleConfig('RicardoMartins_PagSeguroPro')->version;
            $info['RicardoMartins_PagSeguroPro']['key_type'] = (string)Mage::getStoreConfig('payment/pagseguropro/key_type');
        }
        $this->getResponse()->setHeader('Content-type','application/json');
        $this->getResponse()->setBody(json_encode($info));
    }
}