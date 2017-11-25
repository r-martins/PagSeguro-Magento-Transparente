<?php
/**
 * Setup script para criação da tabela de kiosk, usada para vendas avulsas de um único produto
 * Setup script used to create standalone orders with single product
 */

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();
$tableName = $installer->getTable('ricardomartins_pagseguro/kiosk');

$kioskTable = $installer->getConnection()->newTable($tableName)
    ->addColumn('temporary_order_id', Varien_Db_Ddl_Table::TYPE_INTEGER, 8, array(
        'identity' => true,
        'nullable' => false,
        'primary' => true,
    ))
    ->addColumn('temporary_reference', Varien_Db_Ddl_Table::TYPE_VARCHAR, 32, array())
    ->addColumn('name', Varien_Db_Ddl_Table::TYPE_VARCHAR, 120, array())
    ->addColumn('email', Varien_Db_Ddl_Table::TYPE_VARCHAR, 120, array())
    ->addColumn('pagseguro_email', Varien_Db_Ddl_Table::TYPE_VARCHAR, 120, array())
    ->addColumn('product_id', Varien_Db_Ddl_Table::TYPE_INTEGER, 10, array())
    ->addColumn('transaction_code', Varien_Db_Ddl_Table::TYPE_VARCHAR, 40, array())
    ->addColumn('checkout_code', Varien_Db_Ddl_Table::TYPE_VARCHAR, 35, array(), 'Checkout code devolvido na criação do checkout')
    ->addColumn('customer_id', Varien_Db_Ddl_Table::TYPE_INTEGER, 10, array())
    ->addColumn('order_id', Varien_Db_Ddl_Table::TYPE_INTEGER, 10, array())
    ->addColumn('redirect_after', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(), 'URL para ser redirecionado após o sucesso')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, 10, array(
        'nullable'=>false,
        'default' => Varien_Db_Ddl_Table::TIMESTAMP_INIT
    ));

//criará a tabela ricardomartins_pagseguro_kiosk // create table ricardomartins_pagseguro_kiosk
$installer->getConnection()->createTable($kioskTable);
$installer->endSetup();