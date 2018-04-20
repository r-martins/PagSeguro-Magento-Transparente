<?php
/**
 * Class RicardoMartins_PagSeguro_KioskController
 *
 * @author    Ricardo Martins <ricardo@magenteiro.com>
 */
class RicardoMartins_PagSeguro_KioskController extends Mage_Core_Controller_Front_Action
{
    protected $_websiteId;

    /**
     * @return Mage_Core_Controller_Front_Action
     */
    public function preDispatch()
    {
        $this->_websiteId = Mage::app()->getWebsite()->getId();
        return parent::preDispatch();
    }

    /**
     * Create standalone/kiosk order and redirects to PagSeguro
     */
    public function createOrderAction()
    {
        $kHelper = Mage::helper('ricardomartins_pagseguro/kiosk');
        $pHelper = Mage::helper('ricardomartins_pagseguro');
        if (!$kHelper->isActive()) {
            return $this->redirectException($kHelper->__('Kiosk is not active.'));
        }

        $productSku = $this->getRequest()->getParam('sku');
        $product = Mage::getModel('catalog/product')->loadByAttribute('sku', $productSku);
        $temporaryOrder = new Varien_Object();
        $temporaryReference = uniqid('kiosk_');


        if (!$product)
            return $this->norouteAction();

        $temporaryOrder->setProductId($product->getId());
        $temporaryOrder->setTemporaryReference($temporaryReference);


        $email = $this->getRequest()->getParam('email', false);

        /** @var Mage_Customer_Model_Customer $customer */
        $customer = Mage::getModel('customer/customer')
            ->setWebsiteId($this->_websiteId)
            ->loadByEmail($email);

        $temporaryOrder->setEmail($email);

        if ($customer->getId()) {
            $temporaryOrder->setName($customer->getFirstname() . ' ' . $customer->getLastname());
            $temporaryOrder->setCustomerId($customer->getId());
        }


        $successUrl = Mage::getUrl('pseguro/kiosk/success', array('_secure'=> true));
        $redirectAfter = $this->getRequest()->getParam('redirectUrl');
        $successUrl = Mage::helper('core/url')->addRequestParam($successUrl, array(
            'temporaryReference' => $temporaryReference
        ));

        if($redirectAfter){
            $redirectAfter = Mage::helper('core/url')->addRequestParam($redirectAfter, array(
                'temporaryReference' => $temporaryReference
            ));
        }

        $params = array(
            'reference' => $temporaryReference,
            'currency' => 'BRL',
            'itemId1' => $product->getId(),
            'itemQuantity1' => (int)$this->getRequest()->getParam('qty', 1),
            'itemDescription1' => substr($product->getName(), 0, 100),
            'itemAmount1' => number_format($product->getFinalPrice(), 2, '.', ''),
            'shippingAddressRequired' => false,
            'redirectURL' => $successUrl,
            'notificationURL' => Mage::getUrl('ricardomartins_pagseguro/notification'),
            'email' => $pHelper->getMerchantEmail(),
            'token' => $pHelper->getToken()
        );

        try{
            $checkout = Mage::getModel('ricardomartins_pagseguro/abstract')->callApi($params, null, 'checkout');
        }catch (Exception $e) {
            return $this->redirectException($e->getMessage());
        }

        if(!isset($checkout->code)){
            $this->redirectException('Falha ao obter código de checkout.');
        }

        $checkoutCode = (string)$checkout->code;
        $temporaryOrder->setCheckoutCode($checkoutCode);
        $temporaryOrder->setRedirectAfter($redirectAfter);

        Mage::getModel('ricardomartins_pagseguro/kiosk')
            ->setData($temporaryOrder->toArray())
            ->save();


        $this->_redirectUrl('https://pagseguro.uol.com.br/v2/checkout/payment.html?code=' . $checkoutCode);

    }

    /**
     * @return Mage_Core_Controller_Varien_Action
     */
    public function successAction()
    {
        $kHelper = Mage::helper('ricardomartins_pagseguro/kiosk');
        $temporaryReference = $this->getRequest()->getParam('temporaryReference');
        $kiosk = Mage::getModel('ricardomartins_pagseguro/kiosk')->loadByTemporaryReference($temporaryReference);
        if(!$kiosk->getId()){
            return $this->redirectException($kHelper->__('Temporary order reference is missing or was not found.'));
        }

        Mage::getSingleton('customer/session')->addData(array(
           'kiosk_order' => $kiosk
        ));

        if ($url = $kiosk->getRedirectAfter()) {
            return $this->_redirectUrl($url);
        }

        $this->loadLayout();
        $this->renderLayout();
    }
    /**
     * @param $errorMsg
     * @return Mage_Core_Controller_Varien_Action
     */
    protected function redirectException($errorMsg)
    {
        Mage::getSingleton('core/session')->addError($errorMsg);
        return $this->_redirect('/');
    }
}