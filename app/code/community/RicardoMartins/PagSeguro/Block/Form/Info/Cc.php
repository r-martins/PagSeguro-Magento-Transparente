<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_Paygate
 * @copyright   Copyright (c) 2013 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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

    public function isSandbox()
    {
        $order = $this->getInfo()->getOrder();
        return (!$order || !$order->getId() || strpos($order->getCustomerEmail(), '@sandbox.pagseguro') === false) ? false : true;
    }

    public function isMultiCcPayment()
    {
        return $this->helper("ricardomartins_pagseguro")->isMultiCcEnabled() && 
               $this->getInfo()->getAdditionalInformation("use_two_cards");
    }

    public function getCc1Data()
    {
        $cc1 = $this->getInfo()->getAdditionalInformation("cc1");

        if($cc1)
        {
            return new Varien_Object($cc1);
        }

        return false;
    }

    public function getCc2Data()
    {
        $cc2 = $this->getInfo()->getAdditionalInformation("cc2");

        if($cc2 && $this->getInfo()->getAdditionalInformation("use_two_cards"))
        {
            return new Varien_Object($cc2);
        }

        return false;
    }

    public function formatTransactionId($transactionId)
    {
        return $this->isSandbox() ? str_replace('-', '', $transactionId) : $transactionId;
    }

    public function getTransactionUrlOnPagSeguro($transactionId)
    {
        if($this->isSandbox())
        {
            return "https://sandbox.pagseguro.uol.com.br/aplicacao/transacoes.html";
        }
        
        return "https://pagseguro.uol.com.br/transaction/details.jhtml?code=" . $this->escapeHtml($transactionId);
    }

    public function getTransactionStatus($transactionId)
    {
        $transaction = $this->getInfo()->lookupTransaction($transactionId);

        if($transaction && $transaction->getAdditionalInformation("status"))
        {
            return $this->getTransactionStatusDescription($transaction->getAdditionalInformation("status"));
        }

        return "";
    }

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

    public function isForceUpdateEnabled()
    {
        return Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/pagseguro_update');
    }

    public function getForceUpdateUrl($transactionId)
    {
        return Mage::helper('adminhtml')->getUrl('adminhtml/updatePayment/transaction', array
        (
            "order_id"       => $this->getInfo()->getOrder()->getId(),
            "transaction_id" => $transactionId,
        ));
    }
    
    private function _useNewInfoFormat()
    {
        return $this->getInfo()->getAdditionalInformation("cc1") != null;
    }

    protected function _beforeToHtml()
    {
        if($this->_useNewInfoFormat())
        {
            $this->setTemplate('ricardomartins_pagseguro/form/info/multi-cc.phtml');
        }

        return $this;
    }
}
