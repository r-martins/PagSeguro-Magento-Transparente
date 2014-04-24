<?php
class RicardoMartins_PagSeguro_TestController extends Mage_Core_Controller_Front_Action
{
    public function indexAction(){

        $client = new Zend_Http_Client('https://ws.pagseguro.uol.com.br/v2/sessions/');
        $client->setMethod(Zend_Http_Client::POST);
        $client->setParameterGet('email','ricardo@ricardomartins.info');
        $client->setParameterGet('token','9F79900A9B454CE6B18613D7224C0621');
        $client->request();

        var_dump($client->getLastResponse()->getBody());
    }

    public function xmlAction()
    {
        echo Mage::helper('ricardomartins_pagseguro')->_getToken();
    }


    public function formAction()
    {
        $this->loadLayout();

        $scriptblock = Mage::app()->getLayout()->createBlock('core/text', 'js_pagseguro');
        $scriptblock->setText(sprintf(
                '<script type="text/javascript" src="%s"></script>
                <script type="text/javascript" src="/js/pagseguro/pagseguro.js"/>
                ',
                Mage::helper('ricardomartins_pagseguro')->getJsUrl()
            ));
        $head = Mage::app()->getLayout()->getBlock('after_body_start');
        $head->append($scriptblock);

        $_helper = Mage::helper('ricardomartins_pagseguro');


$html =<<<EOF

<form id="meu_form" action="#">
    <li>
        <label for="pagseguro_cc_cc_number" class="required"><em>*</em>Credit Card Number</label>
        <div class="input-box">
            <input autocomplete="off" id="pagseguro_cc_cc_number" name="payment[ps_cc_number]" title="Credit Card Number" class="input-text validate-cc-number validate-cc-type" value=""  type="text">
            <span id="card-brand"></span>
        </div>
    </li>
    <li id="pagseguro_cc_cc_type_exp_div">
        <label for="pagseguro_cc_expiration" class="required"><em>*</em>Expiration Date</label>
        <div class="input-box">
            <div class="v-fix">
                <select autocomplete="off" id="pagseguro_cc_expiration" name="payment[ps_cc_exp_month]" class="month validate-cc-exp required-entry">
                                                    <option value="" selected="selected">Month</option>
                                    <option value="1">01 - janeiro</option>
                                    <option value="2">02 - fevereiro</option>
                                    <option value="3">03 - março</option>
                                    <option value="4">04 - abril</option>
                                    <option value="5">05 - maio</option>
                                    <option value="6">06 - junho</option>
                                    <option value="7">07 - julho</option>
                                    <option value="8">08 - agosto</option>
                                    <option value="9">09 - setembro</option>
                                    <option value="10">10 - outubro</option>
                                    <option value="11">11 - novembro</option>
                                    <option value="12">12 - dezembro</option>
                                </select>
            </div>
            <div class="v-fix">
                                <select autocomplete="off" id="pagseguro_cc_expiration_yr" name="payment[ps_cc_exp_year]" class="year required-entry">
                                    <option value="" selected="selected">Year</option>
                                    <option value="2014">2014</option>
                                    <option value="2015">2015</option>
                                    <option value="2016">2016</option>
                                    <option value="2017">2017</option>
                                    <option value="2018">2018</option>
                                    <option value="2019">2019</option>
                                    <option value="2020">2020</option>
                                    <option value="2021">2021</option>
                                    <option value="2022">2022</option>
                                    <option value="2023">2023</option>
                                    <option value="2024">2024</option>
                                </select>
            </div>
        </div>
    </li>
            <li id="pagseguro_cc_cc_type_cvv_div">
        <label for="pagseguro_cc_cc_cid" class="required"><em>*</em>Card Verification Number</label>
        <div class="input-box">
            <div class="v-fix">
                <input autocomplete="off" title="Card Verification Number" class="input-text cvv required-entry validate-cc-cvn" id="ps_cc_cid" name="payment[ps_cc_cid]" value="" type="text">
            </div>
            <a href="#" class="cvv-what-is-this">What is this?</a>
    </div>

    <?php #nao remover/alterar ?>
    <input type="hidden" name="payment[sender_hash]"/>
    <input type="hidden" name="payment[credit_card_token]"/>


    <input type="submit"/>



    </li>
    </form>
<script type="text/javascript">
    //<![CDATA[


       PagSeguroDirectPayment.setSessionId('{$_helper->getSessionId()}');


    //]]>
</script>

EOF;

        $block = Mage::app()->getLayout()->createBlock('core/text')->setText($html);

        Mage::app()->getLayout()->getBlock('content')->append($block, 'teste');
        $this->renderLayout();
    }
}