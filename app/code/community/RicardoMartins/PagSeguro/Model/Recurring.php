<?php
/**
 * Class RicardoMartins_PagSeguro_Model_Recurring - Responsible for Recurring payments operations with PagSeguro
 *
 * @author    Ricardo Martins
 * @copyright 2020 Magenteiro
 */
class RicardoMartins_PagSeguro_Model_Recurring extends RicardoMartins_PagSeguro_Model_Abstract
{
    const PREAPPROVAL_PERIOD_WEEKLY = 'WEEKLY';
    const PREAPPROVAL_PERIOD_MONTHLY = 'MONTHLY';
    const PREAPPROVAL_PERIOD_BIMONTHLY = 'BIMONTHLY';
    const PREAPPROVAL_PERIOD_TRIMONTHLY = 'TRIMONTHLY';
    const PREAPPROVAL_PERIOD_SEMIANNUALLY = 'SEMIANNUALLY';
    const PREAPPROVAL_PERIOD_YEARLY = 'YEARLY';


    const HEADER_V3_JSON
        = 'Accept: application/vnd.pagseguro.com.br.v3+json;charset=ISO-8859-1,Content-Type: application/json';

    const HEADER_V3_URLENCODED
        = 'Accept: application/vnd.pagseguro.com.br.v3+json;charset=ISO-8859-1,Content-Type: application/x-www-form-urlencoded; charset=ISO-8859-1';


    /** @var RicardoMartins_PagSeguro_Helper_Recurring $_helper */
    protected $_helper;

    public function __construct()
    {
        $this->_helper = Mage::helper('ricardomartins_pagseguro/recurring');
    }


    /**
     * Retrieve current status of the subscription at PagSeguro
     *
     * @param $preApprovalCode
     *
     * @param $isSandbox
     *
     * @return mixed
     * @throws Mage_Core_Exception
     */
    public function getPreApprovalDetails($preApprovalCode, $isSandbox)
    {
        $suffix = 'pre-approvals/'. $preApprovalCode;
        $helper = Mage::helper('ricardomartins_pagseguro');
        if ($isSandbox) {
            $suffix .= $isSandbox ? $helper->addUrlParam($suffix, array('isSandbox' => '1')) : '';
        }

        $key = $isSandbox ? $helper->getPagSeguroProSandboxKey() : $helper->getPagSeguroProNonSandboxKey();
        $suffix .= $helper->addUrlParam($suffix, array('public_key'=> $key));

        $response = $this->callGetAPI($suffix, explode(',', self::HEADER_V3_JSON), true);

        if (isset($response->errors)) {
            foreach ($response->errors as $code => $error) {
                $err[] = (string)$error . ' (' . $code . ')';
            }

            Mage::throwException(implode("\n", $err));
        }
        
        return $response;
    }

    /**
     * Gets latest status from PagSeguro and Updates profile
     * @param Mage_Sales_Model_Recurring_Profile $profile
     *
     * @throws Mage_Core_Exception
     */
    public function updateProfile($profile)
    {
        //general status (ACTIVE, EXPIRED, etc)
        $additionalInfo = $profile->getAdditionalInfo();
        $additionalInfo = is_string($additionalInfo) ? unserialize($profile->getAdditionalInfo())
            : $additionalInfo;
        $isSandbox = (isset($additionalInfo['isSandbox']) && $additionalInfo['isSandbox']);
        $currentStatus = $this->getPreApprovalDetails($profile->getReferenceId(), $isSandbox);
        if ($currentStatus && isset($currentStatus->status)) {
            $oldState = $profile->getState();
            $pagseguroStatus = (string)$currentStatus->status;
            $this->updateProfileStatus($profile, $pagseguroStatus);
            if ($profile->getState() != $oldState) {
                Mage::helper('ricardomartins_pagseguro/recurring')->writeLog(
                    'Status do perfil recorrente ' . $profile->getId() . ' atualizado para '
                    . $profile->getState() . '.'
                );
            }

            $currentInfo = is_array($additionalInfo) ? $additionalInfo : array();
            $profile->setAdditionalInfo(
                array_merge(
                    $currentInfo,
                    array('tracker'   => (string)$currentStatus->tracker,
                          'reference' => (string)$currentStatus->reference,
                          'status'    => (string)$currentStatus->status)
                )
            );
            $profile->save();
        }
    }

    /**
     * Update profile entity with the associated status from PagSeguro
     * @param Mage_Sales_Model_Recurring_Profile $profile
     * @param string $pagseguroStatus Status code from PagSeguro
     *
     * @return mixed
     */
    public function updateProfileStatus($profile, $pagseguroStatus)
    {
        switch ((string)$pagseguroStatus) {
            case 'ACTIVE':
                $profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_ACTIVE);
                break;
            case 'INITIATED':
            case 'PENDING':
                $profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_PENDING);
                break;
            case 'CANCELLED':
            case 'CANCELLED_BY_RECEIVER':
            case 'CANCELLED_BY_SENDER':
                $profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_CANCELED);
                break;
            case 'EXPIRED':
                $profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_EXPIRED);
                break;
        }

        return $profile->save();
    }

    /**
     * Checks for paid transactions and create orders in Magento
     * @param $subscription
     *
     * @throws Mage_Core_Exception
     */
    public function createOrders($subscription)
    {
        if ($subscription->getState() != Mage_Sales_Model_Recurring_Profile::STATE_ACTIVE) {
            return;
        }

        //let's look for the approved transactions for this subscription
        $paymentTransactions = $this->getOrderPayments($subscription);

        foreach ($paymentTransactions as $transaction) {
            $orderExists = Mage::getModel('sales/order')->loadByAttribute('ext_order_id', $transaction['code']);
            if ($orderExists->getId()) {
                continue;
            }

            $amount = $transaction['grossAmount'];
            $productItemInfo = new Varien_Object;
            $type = 'Regular';
            if ($type == 'Trial') {
                $productItemInfo->setPaymentType(Mage_Sales_Model_Recurring_Profile::PAYMENT_TYPE_TRIAL);
            } elseif ($type == 'Regular') {
                $productItemInfo->setPaymentType(Mage_Sales_Model_Recurring_Profile::PAYMENT_TYPE_REGULAR);
            }

            $taxAmount = $subscription->getTaxAmount();
            $productItemInfo->setTaxAmount($taxAmount);
            $shippingAmount = $subscription->getShippingAmount();
            $productItemInfo->setShippingAmount($shippingAmount);
            $productItemInfo->setPrice($amount - $shippingAmount - $taxAmount);

            /** @var $order Mage_Sales_Model_Order */
            $order = $subscription->createOrder($productItemInfo);
            $order->setExtOrderId($transaction['code']);

            /** @var Mage_Sales_Model_Order_Payment $payment */
            $payment = $order->getPayment();
            $payment->setTransactionId($transaction['code'])
                ->setCurrencyCode('BRL')
//                ->setPreparedMessage($this->_createIpnComment(''))
                ->setIsTransactionClosed(0);
            $order->save();
            $subscription->addOrderRelation($order->getId());
            $payment->registerCaptureNotification($amount);
            $order->save();

            $invoice = $payment->getCreatedInvoice();
            if ($invoice) {
                // notify customer
                $message = Mage::helper('paypal')->__(
                    'Notified customer about invoice #%s.', $invoice->getIncrementId()
                );
                $order->queueNewOrderEmail()->addStatusHistoryComment($message)
                    ->setIsCustomerNotified(true)
                    ->save();
            }
        }
    }

    /**
     * Return approved and payment transactions from current subscription
     *
     * @param Mage_Sales_Model_Recurring_Profile $subscription
     *
     * @return array
     * @throws Mage_Core_Exception
     */
    public function getOrderPayments($subscription)
    {
        $helper = Mage::helper('ricardomartins_pagseguro');

        $isSandbox = $subscription->getAdditionalInfo('isSandbox') ? 1 : 0;
        $key = $isSandbox ? $helper->getPagSeguroProSandboxKey() : $helper->getPagSeguroProNonSandboxKey();
        $suffix = 'pre-approvals/' . $subscription->getReferenceId() . '/payment-orders';
        $suffix.= $helper->addUrlParam($suffix, array('isSandbox' => $isSandbox, 'public_key' => $key));
        $apiReturn = $this->callGetAPI(
            $suffix,
            explode(',', self::HEADER_V3_JSON), true
        );

        if (!$apiReturn || !isset($apiReturn->paymentOrders)) {
            return array();
        }

        $paymentOrders = array();
        foreach ($apiReturn->paymentOrders as $order) {
            //if there's no transactions or the last transaction is not paid...
            //https://dev.pagseguro.uol.com.br/docs/pagamento-recorrente-tabelas-de-status-e-erros
            if (!isset($order->transactions) || $order->status != 5) {
                continue;
            }

            foreach ($order->transactions as $transaction) {
                $paymentOrders[] = array(
                    'code' => (string)$transaction->code,
                    'date' => (string)$transaction->date, //phpcs:ignore
                    'status' => (int)$transaction->status,
                    'grossAmount' => (float)$order->grossAmount,
                    'amount' => (float)$order->amount
                );
            }
        }

        return $paymentOrders;

    }

    /**
     * Get recurring profile id for the current order
     *
     * @param Mage_Sales_Model_Order $order
     *
     * @return bool|string
     */
    public function getRecurringProfileIdFromOrder($order)
    {
        if (!$order->getId()) {
            return false;
        }

        $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
        $bind    = array(':order_id' => $order->getId());
        $select  = $adapter->select()
            ->from(
                array('main_table'
                      => Mage::getSingleton('core/resource')->getTableName('sales/recurring_profile_order')),
                array('profile_id')
            )
            ->where('order_id=:order_id');

        return $adapter->fetchOne($select, $bind);
    }

    /**
     * @param Mage_Sales_Model_Order$order
     *
     * @return Mage_Core_Model_Abstract|Mage_Sales_Model_Recurring_Profile|null
     */
    public function getProfileFromOrder($order)
    {
        return Mage::getSingleton('sales/recurring_profile')->load(
            $this->getRecurringProfileIdFromOrder($order)
        );
    }

    public function changePagseguroStatus($profile, $newStatus)
    {
        $helper = Mage::helper('ricardomartins_pagseguro');
        $preApproval = $profile->getReferenceId();
        $suffix = 'pre-approvals/' . $preApproval . '/status/';
        $isSandbox = $profile->getAdditionalInfo('isSandbox') ? 1 : 0;

        $key = $isSandbox ? $helper->getPagSeguroProSandboxKey() : $helper->getPagSeguroProNonSandboxKey();
        $suffix .= $helper->addUrlParam($suffix, array('public_key'=> $key));
        $suffix .= $helper->addUrlParam($suffix, array('isSandbox'=> $isSandbox));


        $apiReturn = $this->callPutAPI(
            $suffix,
            explode(',', self::HEADER_V3_JSON),
            '{"status": "'. $newStatus . '"}',
            true
        );

        if ($apiReturn != '') {
            return false;
        }

        return true;
    }

    public function cancelPagseguroProfile($profile)
    {
        $helper = Mage::helper('ricardomartins_pagseguro');
        $preApproval = $profile->getReferenceId();
        $suffix = 'pre-approvals/' . $preApproval . '/cancel/';
        $isSandbox = $profile->getAdditionalInfo('isSandbox') ? 1 : 0;

        $key = $isSandbox ? $helper->getPagSeguroProSandboxKey() : $helper->getPagSeguroProNonSandboxKey();
        $suffix .= $helper->addUrlParam($suffix, array('public_key'=> $key));
        $suffix .= $helper->addUrlParam($suffix, array('isSandbox'=> $isSandbox));


        return $this->callPutAPI(
            $suffix,
            explode(',', self::HEADER_V3_JSON),
            '',
            true
        );

    }

}