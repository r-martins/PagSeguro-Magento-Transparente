<?php
/**
 * Payment Method Codes
 *
 * @author    Ricardo Martins <ricardo@ricardomartins.net.br>
 */
class RicardoMartins_PagSeguro_Model_Source_Paymentmethods
{
    public function toOptionArray()
    {
        $options = array();
        $options[] = array('value'=>'101','label'=>'Cartão de crédito Visa');
        $options[] = array('value'=>'102','label'=>'Cartão de crédito MasterCard');
        $options[] = array('value'=>'103','label'=>'Cartão de crédito American Express');
        $options[] = array('value'=>'104','label'=>'Cartão de crédito Diners');
        $options[] = array('value'=>'105','label'=>'Cartão de crédito Hipercard');
        $options[] = array('value'=>'106','label'=>'Cartão de crédito Aura');
        $options[] = array('value'=>'107','label'=>'Cartão de crédito Elo');
        $options[] = array('value'=>'108','label'=>'Cartão de crédito PLENOCard');
        $options[] = array('value'=>'109','label'=>'Cartão de crédito PersonalCard');
        $options[] = array('value'=>'110','label'=>'Cartão de crédito JCB');
        $options[] = array('value'=>'111','label'=>'Cartão de crédito Discover');
        $options[] = array('value'=>'112','label'=>'Cartão de crédito BrasilCard');
        $options[] = array('value'=>'113','label'=>'Cartão de crédito FORTBRASIL');
        $options[] = array('value'=>'114','label'=>'Cartão de crédito CARDBAN');
        $options[] = array('value'=>'115','label'=>'Cartão de crédito VALECARD');
        $options[] = array('value'=>'116','label'=>'Cartão de crédito Cabal');
        $options[] = array('value'=>'117','label'=>'Cartão de crédito Mais!');
        $options[] = array('value'=>'118','label'=>'Cartão de crédito Avista');
        $options[] = array('value'=>'119','label'=>'Cartão de crédito GRANDCARD');
        $options[] = array('value'=>'201','label'=>'Boleto Bradesco');
        $options[] = array('value'=>'202','label'=>'Boleto Santander');
        $options[] = array('value'=>'301','label'=>'Débito online Bradesco');
        $options[] = array('value'=>'302','label'=>'Débito online Itaú');
        $options[] = array('value'=>'303','label'=>'Débito online Unibanco');
        $options[] = array('value'=>'304','label'=>'Débito online Banco do Brasil');
        $options[] = array('value'=>'305','label'=>'Débito online Banco Real');
        $options[] = array('value'=>'306','label'=>'Débito online Banrisul');
        $options[] = array('value'=>'307','label'=>'Débito online HSBC');
        $options[] = array('value'=>'401','label'=>'Saldo PagSeguro');
        $options[] = array('value'=>'501','label'=>'Oi Paggo');
        $options[] = array('value'=>'701','label'=>'Depósito em conta - Banco do Brasil');

        return $options;
    }
}