<?php
class RicardoMartins_PagSeguro_Test_Helper_Params extends EcomDev_PHPUnit_Test_Case
{
    public function testItemValuesWithInstallments()
    {
        $order = Mage::getModel('sales/order');
        $order->setCustomerId(1);
        $order->addItem($this->getItemData1());

        $payment = Mage::getModel('sales/order_payment')->setAdditionalInformation('installment_quantity', 2);
        $order->setPayment($payment);

        $helper = Mage::helper('ricardomartins_pagseguro/params');
        $params = $helper->getItemsParams($order);

        $installments = $helper->getCreditCardInstallmentsParams($order, $payment);
        $this->assertEquals('33.98', $installments['installmentValue']);

    }

    //@TODO Improve it
    protected function getItemData1()
    {
        return Mage::getModel('sales/order_item')
            ->getCollection()->getFirstItem()
            ->setQtyOrdered(1)
            ->setPrice(33.98)
            ->setName('Produto Teste 1');
    }
}