<?php
/**
 * Class RicardoMartins_PagSeguro_Helper_Params
 * Classe para auxiliar na montagem dos parametros de chamadas da api. Trata telefones, itens, dados do cliente e afins.
 *
 * @author    Ricardo Martins <ricardo@ricardomartins.net.br>
 */
class RicardoMartins_PagSeguro_Helper_Params extends Mage_Core_Helper_Abstract
{

    /**
     * Retorna um array com informações dos itens para ser enviado pra API
     * @param Mage_Sales_Model_Order $order
     * @return array
     */
    public function getItemsParams(Mage_Sales_Model_Order $order)
    {
        $retorno = array();
        if($items = $order->getAllVisibleItems())
        {
            for($x=1, $y=0, $c=count($items); $x <= $c; $x++, $y++)
            {
                $retorno['itemId'.$x] = $items[$y]->getId();
                $retorno['itemDescription'.$x] = $items[$y]->getName();
                $retorno['itemAmount'.$x] = number_format($items[$y]->getPrice(),2,'.','');
                $retorno['itemQuantity'.$x] = $items[$y]->getQtyOrdered();
            }
        }
        return $retorno;
    }

    /**
     * Retorna um array com informações do Sender(Cliente) para ser enviado pra API
     * @param Mage_Sales_Model_Order $order
     * @param $payment
     * @return array
     */
    public function getSenderParams(Mage_Sales_Model_Order $order, $payment)
    {
        $digits = new Zend_Filter_Digits();

        /** @var Mage_Customer_Model_Customer $customer */
        $customer = Mage::getModel('customer/customer')->load($order->getCustomerId());
        $customer_cpf_attribute = Mage::getStoreConfig('payment/pagseguro/customer_cpf_attribute');
        $cpf = $customer->getResource()->getAttribute($customer_cpf_attribute)->getFrontend()->getValue($customer);

        //telefone
        $phone = $this->_extractPhone($order->getBillingAddress()->getTelephone());


        $retorno = array(
            'senderName'    =>  sprintf('%s %s',$order->getCustomerFirstname(), $order->getCustomerLastname()),
            'senderEmail'   => $order->getCustomerEmail(),
            'senderHash'    => $payment['additional_information']['sender_hash'],
            'senderCPF'     => $digits->filter($cpf),
            'senderAreaCode'=> $phone['area'],
            'senderPhone'   => $phone['number'],
        );

        return $retorno;
    }

    /**
     * Retorna um array com informações do dono do Cartao(Cliente) para ser enviado pra API
     * @param Mage_Sales_Model_Order $order
     * @param $payment
     * @return array
     */
    public function getCreditCardHolderParams(Mage_Sales_Model_Order $order, $payment)
    {
        $digits = new Zend_Filter_Digits();

        /** @var Mage_Customer_Model_Customer $customer */
        $customer = Mage::getModel('customer/customer')->load($order->getCustomerId());
        $customer_cpf_attribute = Mage::getStoreConfig('payment/pagseguro/customer_cpf_attribute');
        $cpf = $customer->getResource()->getAttribute($customer_cpf_attribute)->getFrontend()->getValue($customer);


        $cpf = $customer->getResource()->getAttribute($customer_cpf_attribute)->getFrontend()->getValue($customer);

        //dados
        $creditCardHolderBirthDate = $this->_getCustomerCcDobValue($customer,$payment);
        $phone = $this->_extractPhone($order->getBillingAddress()->getTelephone());


        $retorno = array(
            'creditCardHolderName'      =>  sprintf('%s %s',$order->getCustomerFirstname(), $order->getCustomerLastname()),
            'creditCardHolderBirthDate' => $creditCardHolderBirthDate,
            'creditCardHolderCPF'       => $digits->filter($cpf),
            'creditCardHolderAreaCode'  => $phone['area'],
            'creditCardHolderPhone'     => $phone['number'],
        );

        return $retorno;
    }

    /**
     * Retorna um array com informações de parcelamento (Cartao) para ser enviado pra API
     * @param Mage_Sales_Model_Order $order
     * @param $payment
     * @return array
     */
    public function getCreditCardInstallmentsParams(Mage_Sales_Model_Order $order, $payment)
    {
        //@TODO Criar parcelamentos variáveis
        $retorno = array(
            'installmentQuantity'   => '1',
            'installmentValue'      => number_format($order->getGrandTotal(),2,'.',''),
        );
        return $retorno;
    }


    /**
     * Retorna um array com informações do endereço de entrega/cobranca para ser enviado pra API
     * @param Mage_Sales_Model_Order $order
     * @param string (billing|shipping) $type
     * @return array
     */
    public function getAddressParams(Mage_Sales_Model_Order $order, $type)
    {
        $digits = new Zend_Filter_Digits();

        //atributos de endereço
        /** @var Mage_Sales_Model_Order_Address $address */
        $address = $type=='shipping' ? $order->getShippingAddress() : $order->getBillingAddress();
        $address_street_attribute = Mage::getStoreConfig('payment/pagseguro/address_street_attribute');
        $address_number_attribute = Mage::getStoreConfig('payment/pagseguro/address_number_attribute');
        $address_complement_attribute = Mage::getStoreConfig('payment/pagseguro/address_complement_attribute');
        $address_neighborhood_attribute = Mage::getStoreConfig('payment/pagseguro/address_neighborhood_attribute');

        //obtendo dados de endereço
        $addressStreet = $this->_getAddressAttributeValue($address,$address_street_attribute);
        $addressNumber = $this->_getAddressAttributeValue($address,$address_number_attribute);
        $addressComplement = $this->_getAddressAttributeValue($address,$address_complement_attribute);
        $addressDistrict = $this->_getAddressAttributeValue($address,$address_neighborhood_attribute);
        $addressPostalCode = $address->getPostcode();
        $addressCity = $address->getCity();
        $addressState = $address->getRegion();


        $retorno = array(
            $type.'AddressStreet'      =>  $addressStreet,
            $type.'AddressNumber'     => $addressNumber,
            $type.'AddressComplement' => $addressComplement,
            $type.'AddressDistrict'   => $addressDistrict,
            $type.'AddressPostalCode' => $addressPostalCode,
            $type.'AddressCity'       => $addressCity,
            $type.'AddressState'      => $addressState,
            $type.'AddressCountry'    => 'BRA',
         );

        //específico pra shipping
        if($type == 'shipping')
        {
            $shippingType = $this->_getShippingType($order);
            $shippingCost = $order->getShippingAmount();
            $retorno['shippingType'] = $shippingType;
            if($shippingCost > 0)
            {
                $retorno['shippingCost'] = number_format($shippingCost,2,'.','');
            }
        }
        return $retorno;
    }


    /**
     * Extraí codigo de area e telefone e devolve array com area e number como chave
     * @author Ricardo Martins <ricardo@ricardomartins.net.br>
     * @param string $phone
     * @return array
     */
    private function _extractPhone($phone)
    {
        $digits = new Zend_Filter_Digits();
        $phone = $digits->filter($phone);
        $original_phone = $phone;

        $phone = preg_replace('/^(\d{2})(\d{7,9})$/','$1-$2',$phone);
        if(is_array($phone) && count($phone) == 2)
        {
            list($area,$number) = explode('-',$phone);
            return array(
                'area' => $area,
                'number'=>$number
            );
        }

        return array(
            'area' => (string)substr($original_phone,0,2),
            'number'=> (string)substr($original_phone,2,9),
        );
    }

    /**
     * Retorna a forma de envio do produto
     * 1 – PAC, 2 – SEDEX, 3 - Desconhecido
     * @param Mage_Sales_Model_Order $order
     *
     * @return string
     */
    private function _getShippingType(Mage_Sales_Model_Order $order)
    {
        $method =  strtolower($order->getShippingMethod());
        if(strstr($method,'pac') !== false){
            return '1';
        }else if(strstr($method,'sedex') !== false)
        {
            return '2';
        }
        return '3';
    }

    /**
     * Pega um atributo de endereço baseado em um dos Id's vindos de RicardoMartins_PagSeguro_Model_Source_Customer_Address_*
     * @param Mage_Sales_Model_Order_Address $address
     * @param string $attribute_id
     */
    private function _getAddressAttributeValue($address, $attribute_id)
    {
        $is_streetline = preg_match('/^street_(\d{1})$/', $attribute_id, $matches);

        if($is_streetline !== false && isset($matches[1])) //usa Streetlines
        {
            return $address->getStreet(intval($matches[1]));
        }
        else if($attribute_id == '') //Nao informar ao pagseguro
        {
            return '';
        }
        return (string)$address->getData($attribute_id);
    }

    /**
     * Retorna a Data de Nascimento do cliente baseado na selecao realizada na configuração do Cartao de credito do modulo
     * @param Mage_Customer_Model_Customer $customer
     * @param                              $payment
     *
     * @return mixed
     */
    private function _getCustomerCcDobValue(Mage_Customer_Model_Customer $customer, $payment)
    {
        $cc_dob_attribute = Mage::getStoreConfig('payment/pagseguro_cc/owner_dob_attribute');

        if(empty($cc_dob_attribute)) //Soliciado ao cliente junto com os dados do cartao
        {
            //@TODO Implementar
        }

        $dob = $customer->getResource()->getAttribute($cc_dob_attribute)->getFrontend()->getValue($customer);


        return date('d/m/Y', strtotime($dob));
    }
}