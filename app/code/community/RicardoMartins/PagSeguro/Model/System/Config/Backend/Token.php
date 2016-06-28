<?php
/**
 * PagSeguro Transparente Magento
 * Token Backend model - used for token validation on saving or changing
 *
 * @category    RicardoMartins
 * @package     RicardoMartins_PagSeguro
 * @author      Ricardo Martins
 * @copyright   Copyright (c) 2015 Ricardo Martins (http://r-martins.github.io/PagSeguro-Magento-Transparente/)
 * @license     https://opensource.org/licenses/MIT MIT License
 * @deprecated  2.5.3 There is no official method to validate token at PagSeguro.
 */
class RicardoMartins_PagSeguro_Model_System_Config_Backend_Token
    extends Mage_Adminhtml_Model_System_Config_Backend_Encrypted
{
    /**
     * Decrypt and test current saved token
     * @deprecated 2.5.3 There is no official method to validate token at PagSeguro.
     */
    public function _afterSave()
    {
        $token = Mage::helper('core')->decrypt($this->getValue());
        if (!empty($token) && $token != $this->getOldValue()) {
            $valid = $this->testToken($this->getFieldsetDataValue('merchant_email'), $token);
            if ($valid !== true) {
                Mage::getSingleton('core/session')->addWarning($valid);
            }
        }

        parent::_afterSave();
    }

    /**
     * Test token by calling PagSeguro session API
     * @param $email
     * @param $token
     * @deprecated 2.5.3 There is no official method to validate token at PagSeguro.
     *
     * @return bool|string
     */
    protected function testToken($email, $token)
    {
        $helper = Mage::helper('ricardomartins_pagseguro');
        $url = $helper->getWsUrl('sessions/');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, sprintf('email=%s&token=%s', $email, $token));
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        $ret = curl_exec($ch);
        curl_close($ch);
        libxml_use_internal_errors(true);
        if ($ret == 'Forbidden' && $helper->getLicenseType() == '') {
            return 'PagSeguro: Token de produção não habilitado para utilizar checkout transparente.
                    Você pode <a href="https://pagseguro.uol.com.br/receba-pagamentos.jhtml#checkout-transparent"
                    target="_blank">solicitar a liberação junto ao PagSeguro</a> ou instalar a
                    <a href="http://r-martins.github.io/PagSeguro-Magento-Transparente/pro/app.html"
                    target="_blank">versão PRO APP</a> que não requer autorização.';
        }

        $valid = simplexml_load_string($ret) !== false;
        if (!$valid) {
            return 'PagSeguro: Token de Produção inválido. Se necessário, utilize
                    <a href="http://r-martins.github.io/PagSeguro-Magento-Transparente/#faq" target="_blank">
                    esta ferramenta</a> para validar.';
        }

        return true;
    }
}