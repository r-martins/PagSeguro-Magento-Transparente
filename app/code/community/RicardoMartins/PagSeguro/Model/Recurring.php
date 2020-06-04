<?php
/**
 * Class RicardoMartins_PagSeguro_Model_Recurring - Responsible for Recurring payments operations with PagSeguro
 *
 * @author    Ricardo Martins
 * @copyright 2020 Magenteiro
 */
class RicardoMartins_PagSeguro_Model_Recurring extends RicardoMartins_PagSeguro_Model_Abstract
{
    const PREAPPROVAL_PERIOD_WEEKLY = 'WEEKLY';
    const PREAPPROVAL_PERIOD_MONTHLY = 'MONTHLY';
    const PREAPPROVAL_PERIOD_BIMONTHLY = 'BIMONTHLY';
    const PREAPPROVAL_PERIOD_TRIMONTHLY = 'TRIMONTHLY';
    const PREAPPROVAL_PERIOD_SEMIANNUALLY = 'SEMIANNUALLY';
    const PREAPPROVAL_PERIOD_YEARLY = 'YEARLY';

    /** @var RicardoMartins_PagSeguro_Helper_Recurring $_helper */
    protected $_helper;

    public function __construct()
    {
        $this->_helper = Mage::helper('ricardomartins_pagseguro/recurring');
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     */
    public function createPlanFromProduct($product)
    {
       if (!$product->isRecurring()) {
           return false;
       }

       $params = array(
         'reference' => $this->_helper->getProductReference($product),
         'preApprovalName' => $product->getName(),
         'preApprovalCharge' => '',
         'preApprovalPeriod' => '',
         'preApprovalAmountPerPayment' => '',
         'preApprovalMembershipFee' => '',
         'preApprovalTrialPeriodDuration' => '',
         'preApprovalExpirationValue' => '',
         'preApprovalExpirationUnit' => '',
         'preApprovalDetails' => $product->getShortDescription(),
         'preApprovalMaxTotalAmount' =>'',
         'preApprovalCancelURL' =>'',
         'reviewURL' =>'',
         'maxUses' =>'',
         'receiver[email]' => '',
       );
    }

    /**
     * Retrieve current status of the subscription at PagSeguro
     * @param $preApprovalCode
     *
     * @return mixed
     * @throws Mage_Core_Exception
     */
    public function getPreApprovalDetails($preApprovalCode)
    {
        $headers[] = 'Accept: application/vnd.pagseguro.com.br.v3+json;charset=ISO-8859-1';
        $headers[] = 'Content-Type: application/json';
        $key = Mage::getStoreConfig('payment/pagseguropro/key');
        $helper = Mage::helper('ricardomartins_pagseguro');
        $type = 'pre-approvals/' . $preApprovalCode;
        $urlws = $helper->getWsUrl($type . "?public_key={$key}", true);
        $urlws = str_replace('/v2/', '/', $urlws);

        $helper->writeLog('Chamando API GET (/'. $type .')');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $urlws);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = '';

        try{
            $response = curl_exec($ch);
            $helper->writeLog('Retorno PagSeguro API GET: ' . $response);

            if (json_decode($response) !== null) {
                return json_decode($response);
            }

            Mage::throwException(
                'Falha ao decodificar retorno das informações do plano. Formato retornado inesperado.'
            );
        }catch(Exception $e){
            Mage::throwException('Falha na comunicação com Pagseguro (' . $e->getMessage() . ')');
        }

        if (curl_error($ch)) {
            Mage::throwException(
                sprintf(
                    'Falha ao tentar obter dados da assinatura %s: %s (%s)',
                    $preApprovalCode,
                    curl_error($ch),
                    curl_errno($ch)
                )
            );
        }
    }

}