<?php

class RicardoMartins_PagSeguro_Model_Payment_Notification
{
    /**
     * @var SimpleXMLDocument
     */
    private $_xmlDocument;

    /**
     * @var RicardoMartins_PagSeguro_Helper_Data
     */
    private $_helper;

    /**
     * @var Mage_Sales_Model_Order
     */
    private $_order;


    public function __construct($params)
    {
        if(!isset($params["document"]))
        {
            throw new Exception("Parâmetro 'document' é obrigatório.");
        }

        if(is_string($params["document"]))
        {
            try
            {
                $params["document"] = simplexml_load_string($params["document"]);
            }
            catch(Exception $e)
            {
                throw new Exception("Não foi possível interpretar a resposta da PagSeguro.");
            }
        }

        $this->_xmlDocument = $params["document"];
        $this->_helper = Mage::helper("ricardomartins_pagseguro");;
    }

    public function getDocument()
    {
        return $this->_xmlDocument;
    }

    /**
     * Retrieves the reference of the transaction
     * 
     * @return string
     */
    public function getReference()
    {
        $nodes = $this->_getNodes("reference");

        return count($nodes) == 1 ? (string) $nodes[0] : "";
    }

    /**
     * Updates the value of the reference of the transaction
     * @param String value
     * 
     * @return RicardoMartins_PagSeguro_Model_Payment_Notification
     */
    private function _setReference($value)
    {
        $nodes = $this->_getNodes("reference");
        $nodes[0][0] = $value;

        return $this;
    }

    /**
     * Retrieves the transaction ID (code) of the transaction
     * 
     * @return string
     */
    public function getTransactionId()
    {
        $nodes = $this->_getNodes("code");

        return count($nodes) == 1 ? (string) $nodes[0] : "";
    }

    /**
     * Retrieves the transaction status
     * 
     * @return string
     */
    public function getStatus()
    {
        $nodes = $this->_getNodes("status");

        return count($nodes) == 1 ? (int) $nodes[0] : "";
    }

    /**
     * Retrieves the last event date
     * 
     * @return string
     */
    public function getLastEventDate()
    {
        $nodes = $this->_getNodes("lastEventDate");

        return count($nodes) == 1 ? (int) $nodes[0] : "";
    }

    /**
     * Verifies if there are errors tags in the document
     * 
     * @return boolean
     */
    public function hasErrors()
    {
        return count($this->_getErrorsNodes()) > 0;
    }

    /**
     * Verifies if there are errors related to installments value
     * 
     * @return boolean
     */
    public function hasInstallmentsError()
    {
        foreach($this->_getErrorsNodes() as $errorNode)
        {
            if("53041" == ((string) $errorNode->code))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Retrieves the description of all encoutred errors
     * 
     * @return string
     */
    public function getErrorsDescription()
    {
        $errorsDescription = array();

        foreach($this->_getErrorsNodes() as $errorNode)
        {
            $errorsDescription[] = $this->_helper->__((string) $errorNode->message) . " (" . ((string) $errorNode->code) . ")";
        }

        return "Um ou mais erros ocorreram no seu pagamento." . PHP_EOL . implode(PHP_EOL, $errorsDescription);
    }

    /**
     * Retrieves the total amount of the transaction
     * 
     * @return string
     */
    public function getGrossAmount()
    {
        $nodes = $this->_getNodes("grossAmount");

        return count($nodes) == 1 ? (float) $nodes[0] : "";
    }

    /**
     * Retrieves the authorization code
     * 
     * @return string
     */
    public function getAuthorizationCode()
    {
        $nodes = $this->_getNodes("gatewaySystem/authorizationCode");

        return count($nodes) == 1 ? (float) $nodes[0] : "";
    }

    /**
     * Retrieves the NSU
     * 
     * @return string
     */
    public function getNsu()
    {
        $nodes = $this->_getNodes("gatewaySystem/nsu");

        return count($nodes) == 1 ? (float) $nodes[0] : "";
    }

    /**
     * Retrieves the TID
     * 
     * @return string
     */
    public function getTid()
    {
        $nodes = $this->_getNodes("gatewaySystem/tid");

        return count($nodes) == 1 ? (float) $nodes[0] : "";
    }

    /**
     * Retrieves the cancellation source
     * 
     * @return string
     */
    public function getCancellationSource()
    {
        $nodes = $this->_getNodes("cancellationSource");

        return count($nodes) == 1 ? (string) $nodes[0] : "";
    }

    /**
     * Retrieves the fee amount
     * 
     * @return string
     */
    public function getFeeAmount()
    {
        $nodes = $this->_getNodes("feeAmount");

        return count($nodes) == 1 ? (string) $nodes[0] : "";
    }

    /**
     * Retrieves the net amount
     * 
     * @return string
     */
    public function getNetAmount()
    {
        $nodes = $this->_getNodes("netAmount");

        return count($nodes) == 1 ? (string) $nodes[0] : "";
    }

    /**
     * Retrieves the cancellation source description
     * 
     * @return string
     */
    public function getCancellationSourceDescription()
    {
        switch($this->getCancellationSource())
        {
            case 'INTERNAL':
                return ' O próprio PagSeguro negou ou cancelou a transação.';

            case 'EXTERNAL':
                return ' A transação foi negada ou cancelada pela instituição bancária.';
        }

        return "";
    }

    /**
     * Searches nodes for tags path
     * @param string $path
     * 
     * @return array
     */
    private function _getErrorsNodes()
    {
        return array_merge($this->_getNodes("errors"), $this->_getNodes("error"));
    }

    /**
     * Searches nodes for tags path
     * @param string $path
     * 
     * @return array
     */
    private function _getNodes($path)
    {
        if(!$this->_xmlDocument)
        {
            return "";
        }

        return $this->_xmlDocument->xpath($path);
    }

    /**
     * Loads Magento order by transaction reference (increment ID 
     * concatened with the card suffix)
     * 
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        if(!$this->_order)
        {
            if(!$this->getReference())
            {
                Mage::throwException("Could not load order related to payment notification. Reference not found.");
            }

            // if its a payment link (kiosk) order notification, 
            // triggers the event that creates the order
            if($this->_isKioskOrderNotification())
            {
                $this->_triggerKioskOrderCreation();
            }

            $incrementId = $this->getReference();

            // if its a multi credit card order notification,
            // ajust the order increment ID
            if($this->_isMultiCcOrderNotification())
            {
                $incrementId = substr($incrementId, 0, strlen($incrementId) - 4);
            }

            $this->_order = Mage::getModel('sales/order')->loadByIncrementId($incrementId);

            if(!$this->_order || !$this->_order->getId())
            {
                Mage::throwException(sprintf("Could not load order related to payment notification (Increment ID %s).", $incrementId));
            }
        }

        return $this->_order;
    }

    /**
     * Checks if the notification is related to a payment
     * link (kiosk) notification
     * @return Boolean
     */
    private function _isKioskOrderNotification()
    {
        return strstr($this->getReference(), 'kiosk_') !== false;
    }

    /**
     * Checks if the notification is related to a multi
     * credit card order notification
     * @return Boolean
     */
    private function _isMultiCcOrderNotification()
    {
        $referenceSuffix = strlen($this->getReference()) > 4
                            ? substr($this->getReference(), strlen($this->getReference()) - 4)
                            : "";

        return $referenceSuffix == "-cc1" || $referenceSuffix == "-cc2";
    }

    /**
     * Triggers the event of order creation for payment links (kiosk mode)
     */
    private function _triggerKioskOrderCreation()
    {
        $kioskNotification = new Varien_Object();
        $kioskNotification->setOrderNo($this->getReference());
        $kioskNotification->setNotificationXml($this->getDocument());
        Mage::dispatchEvent
        (
            'ricardomartins_pagseguro_kioskorder_notification_received',
            array('kiosk_notification' => $kioskNotification)
        );

        // updates notification reference
        $this->_setReference($kioskNotification->getOrderNo());
    }

    /**
     * Retrieves the XML document as a string
     */
    public function returnXMLasString()
    {
        if(is_object($this->_xmlDocument) && method_exists($this->_xmlDocument, "asXML"))
        {
            return $this->_xmlDocument->asXML();
        }
        
        return strval($this->_xmlDocument);
    }
    
    public function getGatewayData()
    {
        $gData = $this->_getNodes('gatewaySystem');
        if ($gData && $gData[0]) {
            $data = array();
            foreach ($gData[0] as $k => $v) {
                $data[$k] = (string) $v;
            }
            return $data;
        }
        
        return false;
    }

    /**
     * Returns the Credit card index or 1 is this is not a multi-cc notification (based on reference number)
     * @return int
     */
    public function getCcIdx()
    {
        $reference = $this->getReference();
        $ccStrPos = stripos($reference, '-cc');
        if (!$ccStrPos) {
            return 1;
        }
        
        return (int)substr($reference, -1);
    }
    
    public function getPaymentMethodType()
    {
        return isset($this->_xmlDocument->paymentMethod->type) ? $this->_xmlDocument->paymentMethod->type : null;
    }
}