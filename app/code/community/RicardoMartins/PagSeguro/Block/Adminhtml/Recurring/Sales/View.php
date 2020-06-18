<?php
class RicardoMartins_PagSeguro_Block_Adminhtml_Recurring_Sales_View extends Mage_Sales_Block_Recurring_Profile_View
{
    /**
     * Prepare profile main reference info
     */
    public function prepareReferenceInfo()
    {
        $this->_shouldRenderInfo = true;

        $tracker = $this->_profile->getAdditionalInfo('tracker');
        if ($tracker) {
            $link = "<a href=\"https://pagseguro.uol.com.br/pagamento-recorrente/lista.html"
                . "?asBuyer=false&tracker=$tracker\" "
                . "target='_blank' title='ver no PagSeguro'>$tracker</a>";

            if ($this->_profile->getAdditionalInfo('isSandbox')) {
                $link = "<a href=\"https://sandbox.pagseguro.uol.com.br/aplicacao/assinaturas/detalhes.html?code="
                    . $this->_profile->getReferenceId() . "\" "
                    . "target='_blank' title='ver no PagSeguro'>$tracker</a>";
            }

            $this->_addInfo(array('label' => 'Identificador', 'value' => $link, 'skip_html_escaping' => true));
        }

        $otherFields = array('Status PagSeguro'   => 'status', 'Reference' => 'reference',
                             'Última atualização' => 'last_event_date', 'Sandbox' => 'is_sandbox', 'Código do plano' => 'pagSeguroPlanCode');
        foreach ($otherFields as $label => $field) {
            $this->_addFieldIfAvailable($label, $field);
        }
    }

    protected function _addFieldIfAvailable($label, $field)
    {
        if ($value = $this->_profile->getAdditionalInfo($field)) {
            $this->_addInfo(array('label' => $label, 'value' => $value));
        }
    }

    /**
     * Add self to the specified group of parent block
     *
     * @param string $groupName
     * @return Mage_Core_Block_Abstract
     */
    public function addToParentGroup($groupName)
    {
        // do not add block if payment was made with different payment method
        if ($this->_profile && $this->_profile->getMethodCode() == 'rm_pagseguro_recurring') {
            $this->getParentBlock()->addToChildGroup($groupName, $this);
        }

        return $this;
    }
}