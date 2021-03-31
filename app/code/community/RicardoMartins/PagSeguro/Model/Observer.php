<?php
/**
 * Class RicardoMartins_PagSeguro_Model_Observer
 *
 * @author    Ricardo Martins <ricardo@magenteiro.com>
 * @copyright 2021 Magenteiro
 */
class RicardoMartins_PagSeguro_Model_Observer
{
    /**
     * Adiciona o bloco do direct payment logo após um dos forms do pagseguro ter sido inserido.
     *
     * @param $observer
     *
     * @return $this
     * @noinspection PhpUnused*/
    public function addDirectPaymentBlock($observer)
    {
        $pagseguroBlocks = array(
            'ricardomartins_pagseguropro/form_tef',
            'ricardomartins_pagseguropro/form_boleto',
            'ricardomartins_pagseguro/form_cc',
            'ricardomartins_pagseguro/form_recurring',
        );
        $blockType = $observer->getBlock()->getType();
        if (in_array($blockType, $pagseguroBlocks)) {
            $output = $observer->getTransport()->getHtml();
            $directpayment = Mage::app()->getLayout()
                                ->createBlock('ricardomartins_pagseguro/form_directpayment')
                                ->toHtml();
            $observer->getTransport()->setHtml($directpayment . $output);
        }

        return $this;
    }

    /**
     * Used to display notices and warnings regarding incompatibilities with the saved recurring product and Pagseguro
     *
     * @param $observer
     *
     * @noinspection PhpUnused*/
    public static function validateRecurringProfile($observer)
    {
        $product = $observer->getProduct();
        if (!$product || !$product->isRecurring()) {
            return;
        }

        $helper = Mage::helper('ricardomartins_pagseguro/recurring');
        $profile = $product->getRecurringProfile();
        $pagSeguroPeriod = $helper->getPagSeguroPeriod($profile);

        if (false === $pagSeguroPeriod) {
            Mage::getSingleton('core/session')->addWarning(
                'O PagSeguro não será exibido como meio de pagamento para este produto, pois as configurações do '
                . 'ciclo de cobrança não são suportadas. <a href="https://pagsegurotransparente.zendesk.com/hc/pt'
                . '-br/articles/360044169531" target="_blank">Clique aqui</a> para saber mais.'
            );
        }

        if ($profile['start_date_is_editable']) {
            Mage::getSingleton('core/session')->addWarning(
                'O PagSeguro não será exibido como meio de pagamento para este produto, pois não é possível'
                . ' definir a Data de Início em planos com cobrança automática.'
            );
        }

        if ($profile['trial_period_unit']) {
            if (!$profile['trial_period_max_cycles']) {
                Mage::getSingleton('core/session')->addWarning(
                    'Periodo máximo de cobranças te'
                    . 'mporárias deve ser especificado. Este valor será ignorado quando usado no PagSeguro, '
                    . 'mas o Magento impedirá a finalização de um pedido.'
                );
            }

            if (!$profile['trial_billing_amount']) {
                Mage::getSingleton('core/session')->addWarning(
                    'Valor temporário de cobranças deve ser especificado. Este valor será ignorado quando usado'
                    . ' no PagSeguro, mas o Magento impedirá a finalização de um pedido.'
                );
            }
        }

    }

    /**
     * This will create a new customer and update customer_id in the recurring profile if checkout is METHOD_REGISTER
     * This solves a Magento bug in recurring profiles
     *
     * @param $observer
     *
     * @throws Exception
     * @noinspection PhpUnused*/
    public function updateRecurringCustomerId($observer)
    {
        if (!$observer->getObject() || $observer->getObject()->getResourceName() != 'sales/recurring_profile') {
            return;
        }

        $quote = $observer->getObject()->getQuote();
        if (!$quote || !$quote->getId()) {
            return;
        }

        if ($quote->getData('checkout_method') != Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER) {
            return;
        }

        #registers the customer (extracted from \Mage_Checkout_Model_Type_Onepage::_prepareNewCustomerQuote)
        $billing    = $quote->getBillingAddress();
        $shipping   = $quote->isVirtual() ? null : $quote->getShippingAddress();

        $customer = $quote->getCustomer();
        $customerBilling = $billing->exportCustomerAddress();
        $customer->addAddress($customerBilling);
        $billing->setCustomerAddress($customerBilling);
        $customerBilling->setIsDefaultBilling(true);
        if ($shipping && !$shipping->getSameAsBilling()) {
            $customerShipping = $shipping->exportCustomerAddress();
            $customer->addAddress($customerShipping);
            $shipping->setCustomerAddress($customerShipping);
            $customerShipping->setIsDefaultShipping(true);
        } else {
            $customerBilling->setIsDefaultShipping(true);
        }

        Mage::helper('core')->copyFieldset('checkout_onepage_quote', 'to_customer', $quote, $customer);
        $customer->setPassword($customer->getPassword());
        $passwordCreatedTime = Mage::getSingleton('checkout/session')
                                   ->getData('_session_validator_data')['session_expire_timestamp']
            - Mage::getSingleton('core/cookie')->getLifetime();
        $customer->setPasswordCreatedAt($passwordCreatedTime);
        $quote->setCustomer($customer)
            ->setCustomerId(true);
        $quote->setPasswordHash('');

        $customer->save();
        $customerId = $customer->getEntityId();
        $observer->getObject()->getQuote()->setCustomerId($customerId);
        $data = array('customer_id' => $customerId);
        $observer->getObject()->setOrderInfo(array_merge($observer->getObject()->getOrderInfo(), $data));
        $observer->getObject()->setCustomerId($customerId);
    }

    /**
     * Observes order cancelation to void open transactions
     */
    public function voidOrderTransactions($observer)
    {
        $order = $observer->getOrder();

        if (!$order) {
            return;
        }

        $methodInstance = $order->getPayment()->getMethodInstance();

        // forces void action of the payment method, because its
        // payment action is order, but it could have open transactions
        if ($methodInstance
            && $methodInstance->getCode() == "rm_pagseguro_cc") {
            $methodInstance->void($order->getPayment());
        }
    }
}