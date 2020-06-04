<?php
class RicardoMartins_PagSeguro_Model_Observer
{
    /**
     * Adiciona o bloco do direct payment logo após um dos forms do pagseguro ter sido inserido.
     * @param $observer
     *
     * @return $this
     */
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
     * @param $observer
     */
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
}