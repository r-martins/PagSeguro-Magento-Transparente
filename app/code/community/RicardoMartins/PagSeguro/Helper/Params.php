<?php
/**
 * PagSeguro Transparente Magento
 * Params Helper Class - responsible for formatting and grabbing parameters used on PagSeguro API calls
 *
 * @category    RicardoMartins
 * @package     RicardoMartins_PagSeguro
 * @author      Ricardo Martins
 * @copyright   Copyright (c) 2015 Ricardo Martins (http://r-martins.github.io/PagSeguro-Magento-Transparente/)
 * @license     https://opensource.org/licenses/MIT MIT License
 */
class RicardoMartins_PagSeguro_Helper_Params extends Mage_Core_Helper_Abstract
{

    /**
     * Return items information, to be send to API
     * @param Mage_Sales_Model_Order $order
     * @return array
     */
    public function getItemsParams(Mage_Sales_Model_Order $order)
    {
        $return = array();
        if ($items = $order->getAllVisibleItems()) {
            for ($x=1, $y=0, $c=count($items); $x <= $c; $x++, $y++) {
                $return['itemId'.$x] = $items[$y]->getId();
                $return['itemDescription'.$x] = substr($items[$y]->getName(), 0, 100);
                $return['itemAmount'.$x] = number_format($items[$y]->getPrice(), 2, '.', '');
                $return['itemQuantity'.$x] = $items[$y]->getQtyOrdered();
            }
        }
        return $return;
    }

    /**
     * Return an array with Sender(Customer) information to be used on API call
     *
     * @param Mage_Sales_Model_Order $order
     * @param $payment
     * @return array
     */
    public function getSenderParams(Mage_Sales_Model_Order $order, $payment)
    {
        $digits = new Zend_Filter_Digits();
        $cpf = $this->_getCustomerCpfValue($order, $payment);

        $phone = $this->_extractPhone($order->getBillingAddress()->getTelephone());


        $senderName = $this->removeDuplicatedSpaces(
            sprintf('%s %s', $order->getCustomerFirstname(), $order->getCustomerLastname())
        );

        $senderName = substr($senderName, 0, 50);

        $return = array(
            'senderName'    => $senderName,
            'senderEmail'   => trim($order->getCustomerEmail()),
            'senderHash'    => $payment['additional_information']['sender_hash'],
            'senderCPF'     => $digits->filter($cpf),
            'senderAreaCode'=> $phone['area'],
            'senderPhone'   => $phone['number'],
        );
        if (strlen($return['senderCPF']) > 11) {
            $return['senderCNPJ'] = $return['senderCPF'];
            unset($return['senderCPF']);
        }

        return $return;
    }

    /**
     * Returns an array with credit card's owner (Customer) to be used on API
     * @param Mage_Sales_Model_Order $order
     * @param $payment
     * @return array
     */
    public function getCreditCardHolderParams(Mage_Sales_Model_Order $order, $payment)
    {
        $digits = new Zend_Filter_Digits();

        $cpf = $this->_getCustomerCpfValue($order, $payment);

        //data
        $creditCardHolderBirthDate = $this->_getCustomerCcDobValue($order->getCustomer(), $payment);
        $phone = $this->_extractPhone($order->getBillingAddress()->getTelephone());


        $holderName = $this->removeDuplicatedSpaces($payment['additional_information']['credit_card_owner']);
        $return = array(
            'creditCardHolderName'      => $holderName,
            'creditCardHolderBirthDate' => $creditCardHolderBirthDate,
            'creditCardHolderCPF'       => $digits->filter($cpf),
            'creditCardHolderAreaCode'  => $phone['area'],
            'creditCardHolderPhone'     => $phone['number'],
        );

        return $return;
    }

    /**
     * Return an array with installment information to be used with API
     * @param Mage_Sales_Model_Order $order
     * @param $payment Mage_Sales_Model_Order_Payment
     * @return array
     */
    public function getCreditCardInstallmentsParams(Mage_Sales_Model_Order $order, $payment)
    {
        $return = array();
        if ($payment->getAdditionalInformation('installment_quantity')
            && $payment->getAdditionalInformation('installment_value')) {
            $return = array(
                'installmentQuantity'   => $payment->getAdditionalInformation('installment_quantity'),
                'installmentValue'      => number_format(
                    $payment->getAdditionalInformation('installment_value'), 2, '.', ''
                ),
            );
        } else {
            $return = array(
                'installmentQuantity'   => '1',
                'installmentValue'      => number_format($order->getGrandTotal(), 2, '.', ''),
            );
        }
        return $return;
    }


    /**
     * Return an array with address (shipping/billing) information to be used on API
     * @param Mage_Sales_Model_Order $order
     * @param string (billing|shipping) $type
     * @return array
     */
    public function getAddressParams(Mage_Sales_Model_Order $order, $type)
    {
        $digits = new Zend_Filter_Digits();

        //address attributes
        /** @var Mage_Sales_Model_Order_Address $address */
        $address = ($type=='shipping' && !$order->getIsVirtual()) ?
            $order->getShippingAddress() : $order->getBillingAddress();
        $addressStreetAttribute = Mage::getStoreConfig('payment/pagseguro/address_street_attribute');
        $addressNumberAttribute = Mage::getStoreConfig('payment/pagseguro/address_number_attribute');
        $addressComplementAttribute = Mage::getStoreConfig('payment/pagseguro/address_complement_attribute');
        $addressNeighborhoodAttribute = Mage::getStoreConfig('payment/pagseguro/address_neighborhood_attribute');

        //gathering address data
        $addressStreet = $this->_getAddressAttributeValue($address, $addressStreetAttribute);
        $addressNumber = $this->_getAddressAttributeValue($address, $addressNumberAttribute);
        $addressComplement = $this->_getAddressAttributeValue($address, $addressComplementAttribute);
        $addressDistrict = $this->_getAddressAttributeValue($address, $addressNeighborhoodAttribute);
        $addressPostalCode = $digits->filter($address->getPostcode());
        $addressCity = $address->getCity();
        $addressState = $this->getStateCode($address->getRegion());


        $return = array(
            $type.'AddressStreet'     => substr($addressStreet, 0, 80),
            $type.'AddressNumber'     => substr($addressNumber, 0, 20),
            $type.'AddressComplement' => substr($addressComplement, 0, 40),
            $type.'AddressDistrict'   => substr($addressDistrict, 0, 60),
            $type.'AddressPostalCode' => $addressPostalCode,
            $type.'AddressCity'       => substr($addressCity, 0, 60),
            $type.'AddressState'      => $addressState,
            $type.'AddressCountry'    => 'BRA',
         );

        //shipping specific
        if ($type == 'shipping') {
            $shippingType = $this->_getShippingType($order);
            $shippingCost = $order->getShippingAmount();
            $return['shippingType'] = $shippingType;
            if ($shippingCost > 0) {
                if ($this->_shouldSplit($order)) {
                    $shippingCost -= 0.01;
                }
                $return['shippingCost'] = number_format($shippingCost, 2, '.', '');
            }
        }
        return $return;
    }

    /**
     * Get BR State code even if it was typed manually
     * @param $state
     *
     * @return string
     */
    public function getStateCode($state)
    {
        if(strlen($state) == 2 && is_string($state))
        {
            return mb_convert_case($state,MB_CASE_UPPER);
        }
        else if(strlen($state) > 2 && is_string($state))
        {
            $state = $this->normalizeChars($state);
            $state = trim($state);
            $state = $this->stripAccents($state);
            $state = mb_convert_case($state, MB_CASE_UPPER);
            $codes = array(
                'AC'=>'ACRE',
                'AL'=>'ALAGOAS',
                'AM'=>'AMAZONAS',
                'AP'=>'AMAPA',
                'BA'=>'BAHIA',
                'CE'=>'CEARA',
                'DF'=>'DISTRITO FEDERAL',
                'ES'=>'ESPIRITO SANTO',
                'GO'=>'GOIAS',
                'MA'=>'MARANHAO',
                'MT'=>'MATO GROSSO',
                'MS'=>'MATO GROSSO DO SUL',
                'MG'=>'MINAS GERAIS',
                'PA'=>'PARA',
                'PB'=>'PARAIBA',
                'PR'=>'PARANA',
                'PE'=>'PERNAMBUCO',
                'PI'=>'PIAUI',
                'RJ'=>'RIO DE JANEIRO',
                'RN'=>'RIO GRANDE DO NORTE',
                'RO'=>'RONDONIA',
                'RS'=>'RIO GRANDE DO SUL',
                'RR'=>'RORAIMA',
                'SC'=>'SANTA CATARINA',
                'SE'=>'SERGIPE',
                'SP'=>'SAO PAULO',
                'TO'=>'TOCANTINS'
            );
            if ($code = array_search($state, $codes)) {
                return $code;
            }
        }
        return $state;
    }

    /**
     * Replace language-specific characters by ASCII-equivalents.
     * @see http://stackoverflow.com/a/16427125/529403
     * @param string $s
     * @return string
     */
    public static function normalizeChars($s)
    {
        $replace = array(
            'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE', 'È' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ñ' => 'N', 'Ò' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y',
            'ä' => 'a', 'ã' => 'a', 'á' => 'a', 'à' => 'a', 'å' => 'a', 'æ' => 'ae', 'è' => 'e', 'ë' => 'e', 'ì' => 'i',
            'í' => 'i', 'î' => 'i', 'ï' => 'i', 'Ã' => 'A', 'Õ' => 'O',
            'ñ' => 'n', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'ø' => 'o', 'ù' => 'ú', 'û' => 'u', 'ü' => 'ý', 'ÿ' => 'y',
            'Œ' => 'OE', 'œ' => 'oe', 'Š' => 'š', 'Ÿ' => 'Y', 'ƒ' => 'f', 'Ğ'=>'G', 'ğ'=>'g', 'Š'=>'S',
            'š'=>'s', 'Ş'=>'S', 'ș'=>'s', 'Ș'=>'S', 'ş'=>'s', 'ț'=>'t', 'Ț'=>'T', 'ÿ'=>'y', 'Ž'=>'Z', 'ž'=>'z'
        );
        return preg_replace('/[^0-9A-Za-zÃÁÀÂÇÉÊÍÕÓÔÚÜãáàâçéêíõóôúü.\-\/ ]/u', '', strtr($s, $replace));
    }

    /**
     * Replace accented characters
     * @param $string
     *
     * @return string
     */
    public function stripAccents($string)
    {
        return preg_replace('/[`^~\'"]/', null, iconv('UTF-8', 'ASCII//TRANSLIT', $string));
    }

    /**
     * Calculates the "Exta" value that corresponds to Tax values minus Discount given
     * It makes the correct discount to be shown correctly on PagSeguro
     * @param Mage_Sales_Model_Order $order
     *
     * @return float
     */
    public function getExtraAmount($order)
    {
        $discount = $order->getDiscountAmount();
        $taxAmount = $order->getTaxAmount();
        $extra = $discount + $taxAmount;

        if ($this->_shouldSplit($order)) {
            $extra = $extra+0.01;
        }
        return number_format($extra, 2, '.', '');
    }

    /**
     * Remove duplicated spaces from string
     * @param $string
     * @return string
     */
    public function removeDuplicatedSpaces($string)
    {
        $string = $this->normalizeChars($string);

        return preg_replace('/\s+/', ' ', trim($string));
    }

    /**
     * Retrieve array of available years
     *
     * @return array
     */
    public function getYears()
    {
        $years = array();
        $first = date("Y");

        for ($index=0; $index <= 20; $index++) {
            $year = $first + $index;
            $years[$year] = $year;
        }
        return $years;
    }

    /**
     * Extracts phone area code and returns phone number, with area code as key of the returned array
     * @author Ricardo Martins <ricardo@ricardomartins.net.br>
     * @param string $phone
     * @return array
     */
    private function _extractPhone($phone)
    {
        $digits = new Zend_Filter_Digits();
        $phone = $digits->filter($phone);
        //se começar com zero, pula o primeiro digito
        if (substr($phone, 0, 1) == '0') {
            $phone = substr($phone, 1, strlen($phone));
        }
        $originalPhone = $phone;

        $phone = preg_replace('/^(\d{2})(\d{7,9})$/', '$1-$2', $phone);

        if (is_array($phone) && count($phone) == 2) {
            list($area, $number) = explode('-', $phone);
            return array(
                'area' => $area,
                'number'=>$number
            );
        }

        return array(
            'area' => (string)substr($originalPhone, 0, 2),
            'number'=> (string)substr($originalPhone, 2, 9),
        );
    }

    /**
     * Return shipping code based on PagSeguro Documentation
     * 1 – PAC, 2 – SEDEX, 3 - Desconhecido
     * @param Mage_Sales_Model_Order $order
     *
     * @return string
     */
    private function _getShippingType(Mage_Sales_Model_Order $order)
    {
        $method =  strtolower($order->getShippingMethod());
        if (strstr($method, 'pac') !== false) {
            return '1';
        } else if (strstr($method, 'sedex') !== false) {
            return '2';
        }
        return '3';
    }

    /**
     * Gets the shipping attribute based on one of the id's from
     * RicardoMartins_PagSeguro_Model_Source_Customer_Address_*
     *
     * @param Mage_Sales_Model_Order_Address $address
     * @param string $attributeId
     *
     * @return string
     */
    private function _getAddressAttributeValue($address, $attributeId)
    {
        $isStreetline = preg_match('/^street_(\d{1})$/', $attributeId, $matches);

        if ($isStreetline !== false && isset($matches[1])) { //uses streetlines
            return $address->getStreet(intval($matches[1]));
        } else if ($attributeId == '') { //do not tell pagseguro
            return '';
        }
        return (string)$address->getData($attributeId);
    }

    /**
     * Returns customer's date of birthday, based on your module configuration
     * @param Mage_Customer_Model_Customer $customer
     * @param                              $payment
     *
     * @return mixed
     */
    private function _getCustomerCcDobValue(Mage_Customer_Model_Customer $customer, $payment)
    {
        $ccDobAttribute = Mage::getStoreConfig('payment/pagseguro_cc/owner_dob_attribute');

        if (empty($ccDobAttribute)) { //when asked with payment data
            if (isset($payment['additional_information']['credit_card_owner_birthdate'])) {
                return $payment['additional_information']['credit_card_owner_birthdate'];
            }
        }

        $dob = $customer->getResource()->getAttribute($ccDobAttribute)->getFrontend()->getValue($customer);


        return date('d/m/Y', strtotime($dob));
    }

    /**
     * Returns customer's CPF based on your module configuration
     * @param Mage_Sales_Model_Order $order
     * @param Mage_Payment_Model_Method_Abstract $payment
     *
     * @return mixed
     */
    private function _getCustomerCpfValue(Mage_Sales_Model_Order $order, $payment)
    {
        $customerCpfAttribute = Mage::getStoreConfig('payment/pagseguro/customer_cpf_attribute');

        if (empty($customerCpfAttribute)) { //Asked with payment data
            if (isset($payment['additional_information'][$payment->getMethod() . '_cpf'])) {
                return $payment['additional_information'][$payment->getMethod() . '_cpf'];
            }
        }
        $entity = explode('|', $customerCpfAttribute);
        $cpf = '';
        if (count($entity) == 1 || $entity[0] == 'customer') {
            if (count($entity) == 2) {
                $customerCpfAttribute = $entity[1];
            }
            $customer = $order->getCustomer();

            $cpf = $customer->getData($customerCpfAttribute);
        } else if (count($entity) == 2 && $entity[0] == 'billing' ) { //billing
            $cpf = $order->getShippingAddress()->getData($entity[1]);
        }

        if ($order->getCustomerIsGuest() && empty($cpf)) {
            $cpf = $order->getData('customer_' . $customerCpfAttribute);
        }

        $cpfObj = new Varien_Object(array('cpf'=>$cpf));

        //you can create a module to get customer's CPF from somewhere else
        Mage::dispatchEvent(
            'ricardomartins_pagseguro_return_cpf_before',
            array(
                'order' => $order,
                'payment' => $payment,
                'cpf_obj' => $cpfObj,
                )
        );

        return $cpfObj->getCpf();
    }


    /**
     * Should split shipping? If grand total is equal to discount total.
     * PagSeguro needs to receive product values > R$0,00, even if you need to invoice only shipping
     * and would like to give producs for free.
     * In these cases, splitting will add R$0,01 for each product, reducing R$0,01 from shipping total.
     *
     * @param $order
     *
     * @return bool
     */
    private function _shouldSplit($order)
    {
        $discount = $order->getDiscountAmount();
        $taxAmount = $order->getTaxAmount();
        $extraAmount = $discount + $taxAmount;

        $totalAmount = 0;
        foreach ($order->getAllVisibleItems() as $item) {
            $totalAmount += $item->getRowTotal();
        }
        return (abs($extraAmount) == $totalAmount);
    }
}
