<?php

/**
 * Class RicardoMartins_PagSeguro_Block_Form_Info_Cc
 *
 * @author    Ricardo Martins <ricardo@magenteiro.com> and Fillipe Dutra
 * @copyright 2021 Magenteiro
 */
class RicardoMartins_PagSeguro_Block_Form_Info_Cc extends Mage_Payment_Block_Info
{
    /**
     * Set block template
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('ricardomartins_pagseguro/form/info/cc.phtml');
    }

    /**
     * @return bool
     */
    public function isSandbox()
    {
        $order = $this->getInfo()->getOrder();
        return (!$order || !$order->getId() || strpos($order->getCustomerEmail(), '@sandbox.pagseguro') === false)
            ? false : true;
    }

    /**
     * @return bool
     */
    public function isMultiCcPayment()
    {
        return $this->helper("ricardomartins_pagseguro")->isMultiCcEnabled() && 
               $this->getInfo()->getAdditionalInformation("use_two_cards");
    }

    /**
     * @return false|Varien_Object
     */
    public function getCc1Data()
    {
        $cc1 = $this->getInfo()->getAdditionalInformation("cc1");

        if ($cc1) {
            return new Varien_Object($cc1);
        }

        return false;
    }

    /**
     * @return false|Varien_Object
     */
    public function getCc2Data()
    {
        $cc2 = $this->getInfo()->getAdditionalInformation("cc2");

        if ($cc2 && $this->getInfo()->getAdditionalInformation("use_two_cards")) {
            return new Varien_Object($cc2);
        }

        return false;
    }

    /**
     * @param $transactionId
     *
     * @return string|string[]
     */
    public function formatTransactionId($transactionId)
    {
        return $this->isSandbox() ? str_replace('-', '', $transactionId) : $transactionId;
    }

    /**
     * @param $transactionId
     *
     * @return string
     */
    public function getTransactionUrlOnPagSeguro($transactionId)
    {
        if ($this->isSandbox()) {
            return "https://sandbox.pagseguro.uol.com.br/aplicacao/transacoes.html";
        }
        
        return "https://pagseguro.uol.com.br/transaction/details.jhtml?code=" . $this->escapeHtml($transactionId);
    }

    /**
     * @param $transactionId
     *
     * @return string
     */
    public function getTransactionStatus($transactionId)
    {
        $transaction = $this->getInfo()->lookupTransaction($transactionId);

        if ($transaction && $transaction->getAdditionalInformation("status")) {
            return $this->getTransactionStatusDescription($transaction->getAdditionalInformation("status"));
        }

        return "";
    }

    /**
     * @param $status
     *
     * @return string
     */
    public function getTransactionStatusDescription($status)
    {
        switch($status)
        {
            case RicardoMartins_PagSeguro_Model_Abstract::PS_TRANSACTION_STATUS_PENDING_PAYMENT:
                return "Aguardando pagamento";
            case RicardoMartins_PagSeguro_Model_Abstract::PS_TRANSACTION_STATUS_REVIEW:
                return "Em análise";
            case RicardoMartins_PagSeguro_Model_Abstract::PS_TRANSACTION_STATUS_PAID:
                return "Paga";
            case RicardoMartins_PagSeguro_Model_Abstract::PS_TRANSACTION_STATUS_AVAILABLE:
                return "Disponível";
            case RicardoMartins_PagSeguro_Model_Abstract::PS_TRANSACTION_STATUS_CONTESTED:
                return "Em disputa";
            case RicardoMartins_PagSeguro_Model_Abstract::PS_TRANSACTION_STATUS_REFUNDED:
                return "Devolvida";
            case RicardoMartins_PagSeguro_Model_Abstract::PS_TRANSACTION_STATUS_CANCELED:
                return "Cancelada";
            case RicardoMartins_PagSeguro_Model_Abstract::PS_TRANSACTION_STATUS_DEBITED:
                return "Debitado";
            case RicardoMartins_PagSeguro_Model_Abstract::PS_TRANSACTION_STATUS_TEMPORARY_RETENTION:
                return "Retenção temporária";
        }

        return "";
    }

    /**
     * @return bool
     */
    public function isForceUpdateEnabled()
    {
        return Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/pagseguro_update');
    }

    /**
     * @param $transactionId
     *
     * @return string
     */
    public function getForceUpdateUrl($transactionId)
    {
        return Mage::helper('adminhtml')->getUrl(
            'adminhtml/updatePayment/transaction',
            array(
            "order_id"       => $this->getInfo()->getOrder()->getId(),
            "transaction_id" => $transactionId,
            )
        );
    }

    /**
     * Check if we should expect multiple creditcard format
     * @return bool
     */
    protected function _useNewInfoFormat()
    {
        $additionalInfo = $this->getInfo()->getAdditionalInformation();
        return isset($additionalInfo["use_two_cards"]);
    }

    /**
     * Before rendering html, but after trying to load cache
     *
     * @return Mage_Core_Block_Abstract
     */
    protected function _beforeToHtml()
    {
        if ($this->_useNewInfoFormat()) {
            $this->setTemplate('ricardomartins_pagseguro/form/info/multi-cc.phtml');
        }

        return $this;
    }
}
