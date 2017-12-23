<?php
class RicardoMartins_PagSeguro_Model_Kiosk extends Mage_Core_Model_Abstract
{
    protected $_websiteId;
    protected $_store;


    protected function _construct()
    {
        $this->_store = Mage::app()->getStore();
        $this->_websiteId = Mage::app()->getWebsite()->getId();

        $this->_init('ricardomartins_pagseguro/kiosk');
    }

    /**
     * Create new order from notification sent from PagSeguro
     * @param $observer
     *
     * @throws Exception
     */
    public function createOrderFromNotification($observer)
    {
        $notificationXML = $observer->getKioskNotification()->getNotificationXml();

        $this->getCollection()->addFieldToFilter('temporary_reference', (string)$notificationXML->reference);
        $this->loadByTemporaryReference((string)$notificationXML->reference);

        //if order exists
        if ($orderId = $this->getOrderId()) {
            $order = Mage::getModel('sales/order')->load($orderId);
            $observer->getKioskNotification()->setOrderNo($order->getIncrementId());
            return;
        }

        //update kiosk order
        $this->setPagseguroEmail((string)$notificationXML->sender->email);
        $this->setTransactionCode((string)$notificationXML->code);

        $productIds = array($this->getProductId());

        $quote = Mage::getModel('sales/quote')->setStoreId($this->_store->getId())->setIsKiosk(true);
        $this->loadOrCreateCustomer($notificationXML);
        $customer = Mage::getModel('customer/customer')->load($this->getCustomerId());

        //Assign customer to a new quote
        $quote->assignCustomer($customer);

        $quote->setSendConfirmation(1);
        foreach ($productIds as $id)
        {
            $product = Mage::getModel('catalog/product')->load($id);
            $quote->addProduct($product, 1);
        }


        //assign addresses
        $quote->getBillingAddress()->importCustomerAddress($customer->getDefaultBillingAddress());
        $quote->getShippingAddress()->importCustomerAddress($customer->getDefaultShippingAddress());

        $quote->getPayment()->importData(array('method' => 'rm_pagseguro_kiosk'));

        $quote->collectTotals()->save();

        //Create order from quote
        $service  = Mage::getModel('sales/service_quote', $quote);
        $service->submitAll();
        $incrementId = $service->getOrder()->getRealOrderId();
        $this->setOrderId($service->getOrder()->getId());

        $observer->getKioskNotification()->setOrderNo($incrementId);
        $this->save();
    }

    /**
     * Create new customer or load an existing one, based on notificationXML result and typed email address
     * @param $notificationXML
     *
     * @return Mage_Customer_Model_Customer
     * @throws Exception
     */
    protected function loadOrCreateCustomer($notificationXML)
    {

        //try to load typed customer email
        $customer = Mage::getModel('customer/customer')
            ->setWebsiteId($this->_websiteId)
            ->loadByEmail($this->getEmail());
        if (!$customer->getId()) {
            $pHelper = Mage::helper('ricardomartins_pagseguro/params');
            $name = $pHelper->splitName($notificationXML->sender->name);

            /** @var Mage_Customer_Model_Customer $customer */
            $customer = Mage::getModel('customer/customer')
               ->setWebsite($this->_websiteId);
            $customer->setData(array(
               'firstname' => $name[0],
               'lastname' => $name[1],
               'email' => $this->getEmail(),
               'password' => uniqid(),
            ))->save();
            /** @var Mage_Customer_Model_Address $address */
            $address = Mage::getModel('customer/address');
            $address->setCustomerId($customer->getId())
                ->setFirstname($customer->getFirstname())
                ->setLastname($customer->getLastname())
                ->setCountryId('BR')
                ->setPostcode((string)$notificationXML->shipping->address->postalCode)
                ->setCity((string)$notificationXML->shipping->address->city)
                ->setTelephone((string)$notificationXML->sender->phone->areaCode . (string)$notificationXML->sender->phone->number)
                ->setStreet((string)$notificationXML->shipping->address->street)
                ->setRegion($pHelper->convertUFRegion((string)$notificationXML->shipping->address->state))
                ->setRegionId($pHelper->getRegionIdFromUF((string)$notificationXML->shipping->address->state))
                ->setIsDefaultBilling('1')
                ->setIsDefaultShipping('1')
                ->setSaveInAddressBook('1');
            $address->save();

            $customer->sendPasswordReminderEmail();
            $customer->setConfirmation(null);
            $customer->save();
        }
        $this->setCustomerId($customer->getId());
        return $customer;
    }

    public function loadByTemporaryReference($temporaryReference)
    {
        $collection = $this->getCollection()
            ->addFieldToFilter('temporary_reference', $temporaryReference);

        if ($firstItem = $collection->getFirstItem()) {
            $this->load($firstItem->getId());
            return $this;
        }
        return false;
    }

    public function loadByOrderId($orderId)
    {
        $collection = $this->getCollection()
            ->addFieldToFilter('order_id', $orderId);

        if ($firstItem = $collection->getFirstItem()) {
            $this->load($firstItem->getId());
            return $this;
        }
        return false;
    }
}