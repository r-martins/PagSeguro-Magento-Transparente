<?php

class RicardoMartins_PagSeguro_Block_Product_Installments extends Mage_Core_Block_Template
{
    public function _toHtml()
    {
        if (!$this->isEnabled()) {
            return '';
        }

        return parent::_toHtml();
    }

    public function getPrice()
    {
        $product = $this->getProduct();
        if (!$product) {
            $product = Mage::registry('current_product');
        }

        return $product->getFinalPrice();
    }

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('ricardomartins_pagseguro/product/installments.phtml');
    }

    /**
     * Checks if display installments on page is enabled and if the maximum number of installments is not 1
     * @return bool
     */
    public function isEnabled()
    {
        if ($this->getNameInLayout() != 'ricardomartins.pagseguro.parcelas') {
            return true;
        }

        $displayInstallmentsOnProductPage = Mage::getStoreConfigFlag('payment/rm_pagseguro_cc/installments_product');
        $maxInstallments = (int)Mage::getStoreConfig(
            RicardoMartins_PagSeguro_Helper_Data::XML_PATH_PAYMENT_PAGSEGURO_CC_INSTALLMENT_LIMIT
        );
        return $displayInstallmentsOnProductPage && $maxInstallments !== 1;
    }

    protected function _prepareLayout()
    {
        if (!$this->isEnabled()) {
            return parent::_prepareLayout();
        }

        //adicionaremos o JS do pagseguro na tela que usará o bloco de installments logo após o <body>
        $head = Mage::app()->getLayout()->getBlock('after_body_start');

        if ($head && false == $head->getChild('pagseguro_direct')) {
            $scriptBlock = Mage::helper('ricardomartins_pagseguro')->getExternalPagSeguroScriptBlock();
            $head->append($scriptBlock);
        }

        return parent::_prepareLayout();
    }

    /**
     * Converte a messagem especificada em XML_PATH_PAYMENT_PAGSEGURO_CC_INSTALLMENT_PRODUCT_MESSAGE
     * para exibição na página de produtos
     */
    public function _messageToHTML()
    {
        $variables = array(
            "num_parcelas" => "installments", /** Número de parcelas. Ex.: 12 */
            "valor_parcela" => "installment_value", /** Preço da parcela do produto */
            "valor_total" => "installment_total" /** Preço total do produto (com o valor dos juros incluso, caso haja) */
        );
        $message = Mage::getStoreConfig(
            RicardoMartins_PagSeguro_Helper_Data::XML_PATH_PAYMENT_PAGSEGURO_CC_INSTALLMENT_PRODUCT_MESSAGE
        );

        foreach ($variables as $variable => $id) {
            $message = str_replace("{".$variable."}", "<span id='$id'></span>", $message);
        }

        return $message;
    }
}