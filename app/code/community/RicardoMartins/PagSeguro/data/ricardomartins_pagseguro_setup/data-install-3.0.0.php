<?php
/**
 * Migra configurações antigas(se houver) pro padrão novo, evitando conflitos com outros módulos PagSeguro
 */

/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;

$token = Mage::getStoreConfig('payment/pagseguro/token');
$decryptedToken = Mage::helper('core')->decrypt($token);
Mage::log(var_export('ran data', true), null, 'martins.log', true);
if ($token != false && (strlen($decryptedToken) == 32 || strlen($decryptedToken) == 100) ) {
    $sql = "UPDATE {$this->getTable('core/config_data')} 
            SET path = REPLACE(path, 'payment/pagseguro/', 'payment/rm_pagseguro/')
            WHERE path LIKE 'payment/pagseguro/%'";

    try {
        $installer->getConnection()->query($sql);
    } catch (Exception $e) {
        Mage::log(
            'Erro ao atualizar configurações do módulo PagSeguro. Por favor revise as configurações do módulo '
            . 'PagSeguro (Ricardo Martins) apenas por precaução. Veja log de exceções para mais detalhes. Isto '
            . 'pode acontecer ao atualizar uma versão antiga do módulo PagSeguro, mas não trará problemas futuros.',
            Zend_log::ERR, 'pagseguro.log', true
        );
        Mage::logException($e);
    }
}
