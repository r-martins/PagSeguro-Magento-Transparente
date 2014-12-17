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
                $retorno['itemDescription'.$x] = substr($items[$y]->getName(), 0, 100);
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
        $cpf = $this->_getCustomerCpfValue($order,$payment);

        //telefone
        $phone = $this->_extractPhone($order->getBillingAddress()->getTelephone());


        $retorno = array(
            'senderName'    =>  sprintf('%s %s',trim($order->getCustomerFirstname()), trim($order->getCustomerLastname())),
            'senderEmail'   => trim($order->getCustomerEmail()),
            'senderHash'    => $payment['additional_information']['sender_hash'],
            'senderCPF'     => $digits->filter($cpf),
            'senderAreaCode'=> $phone['area'],
            'senderPhone'   => $phone['number'],
        );
        if(strlen($retorno['senderCPF']) > 11){
            $retorno['senderCNPJ'] = $retorno['senderCPF'];
            unset($retorno['senderCPF']);
        }

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

        $cpf = $this->_getCustomerCpfValue($order,$payment);


        //dados
        $creditCardHolderBirthDate = $this->_getCustomerCcDobValue($order->getCustomer(),$payment);
        $phone = $this->_extractPhone($order->getBillingAddress()->getTelephone());


        $retorno = array(
            'creditCardHolderName'      =>  $payment['additional_information']['credit_card_owner'],
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
     * @param $payment Mage_Sales_Model_Order_Payment
     * @return array
     */
    public function getCreditCardInstallmentsParams(Mage_Sales_Model_Order $order, $payment)
    {
        $retorno = array();
        if($payment->getAdditionalInformation('installment_quantity') && $payment->getAdditionalInformation('installment_value'))
        {
            $retorno = array(
                'installmentQuantity'   => $payment->getAdditionalInformation('installment_quantity'),
                'installmentValue'      => number_format($payment->getAdditionalInformation('installment_value'),2,'.',''),
            );
        }else{
            $retorno = array(
                'installmentQuantity'   => '1',
                'installmentValue'      => number_format($order->getGrandTotal(),2,'.',''),
            );
        }
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
        $address = ($type=='shipping' && !$order->getIsVirtual()) ? $order->getShippingAddress() : $order->getBillingAddress();
        $address_street_attribute = Mage::getStoreConfig('payment/pagseguro/address_street_attribute');
        $address_number_attribute = Mage::getStoreConfig('payment/pagseguro/address_number_attribute');
        $address_complement_attribute = Mage::getStoreConfig('payment/pagseguro/address_complement_attribute');
        $address_neighborhood_attribute = Mage::getStoreConfig('payment/pagseguro/address_neighborhood_attribute');

        //obtendo dados de endereço
        $addressStreet = $this->_getAddressAttributeValue($address,$address_street_attribute);
        $addressNumber = $this->_getAddressAttributeValue($address,$address_number_attribute);
        $addressComplement = $this->_getAddressAttributeValue($address,$address_complement_attribute);
        $addressDistrict = $this->_getAddressAttributeValue($address,$address_neighborhood_attribute);
        $addressPostalCode = $digits->filter($address->getPostcode());
        $addressCity = $address->getCity();
        $addressState = $this->getStateCode( $address->getRegion() );


        $retorno = array(
            $type.'AddressStreet'     => substr($addressStreet,0,80),
            $type.'AddressNumber'     => substr($addressNumber,0,20),
            $type.'AddressComplement' => substr($addressComplement,0,40),
            $type.'AddressDistrict'   => substr($addressDistrict,0,60),
            $type.'AddressPostalCode' => $addressPostalCode,
            $type.'AddressCity'       => substr($addressCity,0,60),
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
                if($this->_shouldSplit($order)){
                    $shippingCost -= 0.01;
                }
                $retorno['shippingCost'] = number_format($shippingCost,2,'.','');
            }
        }
        return $retorno;
    }

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
            $state = mb_convert_case($state, MB_CASE_UPPER);
            $codes = array("AC"=>"ACRE", "AL"=>"ALAGOAS", "AM"=>"AMAZONAS", "AP"=>"AMAPA","BA"=>"BAHIA","CE"=>"CEARA","DF"=>"DISTRITO FEDERAL","ES"=>"ESPIRITO SANTO","GO"=>"GOIAS","MA"=>"MARANHAO","MT"=>"MATO GROSSO","MS"=>"MATO GROSSO DO SUL","MG"=>"MINAS GERAIS","PA"=>"PARA","PB"=>"PARAIBA","PR"=>"PARANA","PE"=>"PERNAMBUCO","PI"=>"PIAUI","RJ"=>"RIO DE JANEIRO","RN"=>"RIO GRANDE DO NORTE","RO"=>"RONDONIA","RS"=>"RIO GRANDE DO SUL","RR"=>"RORAIMA","SC"=>"SANTA CATARINA","SE"=>"SERGIPE","SP"=>"SAO PAULO","TO"=>"TOCANTINS");
            if($code = array_search($state,$codes))
            {
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
    public static function normalizeChars($s) {
        $replace = array(
            'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'Ae', 'Å'=>'A', 'Æ'=>'A', 'Ă'=>'A',
            'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'ae', 'å'=>'a', 'ă'=>'a', 'æ'=>'ae',
            'þ'=>'b', 'Þ'=>'B',
            'Ç'=>'C', 'ç'=>'c',
            'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E',
            'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e',
            'Ğ'=>'G', 'ğ'=>'g',
            'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'İ'=>'I', 'ı'=>'i', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i',
            'Ñ'=>'N',
            'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'Oe', 'Ø'=>'O', 'ö'=>'oe', 'ø'=>'o',
            'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
            'Š'=>'S', 'š'=>'s', 'Ş'=>'S', 'ș'=>'s', 'Ș'=>'S', 'ş'=>'s', 'ß'=>'ss',
            'ț'=>'t', 'Ț'=>'T',
            'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'Ue',
            'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ü'=>'ue',
            'Ý'=>'Y',
            'ý'=>'y', 'ý'=>'y', 'ÿ'=>'y',
            'Ž'=>'Z', 'ž'=>'z'
        );
        return strtr($s, $replace);
    }

    /**
     * Calcula o valor "Extra", que será o valor das Taxas subtraído do valor dos impostos
     * @param Mage_Sales_Model_Order $order
     *
     * @return string
     */
    public function getExtraAmount($order)
    {
        $discount = $order->getDiscountAmount();
        $tax_amount = $order->getTaxAmount();
        $extra = $discount+$tax_amount;
        if($this->_shouldSplit($order)){
            $extra = $extra+0.01;
        }
        return number_format($extra,2, '.','');
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
        //se começar com zero, pula o primeiro digito
        if(substr($phone,0,1) == '0')
        {
            $phone = substr($phone,1,strlen($phone));
        }
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
            if(isset($payment['additional_information']['credit_card_owner_birthdate'])){
                return $payment['additional_information']['credit_card_owner_birthdate'];
            }
        }

        $dob = $customer->getResource()->getAttribute($cc_dob_attribute)->getFrontend()->getValue($customer);


        return date('d/m/Y', strtotime($dob));
    }

    /**
     * Retorna o CPF do cliente baseado na selecao realizada na configuração do modulo
     * @param Mage_Sales_Model_Order $order
     * @param Mage_Payment_Model_Method_Abstract $payment
     *
     * @return mixed
     */
    private function _getCustomerCpfValue(Mage_Sales_Model_Order $order, $payment)
    {
        $customer_cpf_attribute = Mage::getStoreConfig('payment/pagseguro/customer_cpf_attribute');

        if(empty($customer_cpf_attribute)) //Soliciado ao cliente junto com os dados do cartao
        {
            if(isset($payment['additional_information'][$payment->getMethod().'_cpf'])){
                return $payment['additional_information'][$payment->getMethod().'_cpf'];
            }
        }
        $entity = explode('|',$customer_cpf_attribute);
        $cpf = '';
        if(count($entity) == 1 || $entity[0] == 'customer'){
            if(count($entity) == 2){
                $customer_cpf_attribute = $entity[1];
            }
            $customer = $order->getCustomer();

            $cpf = $customer->getData($customer_cpf_attribute);
        }else if(count($entity) == 2 && $entity[0] == 'billing' ){ //billing
            $cpf = $order->getShippingAddress()->getData($entity[1]);
        }

        return $cpf;
    }


    /**
     * Se deve ou não dividir o frete.. Se o total de produtos for igual o
     * totalde desconto, o modulo diminuirá 1 centavo do frete e adicionará
     * ao valor dos itens, pois o PagSeguro não aceita que os produtos custem
     * zero.
     *
     * @param $order
     *
     * @return bool
     */
    private function _shouldSplit($order)
    {
        $discount = $order->getDiscountAmount();
        $tax_amount = $order->getTaxAmount();
        $extraAmount = $discount+$tax_amount;

        $totalAmount = 0;
        foreach($order->getAllVisibleItems() as $item){
            $totalAmount += $item->getRowTotal();
        }
        return (abs($extraAmount) == $totalAmount);
    }
}