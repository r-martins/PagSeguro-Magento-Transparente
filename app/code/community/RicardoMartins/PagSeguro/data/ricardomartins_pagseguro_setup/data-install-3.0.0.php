<?php
/**
 * Migra configurações antigas(se houver) pro padrão novo, evitando conflitos com outros módulos PagSeguro
 */

/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;

$token = Mage::getStoreConfig('payment/pagseguro/token');
$decryptedToken = Mage::helper('core')->decrypt($token);

if ($token != false && (strlen($decryptedToken) == 32 || strlen($decryptedToken) == 100) ) {
    $sql = "UPDATE {$this->getTable('core/config_data')} 
            SET path = REPLACE(path, 'payment/pagseguro/', 'payment/rm_pagseguro/')
            WHERE path LIKE 'payment/pagseguro/%'";

    $installer->getConnection()->query($sql);
}
