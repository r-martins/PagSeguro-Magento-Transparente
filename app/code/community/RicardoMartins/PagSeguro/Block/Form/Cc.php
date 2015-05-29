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

class RicardoMartins_PagSeguro_Block_Form_Cc extends Mage_Payment_Block_Form_Cc
{
    /**
     * Set block template
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('ricardomartins_pagseguro/form/cc.phtml');
    }

    /**
     * Insere o javascript do modulo somente na hora da renderização, caso ainda não tenha sido inserido.
     * @return Mage_Core_Block_Abstract
     */
    protected function _prepareLayout()
    {
        //adicionaremos o JS do pagseguro na tela que usará o bloco de cartao logo após o <body>
        $head = Mage::app()->getLayout()->getBlock('after_body_start');

        if ($head && false == $head->getChild('js_pagseguro')) {
            $scriptBlock = Mage::helper('ricardomartins_pagseguro')->getPagSeguroScriptBlock();
            $head->append($scriptBlock);
        }

        return parent::_prepareLayout();
    }

    public function isDobVisible()
    {
        $ownerDobAttribute = Mage::getStoreConfig('payment/pagseguro_cc/owner_dob_attribute');
        return empty($ownerDobAttribute);
    }

}
