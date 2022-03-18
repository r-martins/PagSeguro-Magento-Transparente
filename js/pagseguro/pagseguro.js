/**
 * PagSeguro Transparente para Magento
 * @author Ricardo Martins <ricardo@ricardomartins.net.br>
 * @link https://github.com/r-martins/PagSeguro-Magento-Transparente
 * @version 3.15.0
 */

RMPagSeguro = Class.create({
    initialize: function (config) {
        this.config = config;

        if (!config.PagSeguroSessionId) {
            console.error('Falha ao obter sessão junto ao PagSeguro. Verifique suas credenciais, configurações e logs de erro.')
        }
        PagSeguroDirectPayment.setSessionId(config.PagSeguroSessionId);

        // this.updateSenderHash();
        PagSeguroDirectPayment.onSenderHashReady(this.updateSenderHash);

        if (typeof config.checkoutFormElm == "undefined") {
            var methods= $$('#p_method_rm_pagseguro_cc', '#p_method_pagseguropro_boleto', '#p_method_pagseguropro_tef');
            if(!methods.length){
                console.log('PagSeguro: Não há métodos de pagamento habilitados em exibição. Execução abortada.');
                return;
            }else{
                var form = methods.first().closest('form');
                form.observe('submit', function(e){
                    e.preventDefault();
                    e.stopPropagation();
                    RMPagSeguroObj.formElementAndSubmit = e.element();
                    RMPagSeguroObj.updateCreditCardToken();
                });
            }
        }

        if(config.PagSeguroSessionId == false){
            console.error('Não foi possível obter o SessionId do PagSeguro. Verifique seu token, chave e configurações.');
        }
        console.log('RMPagSeguro prototype class has been initialized.');

        this.maxSenderHashAttempts = 30;

        //internal control to avoid duplicated calls to updateCreditCardToken
        this.updatingCreditCardToken = false;
        this.formElementAndSubmit = false;


        Validation.add('validate-pagseguro', 'Falha ao atualizar dados do pagamento. Entre novamente com seus dados.',
            function(v, el){
                RMPagSeguroObj.updatePaymentHashes();
                return true;
        });
    },
    updateSenderHash: function(response) {
        if(typeof response === 'undefined'){
            PagSeguroDirectPayment.onSenderHashReady(RMPagSeguroObj.updateSenderHash);
            return false;
        }
        if(response.status == 'error'){
            console.log('PagSeguro: Falha ao obter o senderHash. ' + response.message);
            return false;
        }
        RMPagSeguroObj.senderHash = response.senderHash;
        RMPagSeguroObj.updatePaymentHashes();

        return true;
    },

    //used when multicc is DISABLED
    getInstallments: function(grandTotal, selectedInstallment){
        var brandName = "";
        if(typeof RMPagSeguroObj.brand == "undefined"){
            return;
        }
        if(!grandTotal){
            grandTotal = this.getGrandTotal();
            return;
        }
        this.grandTotal = grandTotal;

        brandName = RMPagSeguroObj.brand.name;
        var parcelsDrop = $('rm_pagseguro_cc_cc_installments');
        if(!selectedInstallment && parcelsDrop.value != ""){
            selectedInstallment = parcelsDrop.value.split('|').first();
        }
        var maxInstallmentNoInterest = RMPagSeguroObj.config.installment_free_interest_minimum_amt === "0" ? 0 : "";
        if (RMPagSeguroObj.config.installment_free_interest_minimum_amt > 0) {
            maxInstallmentNoInterest = grandTotal / RMPagSeguroObj.config.installment_free_interest_minimum_amt;
            maxInstallmentNoInterest = Math.floor(maxInstallmentNoInterest);
            maxInstallmentNoInterest = (maxInstallmentNoInterest > 1) ? maxInstallmentNoInterest : '';
        }
        PagSeguroDirectPayment.getInstallments({
            amount: grandTotal,
            brand: brandName,
            maxInstallmentNoInterest: maxInstallmentNoInterest,
            success: function(response) {
                for(installment in response.installments) break;
//                       console.log(response.installments);
//                 var responseBrand = Object.keys(response.installments)[0];
//                 var b = response.installments[responseBrand];
                var b = Object.values(response.installments)[0];
                parcelsDrop.length = 0;

                if(RMPagSeguroObj.config.force_installments_selection){
                    var option = document.createElement('option');
                    option.text = "Selecione a quantidade de parcelas";
                    option.value = "";
                    parcelsDrop.add(option);
                }

                var installment_limit = RMPagSeguroObj.config.installment_limit;
                for(var x=0; x < b.length; x++){
                    var option = document.createElement('option');
                    option.text = b[x].quantity + "x de R$" + b[x].installmentAmount.toFixed(2).toString().replace('.',',');
                    option.text += (b[x].interestFree)?" sem juros":" com juros";
                    if(RMPagSeguroObj.config.show_total){
                        option.text += " (total R$" + (b[x].installmentAmount*b[x].quantity).toFixed(2).toString().replace('.', ',') + ")";
                    }
                    option.selected = (b[x].quantity == selectedInstallment);
                    option.value = b[x].quantity + "|" + b[x].installmentAmount;
                    if (installment_limit != 0 && installment_limit <= x) {
                        break;
                    }
                    parcelsDrop.add(option);
                }
//                       console.log(b[0].quantity);
//                       console.log(b[0].installmentAmount);

            },
            error: function(response) {
                parcelsDrop.length = 0;

                var option = document.createElement('option');
                option.text = "1x de R$" + RMPagSeguroObj.grandTotal.toFixed(2).toString().replace('.',',') + " sem juros";
                option.selected = true;
                option.value = "1|" + RMPagSeguroObj.grandTotal.toFixed(2);
                parcelsDrop.add(option);

                var option = document.createElement('option');
                option.text = "Falha ao obter demais parcelas junto ao pagseguro";
                option.value = "";
                parcelsDrop.add(option);

                console.error('Somente uma parcela será exibida. Erro ao obter parcelas junto ao PagSeguro:');
                console.error(response);
            },
            complete: function(response) {
//                       console.log(response);
//                 RMPagSeguro.reCheckSenderHash();
            }
        });
    },

    addCardFieldsObserver: function(obj){
        try {
            var ccNumElm = $$('input[name="payment[ps_cc_number]"]').first();
            var ccExpMoElm = $$('select[name="payment[ps_cc_exp_month]"]').first();
            var ccExpYrElm = $$('select[name="payment[ps_cc_exp_year]"]').first();
            var ccCvvElm = $$('input[name="payment[ps_cc_cid]"]').first();

            Element.observe(ccNumElm,'change',function(e){obj.updateCreditCardToken();});
            Element.observe(ccExpMoElm,'change',function(e){obj.updateCreditCardToken();});
            Element.observe(ccExpYrElm,'change',function(e){obj.updateCreditCardToken();});
            Element.observe(ccCvvElm,'change',function(e){obj.updateCreditCardToken();});
        }catch(e){
            console.error('Não foi possível adicionar observevação aos cartões. ' + e.message);
        }

    },
    updateCreditCardToken: function(){
        var ccNum = $$('input[name="payment[ps_cc_number]"]').first().value.replace(/\D/g,'');
        // var ccNumElm = $$('input[name="payment[ps_cc_number]"]').first();
        var ccExpMo = $$('select[name="payment[ps_cc_exp_month]"]').first().value.replace(/\D/g,'');
        var ccExpYr = $$('select[name="payment[ps_cc_exp_year]"]').first().value.replace(/\D/g,'');
        var ccCvv = $$('input[name="payment[ps_cc_cid]"]').first().value.replace(/\D/g,'');

        var brandName = '';
        if(typeof RMPagSeguroObj.lastCcNum != "undefined" || ccNum != RMPagSeguroObj.lastCcNum){
            this.updateBrand();
            if(typeof RMPagSeguroObj.brand != "undefined"){
                brandName = RMPagSeguroObj.brand.name;
            }
        }

        if(ccNum.length > 6 && ccExpMo != "" && ccExpYr != "" && ccCvv.length >= 3)
        {
            if(this.updatingCreditCardToken){
                return;
            }
            this.updatingCreditCardToken = true;

            RMPagSeguroObj.disablePlaceOrderButton();
            PagSeguroDirectPayment.createCardToken({
                cardNumber: ccNum,
                brand: brandName,
                cvv: ccCvv,
                expirationMonth: ccExpMo,
                expirationYear: ccExpYr,
                success: function(psresponse){
                    RMPagSeguroObj.creditCardToken = psresponse.card.token;
                    var formElementAndSubmit = RMPagSeguroObj.formElementAndSubmit;
                    RMPagSeguroObj.formElementAndSubmit = false;
                    RMPagSeguroObj.updatePaymentHashes(formElementAndSubmit);
                    $('card-msg').innerHTML = '';
                },
                error: function(psresponse){
                    if(undefined!=psresponse.errors["30400"]) {
                        $('card-msg').innerHTML = 'Dados do cartão inválidos.';
                    }else if(undefined!=psresponse.errors["10001"]){
                        $('card-msg').innerHTML = 'Tamanho do cartão inválido.';
                    }else if(undefined!=psresponse.errors["10002"]){
                        $('card-msg').innerHTML = 'Formato de data inválido';
                    }else if(undefined!=psresponse.errors["10003"]){
                        $('card-msg').innerHTML = 'Código de segurança inválido';
                    }else if(undefined!=psresponse.errors["10004"]){
                        $('card-msg').innerHTML = 'Código de segurança é obrigatório';
                    }else if(undefined!=psresponse.errors["10006"]){
                        $('card-msg').innerHTML = 'Tamanho do Código de segurança inválido';
                    }else if(undefined!=psresponse.errors["30405"]){
                        $('card-msg').innerHTML = 'Data de validade incorreta.';
                    }else if(undefined!=psresponse.errors["30403"]){
                        RMPagSeguroObj.updateSessionId(); //Se sessao expirar, atualizamos a session
                    }else if(undefined!=psresponse.errors["20000"]){ // request error (pagseguro fora?)
                        console.log('Erro 20000 no PagSeguro. Tentando novamente...');
                        RMPagSeguroObj.updateCreditCardToken(); //tenta de novo
                    }else{
                        console.log('Resposta PagSeguro (dados do cartao incorrreto):');
                        console.log(psresponse);
                        $('card-msg').innerHTML = 'Verifique os dados do cartão digitado.';
                    }
                    console.error('Falha ao obter o token do cartao.');
                    console.log(psresponse.errors);
                },
                complete: function(psresponse){
                    RMPagSeguroObj.updatingCreditCardToken = false;
                    RMPagSeguroObj.enablePlaceOrderButton();
                    if(RMPagSeguroObj.config.debug){
                        console.info('Card token updated successfully.');
                    }
                },
            });
        }
        if(typeof RMPagSeguroObj.brand != "undefined") {
            this.getInstallments();
        }
    },
    updateBrand: function(){
        var ccNum = $$('input[name="payment[ps_cc_number]"]').first().value.replace(/\D/g,'');
        var currentBin = ccNum.substring(0, 6);
        var flag = RMPagSeguroObj.config.flag; //tamanho da bandeira
        var urlPrefix = 'https://stc.pagseguro.uol.com.br/';
        if(this.config.stc_mirror){
            urlPrefix = 'https://stcpagseguro.ricardomartins.net.br/';
        }

        if(ccNum.length >= 6){
            if (typeof RMPagSeguroObj.cardBin != "undefined" && currentBin == RMPagSeguroObj.cardBin) {
                if(typeof RMPagSeguroObj.brand != "undefined"){
                    $('card-brand').innerHTML = '<img src="' + urlPrefix + 'public/img/payment-methods-flags/' +flag + '/' + RMPagSeguroObj.brand.name + '.png" alt="' + RMPagSeguroObj.brand.name + '" title="' + RMPagSeguroObj.brand.name + '"/>';
                }
                return;
            }
            RMPagSeguroObj.cardBin = ccNum.substring(0, 6);
            PagSeguroDirectPayment.getBrand({
                cardBin: currentBin,
                success: function(psresponse){
                    RMPagSeguroObj.brand = psresponse.brand;
                    $('card-brand').innerHTML = psresponse.brand.name;
                    if(RMPagSeguroObj.config.flag != ''){

                        $('card-brand').innerHTML = '<img src="' + urlPrefix + 'public/img/payment-methods-flags/' +flag + '/' + psresponse.brand.name + '.png" alt="' + psresponse.brand.name + '" title="' + psresponse.brand.name + '"/>';
                    }
                    $('card-brand').className = psresponse.brand.name.replace(/[^a-zA-Z]*!/g,'');
                },
                error: function(psresponse){
                    console.error('Falha ao obter bandeira do cartão.');
                    if(RMPagSeguroObj.config.debug){
                        console.debug('Verifique a chamada para /getBin em df.uol.com.br no seu inspetor de Network a fim de obter mais detalhes.');
                    }
                }
            })
        }
    },
    disablePlaceOrderButton: function(){
        if (RMPagSeguroObj.config.placeorder_button) {
            if(typeof $$(RMPagSeguroObj.config.placeorder_button).first() != 'undefined'){
                $$(RMPagSeguroObj.config.placeorder_button).first().up().insert({
                    'after': new Element('div',{
                        'id': 'pagseguro-loader'
                    })
                });

                $$('#pagseguro-loader').first().setStyle({
                    'background': '#000000a1 url(\'' + RMPagSeguroObj.config.loader_url + '\') no-repeat center',
                    'height': $$(RMPagSeguroObj.config.placeorder_button).first().getStyle('height'),
                    'width': $$(RMPagSeguroObj.config.placeorder_button).first().getStyle('width'),
                    'left': document.querySelector(RMPagSeguroObj.config.placeorder_button).offsetLeft + 'px',
                    'z-index': 99,
                    'opacity': .5,
                    'position': 'absolute',
                    'top': document.querySelector(RMPagSeguroObj.config.placeorder_button).offsetTop + 'px'
                });
                // $$(RMPagSeguroObj.config.placeorder_button).first().disable();
                return;
            }

            if(RMPagSeguroObj.config.debug){
                console.error('PagSeguro: Botão configurado não encontrado (' + RMPagSeguroObj.config.placeorder_button + '). Verifique as configurações do módulo.');
            }
        }
    },
    enablePlaceOrderButton: function(){
        if(RMPagSeguroObj.config.placeorder_button && typeof $$(RMPagSeguroObj.config.placeorder_button).first() != 'undefined'){
            $$('#pagseguro-loader').first().remove();
            // $$(RMPagSeguroObj.config.placeorder_button).first().enable();
        }
    },
    updatePaymentHashes: function(formElementAndSubmit=false){
        var _url = RMPagSeguroSiteBaseURL + 'pseguro/ajax/updatePaymentHashes';
        var _paymentHashes = {
            "payment[sender_hash]": this.senderHash,
            "payment[credit_card_token]": this.creditCardToken,
            "payment[cc_type]": (this.brand)?this.brand.name:'',
            "payment[is_admin]": this.config.is_admin
        };
        new Ajax.Request(_url, {
            method: 'post',
            parameters: _paymentHashes,
            onSuccess: function(response){
                if(RMPagSeguroObj.config.debug){
                    console.debug('Hashes atualizados com sucesso.');
                    console.debug(_paymentHashes);
                }
            },
            onFailure: function(response){
                if(RMPagSeguroObj.config.debug){
                    console.error('Falha ao atualizar os hashes da sessão.');
                    console.error(response);
                }
                return false;
            }
        });
        if(formElementAndSubmit){
            formElementAndSubmit.submit();
        }
    },
    getGrandTotal: function(){
        if(this.config.is_admin){
            return this.grandTotal;
        }
        var _url = RMPagSeguroSiteBaseURL + 'pseguro/ajax/getGrandTotal';
        new Ajax.Request(_url, {
            onSuccess: function(response){
                RMPagSeguroObj.grandTotal =  response.responseJSON.total;
                RMPagSeguroObj.getInstallments(RMPagSeguroObj.grandTotal);
            },
            onFailure: function(response){
                return false;
            }
        });
    },
    removeUnavailableBanks: function() {
        if (RMPagSeguroObj.config.active_methods.tef) {
            if($('pseguro_tef_bank').nodeName != "SELECT"){
                //se houve customizações no elemento dropdown de bancos, não selecionaremos aqui
                return;
            }
            PagSeguroDirectPayment.getPaymentMethods({
                amount: RMPagSeguroObj.grandTotal,
                success: function (response) {
                    if (response.error == true && RMPagSeguroObj.config.debug) {
                        console.log('Não foi possível obter os meios de pagamento que estão funcionando no momento.');
                        return;
                    }
                    if (RMPagSeguroObj.config.debug) {
                        console.log(response.paymentMethods);
                    }

                    try {
                        $('pseguro_tef_bank').options.length = 0;
                        for (y in response.paymentMethods.ONLINE_DEBIT.options) {
                            if (response.paymentMethods.ONLINE_DEBIT.options[y].status != 'UNAVAILABLE') {
                                var optName = response.paymentMethods.ONLINE_DEBIT.options[y].displayName.toString();
                                var optValue = response.paymentMethods.ONLINE_DEBIT.options[y].name.toString();

                                var optElm = new Element('option', {value: optValue}).update(optName);
                                $('pseguro_tef_bank').insert(optElm);
                            }
                        }

                        if(RMPagSeguroObj.config.debug){
                            console.info('Bancos TEF atualizados com sucesso.');
                        }
                    } catch (err) {
                        console.log(err.message);
                    }
                }
            })
        }
    },
    updateSessionId: function() {
        var _url = RMPagSeguroSiteBaseURL + 'pseguro/ajax/getSessionId';
        new Ajax.Request(_url, {
            onSuccess: function (response) {
                var session_id = response.responseJSON.session_id;
                if(!session_id){
                    console.log('Não foi possível obter a session id do PagSeguro. Verifique suas configurações.');
                }
                PagSeguroDirectPayment.setSessionId(session_id);
            }
        });
    }
});


RMPagSeguro_Multicc_Control = Class.create
({
    initialize: function(paymentMethodCode, params)
    {
        this.paymentMethodCode = paymentMethodCode;
        this.grandTotal = params.grandTotal;
        this.isMultiCcPreEnabled = false;
        this.forms = {};
        this.syncLocks = {};
        this.importedFormData = {};
        this.psFunctionsQueues = {};
        this.sequentialNumber = null;
        this.config = Object.assign((params.config ? params.config : {}), RMPagSeguroObj.config);

        this._setupUniqueObject();
        this._initForms();
        this._initObservers();
        //this._startAjaxListeners();
    },

    /**
     * Ensures that only one instance of the class exists in 
     * the page, destroying the existing one before itself
     */
     _setupUniqueObject: function()
    {
        this.sequentialNumber = 1;

        if(typeof window.rm_pagseguro_multicc_control != "undefined")
        {
            // before destroying its ancestor, copy its data
            this.importedFormData = window.rm_pagseguro_multicc_control.exportFormData();
            this.isMultiCcPreEnabled = this.importedFormData.isMultiCcEnabled;
            this.sequentialNumber += window.rm_pagseguro_multicc_control.sequentialNumber;

            // goodbye, old friend
            window.rm_pagseguro_multicc_control.destroy();
            delete window.rm_pagseguro_multicc_control;
        }

        window.rm_pagseguro_multicc_control = this;
    },

    /**
     * Creates class instances to control each card form
     * and shows first one for conventional payment flow
     */
    _initForms: function()
    {
        this.forms["cc1"] = new RMPagSeguro_Multicc_CardForm
        ({
            cardIndex         : 1, 
            parentObj         : this, 
            paymentMethodCode : this.paymentMethodCode, 
            grandTotal        : this.grandTotal,
            oldGrandTotal     : this.importedFormData ? this.importedFormData.grandTotal : false,
            importedData      : (typeof this.importedFormData["cc1"] != "undefined")
                                    ? this.importedFormData["cc1"]
                                    : false
        });
        
        this.forms["cc2"] = new RMPagSeguro_Multicc_CardForm
        ({
            cardIndex         : 2, 
            parentObj         : this, 
            paymentMethodCode : this.paymentMethodCode, 
            grandTotal        : this.grandTotal, 
            config            : { _summary: false },
            importedData      : (typeof this.importedFormData["cc2"] != "undefined")
                                    ? this.importedFormData["cc2"]
                                    : false
        });

        if(this.isMultiCcPreEnabled)
        {
            this._enableMultiCc();
        }
        else
        {
            this._getSwitch().checked = false; // ensures that browser or OSC won't play with us
            this._disableMultiCc();
        }

        // hook due to OSC bug (wrong grand total by not considering default shipping method value):
        // if its the first object of the page, we request a grand total update
        if(this.sequentialNumber == 1)
        {
            this.requestUpdateGrandTotal();
        }

        this._declareValidators();
    },

    /**
     * Initializes all navigation observers
     */
    _initObservers: function()
    {
        // multi cc switch
        var multiCcSwitch = this._getSwitch();
        multiCcSwitch.observe('change', (function(event)
        {
            var checkbox = event.currentTarget;
            checkbox.checked 
                ? this._enableMultiCc() 
                : this._disableMultiCc();
            
        }).bind(this));

        // {go to card 2 form} button
        this._getGoToCard2FormButton().observe('click', (function()
        {
            if(this.forms["cc1"].validate())
            {
                this._goToCard("cc2");
            }
        
        }).bind(this));

        // summary links 
        this.forms["cc1"].getSummaryBox().observe('click', this._goToCard.bind(this, "cc1"));

        // syncs totals between forms
        var updateOtherTotalFunc = (function(newValue) { return this.grandTotal - newValue; }).bind(this);
        this._syncData("total", "cc1", "cc2", updateOtherTotalFunc);
        this._syncData("total", "cc2", "cc1", updateOtherTotalFunc);
    },

    /**
     * Ensures that the two forms are fulfilled before 
     * place order, when multi cc is enabled
     */
    _validateMultiCcForms: function()
    {
        var field = this._getSwitch();
        
        if( field.checked &&
            this._validateForm(1) && 
            !this._validateForm(2)
        ) {
            return false;
        }
        
        return true;
    },

    /**
     * Validates forms without triggers its advices 
     */
     _validateForm: function(cardIndex)
    {
        var valid = true;

        this.forms["cc" + cardIndex].getHTMLFormInputsAndSelects().each(function(elm)
        {
            if(elm.id == (this.paymentMethodCode + "_cc" + cardIndex + "_total"))
            {
                return;
            }

            var classes = $w(elm.className);

            for(var idx in classes)
            {
                var validation = Validation.get(classes[idx]);
                valid &= validation.test($F(elm), elm);
            }
        });

        return valid;
    },
    
    /**
     * Syncs card data between two forms and avoids infinite loop
     * @param string data 
     * @param string callingForm 
     * @param string destForm 
     * @param function relationFunction 
     */
    _syncData: function(data, callingForm, destForm, relationFunction)
    {
        // register the data bind and its fulfillment in the other form
        var self = this;
        var passedFunction = function(newValue, previousValue)
        {
            if( self._hasSyncLock(data, destForm, callingForm) ||
                self._hasSyncLock(data, callingForm, destForm) )
            {
                return;
            }

            var updatingValue = relationFunction(newValue, previousValue);

            if(typeof updatingValue !== "undefined")
            {
                self.forms[destForm].setCardData("total", updatingValue);
            }
        };

        this.forms[callingForm].addCardDataBind(data, passedFunction);
    },

    /**
     * Applies and removes sync lock 
     * @param string data 
     * @param string callingForm 
     * @param string destForm 
     */
    _hasSyncLock: function(data, callingForm, destForm)
    {
        // create the syncLock group, if it doesnt exists
        if(typeof this.syncLocks[data] === "undefined")
        {
            this.syncLocks[data] = {};
        }

        // verify if there is a sync lock for this execution

        // First case: this means that the lock didnt exists  
        // and we must create it
        if(typeof this.syncLocks[data][callingForm] === "undefined")
        {
            this.syncLocks[data][callingForm] = true;
            return false;
        }
        // Second case: this means that there is a lock, and   
        // we must clear it and stop the execution
        else
        {
            delete this.syncLocks[data][callingForm];
            delete this.syncLocks[data][destForm];
            return true;
        }
    },

    /**
     * Retrieves the {turn on} / {turn off} multi cc switch
     */
    _getSwitch()
    {
        return $(this.paymentMethodCode + "_switch_use_two_cards");
    },

    /**
     * Retrieves the {go to second card form} button
     */
    _getGoToCard2FormButton()
    {
        return $(this.paymentMethodCode + "_button_go_to_card_two_form");
    },

    /**
     * Activates multi card funcionality
     */
    _enableMultiCc: function()
    {
        for(var formId in this.forms) { this.forms[formId].enable(); };
        this.forms["cc1"].openEditMode();
        
        if(!this.isMultiCcPreEnabled)
        {
            this.forms["cc1"].setCardData("total", 0);
        }
        else
        {
            // disables the flag for next interactions
            this.isMultiCcPreEnabled = false;
        }

        this._getGoToCard2FormButton().show();
    },

    /**
     * Deactivates multi card functionality
     */
    _disableMultiCc: function()
    {
        for(var formId in this.forms) { this.forms[formId].disable(); };
        this.forms["cc1"].enable();
        this.forms["cc1"].openEditMode();
        this.forms["cc1"].hideTotal();
        this.forms["cc1"].setCardData("total", this.grandTotal);
        
        this._getGoToCard2FormButton().hide();
    },

    /**
     * Synthesizes {go to card 1 form} actions
     */
    _goToCard: function(index)
    {
        for(var formId in this.forms) { this.forms[formId].closeEditMode(); };   
        this.forms[index].openEditMode();

        if(index == "cc1")
        {
            this._getGoToCard2FormButton().show();
        }
        else
        {
            this._getGoToCard2FormButton().hide();
        }
    },

    /**
     * Collects data from its forms and return it
     */
     exportFormData: function(index)
    {
        var formData = {};
        
        for(var formId in this.forms)
        {
            formData[formId] = this.forms[formId].exportData();
        }
        
        formData.isMultiCcEnabled = this._getSwitch().checked;
        formData.grandTotal = this.grandTotal;

        return formData;
    },

    /**
     * Triggers the destroy method on forms
     */
     destroy: function()
    {
        for(var formId in this.forms)
        {
            this.forms[formId].destroy();
        }
    },

    /**
     * Starts system that monitors ajax requests on checkout
     */
    _startAjaxListeners: function()
    {
        var self = this;

        // overrides browser ajax requests object
        var oldXHR = window.XMLHttpRequest;
        function newXHR()
        {
            var realXHR = new oldXHR();
    
            realXHR.addEventListener("readystatechange", function()
            {
                if(this.readyState === realXHR.DONE && this.status === 200)
                {
                    self._processAjaxListeners(this.responseURL, this);
                }
            }, false);
    
            return realXHR;
        }
        window.XMLHttpRequest = newXHR;

        /*

        // ProtoypeJS version
        Ajax.Responders.register
        ({
            onCreate: function()
            {
                alert('a request has been initialized');
            }, 
            onComplete: function()
            {
                alert('a request completed');
            }
        });
        */

        // registers urls to be observed
        this.ajaxListeners = [];
        this._registerAjaxListener({url: "onestepcheckout/ajax/saveAddress",         callback: this._ajaxListener__tryToCaptureGrandTotal});
        this._registerAjaxListener({url: "onestepcheckout/ajax/saveFormValues",      callback: this._ajaxListener__tryToCaptureGrandTotal});
        this._registerAjaxListener({url: "onestepcheckout/ajax/saveShippingMethod",  callback: this._ajaxListener__tryToCaptureGrandTotal});
        this._registerAjaxListener({url: "onestepcheckout/ajax/applyCoupon",         callback: this._ajaxListener__tryToCaptureGrandTotal});
    },

    /**
     * Adds URL to ajax listeners
     * @param Object listener 
     */
    _registerAjaxListener: function(listener)
    {
        if(typeof listener.url == "string")
        {
            listener.url = new RegExp(listener.url);
        }

        this.ajaxListeners.push(listener);
    },

    /**
     * Verifies if the URL is monitored and runs callback function
     * @param string candidateUrl 
     */
    _processAjaxListeners: function(candidateUrl, XMLHttpRequestObj)
    {
        this.ajaxListeners.each((function(listener)
        {
            if(listener.url.test(candidateUrl))
            {
                listener.callback(XMLHttpRequestObj);
            }

        }).bind(this));
    },

    /**
     * Default ajax listener to observe grand total change on OSC
     * @param XMLHttpRequest XMLHttpRequestObj 
     */
    _ajaxListener__tryToCaptureGrandTotal: function(XMLHttpRequestObj)
    {
        if(!XMLHttpRequestObj)
        {
            return;
        }

        var responseJson = JSON.parse(XMLHttpRequestObj.response);

        if( responseJson && 
            responseJson.grand_total && 
            this.grandTotal != responseJson.grand_total )
        {
            console.warn("Total do pedido alterado.");

            this.grandTotal = responseJson.grand_total;
            var isMultiCcEnabled = this._getSwitch().checked;

            for(var formId in this.forms)
            {
                this.forms[formId].updateGrandTotal(this.grandTotal, isMultiCcEnabled);
            };
        }
    },

    /**
     * Requests grand total value on server and updates local forms
     */
    requestUpdateGrandTotal: function()
    {
        new Ajax.Request(RMPagSeguroSiteBaseURL + 'pseguro/ajax/getGrandTotal',
        {
            onSuccess: (function(response)
            {
                if(this.grandTotal != response.responseJSON.total)
                {
                    this.grandTotal =  response.responseJSON.total;
                    var isMultiCcEnabled = this._getSwitch().checked;

                    for(var formId in this.forms)
                    {
                        this.forms[formId].updateGrandTotal(this.grandTotal, isMultiCcEnabled);
                    };
                }

            }).bind(this)
        });
    },

    /**
     * Creates new Magento validators
     */
     _declareValidators: function()
    {
        var self = this;

        Validation.add('validate-rm-pagseguro-cc-number', 'Por favor, insira um número de cartão de crédito válido.', function(value, elm)
        {
            var cardIndex = $(elm).getAttribute("data-card-index");
            return self.forms["cc" + cardIndex]._validateCcNumber(value, elm);
        });

        Validation.add('validate-rm-pagseguro-cc-cid', 'Por favor, verifique o código de segurança do cartão.', function(value, elm)
        {
            var cardIndex = $(elm).getAttribute("data-card-index");
            return self.forms["cc" + cardIndex]._validateCcCid(value, elm);
        });

        Validation.add('validate-rm-pagseguro-cc-total', 'O valor a ser pago no cartão deve ser maior que zero e menor do que o total do pedido.', function(value, elm)
        {
            var cardIndex = $(elm).getAttribute("data-card-index");
            return self.forms["cc" + cardIndex]._validateTotal(value, elm);
        });

        Validation.add('validate-rm-pagseguro-multi-cc-enabled', 'Por favor, preencha os dados dos dois cartões antes de concluir o pedido.', function(value, elm)
        {
            return self._validateMultiCcForms();
        });

        Validation.add('validate-rm-pagseguro-customer-document', 'Por favor, insira um número de CPF válido.', function(value)
        {
            return self._validateCPFNumber(value);
        });
    },

    /**
     * Validates document (CPF) numbers
     * @param String value 
     * @returns Boolean
     */
     _validateCPFNumber: function(value)
    {
        if (value.length != 14) return false;
            
        var repeatedDigits = true;
        value = value.replace(/\D/g,"");
        
        for(var i = 0; i < 10; i++)
        {
            if(value.charAt(i) != value.charAt(i + 1)) { repeatedDigits = false; break; }
        }
        
        if (repeatedDigits) { return false; }
        var sum = 0;
        for (i=0; i < 9; i ++) { sum += parseInt(value.charAt(i)) * (10 - i); }
        
        var rev = 11 - (sum % 11);
        if (rev == 10 || rev == 11) rev = 0;
        if (rev != parseInt(value.charAt(9))) return false;
        
        sum = 0;
        for (i = 0; i < 10; i ++) { sum += parseInt(value.charAt(i)) * (11 - i); }
        rev = 11 - (sum % 11);

        if (rev == 10 || rev == 11) rev = 0;
        if (rev != parseInt(value.charAt(10))) return false;
        
        return true;
    },

    /**
     * Adds PagSeguro lib function calling to queue, avoiding concurrence problems
     * @param String fun 
     * @param Object params 
     */
    queuePSCall: function(fun, params)
    {
        if(typeof this.psFunctionsQueues[fun] === "undefined")
        {
            this.psFunctionsQueues[fun] = 
            {
                "queue" : [],
                "lock"  : false
            }
        }

        this.psFunctionsQueues[fun].queue.push(params);

        this._processPSCallQueue(fun);
    },

    /**
     * Triggers processing of one entry of the queue of PagSeguro lib 
     * function callings
     * @param String fun 
     */
    _processPSCallQueue: function(fun)
    {
        if( typeof this.psFunctionsQueues[fun] === "undefined" ||
            this.psFunctionsQueues[fun].queue.length == 0 ||
            this.psFunctionsQueues[fun].lock !== false )
        {
            return;
        }

        var self = this;
        var originalParams = this.psFunctionsQueues[fun].queue.shift();
        var params = Object.assign({}, originalParams);
        var localCallbacks = 
        {
            success : function() {},
            error   : function() {},
            always  : function() {}
        };

        // overrides success, error and always callback functions
        if(params.success) { localCallbacks.success = params.success; }
        if(params.error)   { localCallbacks.error = params.error; }
        if(params.always)  { localCallbacks.always = params.always; }

        params.success = function(response)
        {
            localCallbacks.success(response);
            
            // if you trust in PagSeguro lib, move this to always callback
            self._resumePSCallQueue(fun);
        };

        params.error = function(response)
        {
            localCallbacks.error(response);

            // if you trust in PagSeguro lib, move this to always callback
            self._resumePSCallQueue(fun);
        };

        params.always = function(response)
        {
            localCallbacks.always(response);
        };

        // set a time limit for the lock
        var thisTimeoutId = setTimeout((function()
        {
            if(this.psFunctionsQueues[fun].lock == thisTimeoutId)
            {
                // avoid late response to do wrong work
                params.success = function(){};
                params.error = function(){};
                params.always = function(){};

                // requeue the request
                if(this.psFunctionsQueues[fun].queue.length == 0)
                {
                    this.queuePSCall(fun, originalParams);
                }

                // resume the queue
                this._resumePSCallQueue(fun);
            }
        }).bind(this), 10000);
        this.psFunctionsQueues[fun].lock = thisTimeoutId;

        PagSeguroDirectPayment[fun](params);
    },

    /**
     * Force the queue processing to continue
     * @param String fun 
     */
    _resumePSCallQueue: function(fun)
    {
        this.psFunctionsQueues[fun].lock = false;
        this._processPSCallQueue(fun);
    },
    
    /**
     * Prints information on browser console log.
     * @param mixed msg 
     */
    debug: function(msg)
    {
        if(this.config.debug)
        {
            console.log(msg);
        }
    }
});

RMPagSeguro_Multicc_CardForm = Class.create
({
    initialize: function(params)
    {
        this.parentObj = params.parentObj;
        this.paymentMethodCode = params.paymentMethodCode;
        this.cardIndex = params.cardIndex;
        this.grandTotal = params.grandTotal;
        this.multiccValidation = params.multiccValidation;
        this.config = Object.assign((params.config ? params.config : {}), RMPagSeguroObj.config);
        
        this.cardData = 
        {
            brand       : "",
            token       : "",
            total       : "",
            number      : "",
            cid         : "",
            expMonth    : "",
            expYear     : "",
            owner       : "",
            owner_doc   : "",
            dob_day     : "",
            dob_month   : "",
            dob_year    : "",
            installments: ""
        };
        this.cardMetadata = {};
        this.requestLocks = 
        {
            brand       : false,
            token       : false,
            installments: false
        };
        this.eventListeners = {};
        this.cardDataBinds = {};

        if(params.importedData && params.oldGrandTotal)
        {
            params.importedData.oldGrandTotal = params.oldGrandTotal;
        }

        this._addFieldObservers();
        this._addHTMLCardDataBinds();
        this._importData(params.importedData);
        
        // all forms are initialized disabled
        this.state = "";
        this.disable();
    },

    /**
     * Adds event listener and data binds to form fields
     */
    _addFieldObservers: function()
    {
        // captures the form inputs fulfillments
        this._addFieldEventListener("total",            "change", function(field){this.setCardData("total", (field.getValue() ? parseFloat(field.getValue().replaceAll(".", "").replace(",", ".").replace(/^\s+|\s+$/g,'')) : 0));});
        this._addFieldEventListener("number",           "change", function(field){this.setCardData("number", field.getValue().replace(/\D/g,''));});
        this._addFieldEventListener("cid",              "change", function(field){this.setCardData("cid", field.getValue().replace(/\D/g,''));});
        this._addFieldEventListener("expiration_mth",   "change", function(field){this.setCardData("expMonth", field.getValue());});
        this._addFieldEventListener("expiration_yr",    "change", function(field){this.setCardData("expYear", field.getValue());});
        this._addFieldEventListener("owner",            "change", function(field){this.setCardData("owner", field.getValue());});
        this._addFieldEventListener("owner_document",   "change", function(field){this.setCardData("owner_doc", field.getValue());});
        this._addFieldEventListener("dob_day",          "change", function(field){this.setCardData("dob_day", field.getValue());});
        this._addFieldEventListener("dob_month",        "change", function(field){this.setCardData("dob_month", field.getValue());});
        this._addFieldEventListener("dob_year",         "change", function(field){this.setCardData("dob_year", field.getValue());});

        // masks
        this._addFieldEventListener("total",            "keydown", this._disallowNotNumbers);
        this._addFieldEventListener("total",            "keyup",   this._formatCurrencyInput);
        this._addFieldEventListener("total",            "blur",    this._formatCurrencyInput);
        this._addFieldEventListener("number",           "keydown", this._disallowNotNumbers);
        this._addFieldEventListener("number",           "keyup",   this._formatCardNumber);
        this._addFieldEventListener("number",           "change",  this._formatCardNumber);
        this._addFieldEventListener("cid",              "keydown", this._disallowNotNumbers);
        this._addFieldEventListener("dob_day",          "keydown", this._disallowNotNumbers);
        this._addFieldEventListener("dob_month",        "keydown", this._disallowNotNumbers);
        this._addFieldEventListener("dob_year",         "keydown", this._disallowNotNumbers);
        this._addFieldEventListener("owner_document",   "keydown", this._disallowNotNumbers);
        this._addFieldEventListener("owner_document",   "keyup",   this._formatDocumentInput);
        this._addFieldEventListener("owner_document",   "blur",    this._formatDocumentInput);
        
        // custom listeners
        this._addFieldEventListener("total",            "keyup",  this._instantReflectTotalInProgressBar);
        this._addFieldEventListener("number",           "keyup",  this._consultCardBrandOnPagSeguro);
        this._addFieldEventListener("installments",     "change", this._updateInstallmentsdata);
        
        // logic data binds
        this.addCardDataBind("total",             this._consultInstallmentsOnPagSeguro);
        this.addCardDataBind("total",             this._updateTotalHTMLOnSetValue);
        this.addCardDataBind("number",            this._createCardTokenOnPagSeguro);
        this.addCardDataBind("number",            this._updateFormmatedNumberMetadata);
        this.addCardDataBind("number",            this._verifyIfCardBinChanged);
        //this.addCardDataBind("number",            this._consultInstallmentsOnPagSeguro);
        this.addCardDataBind("brand",             this._updateBrandOnHTML);
        this.addCardDataBind("brand",             this._consultInstallmentsOnPagSeguro);
        this.addCardDataBind("cid",               this._createCardTokenOnPagSeguro);
        this.addCardDataBind("expMonth",          this._createCardTokenOnPagSeguro);
        this.addCardDataBind("expYear",           this._createCardTokenOnPagSeguro);
        this.addCardDataBind("installments",      this._updateInstallmentsMetadata);
        this.addCardDataBind("token",             this._updateTokenOnHTML);
        this.addCardDataBind("metadata_cid_size", this._updateCidMaskOnHTML);

        // triggers fields validation on blur
        this.getHTMLFormInputsAndSelects().each(function(element)
        {
            element.observe("blur", function(){ Validation.validate(element); });
        });
    },

    /**
     * Searches for elements with declared data binds on HTML
     */
    _addHTMLCardDataBinds: function()
    {
        // raw data
        var fieldSelector = 
            "#payment_form_" + this.paymentMethodCode + " " +
                "li[data-card-index=" + this.cardIndex + "] " +
                    "[data-bind=card-data]";
        
        $$(fieldSelector).each((function(element)
        {
            var cardData = $(element).getAttribute("data-card-field");

            if(cardData)
            {
                this.addCardDataBind(cardData, function(newValue)
                {
                    $(element).update(newValue);
                });
            }

        }).bind(this));

        // metada data
        var fieldSelector = 
            "#payment_form_" + this.paymentMethodCode + " " +
                "li[data-card-index=" + this.cardIndex + "] " +
                    "[data-bind=card-metadata]";
        
        $$(fieldSelector).each((function(element)
        {
            var cardMetadata = $(element).getAttribute("data-card-field");

            if(cardMetadata)
            {
                this.addCardDataBind("metadata_" + cardMetadata, function(newValue)
                {
                    $(element).update(newValue);
                });
            }

        }).bind(this));
    },

    /**
     * Observes card number field to consult card brand on PagSeguro web service
     * @param DOMElement field 
     */
    _consultCardBrandOnPagSeguro: function(field)
    {
        var fieldValue = field.getValue().replace(/\D/g,'');
        
        // updates only if there are at least 6 digits and
        if(fieldValue.length >= 6 && !this.getCardData("brand") && !this._hasRequestLock("brand"))
        {
            // adds expiring time to this lock before using it, 
            // because of the PagSeguro lib unreliability
            this._setupSyncLock("brand");
            this._debug("Solicitando bandeira do cartão de crédito");

            PagSeguroDirectPayment.getBrand
            ({
                cardBin: fieldValue.substring(0, 6),
                success: (function(response)
                {
                    if(response && response.brand)
                    {
                        this.setCardData("brand", response.brand.name);
                        this.setCardMetadata("cid_size", response.brand.cvvSize);
                        this.setCardMetadata("validation_algorithm", response.brand.validationAlgorithm);
                        this._debug("Bandeira armazenada com sucesso");
                    }
                    else
                    {
                        this.setCardData("brand", "");
                        this.setCardMetadata("cid_size", "");
                        this.setCardMetadata("validation_algorithm", "");
                        this._debug("Bandeira não encontrada");
                    }

                    // this validation must be here, to ensure that its going to run after 
                    // the set data (not happenned when code was placed on complete callback)
                    if(field !== document.activeElement)
                    {
                        Validation.validate(field);
                    }

                    this._createCardTokenOnPagSeguro();

                }).bind(this),
                error: (function()
                {
                    this.setCardData("brand", "");
                    this.setCardMetadata("cid_size", "");
                    this.setCardMetadata("validation_algorithm", "");
                    this._debug("Bandeira não encontrada");

                    // this validation must be here, to ensure that its going to run after 
                    // the set data (not happenned when code was placed on complete callback)
                    if(field !== document.activeElement)
                    {
                        Validation.validate(field);
                    }

                }).bind(this),
                complete: (function()
                {
                    this._removeSyncLock("brand");
                    this.setCardMetadata("bin_calculated_for", fieldValue.substring(0, 6));

                }).bind(this)
            });
        }
        // clears brand if the card data became smaller than 6 digits
        else if(fieldValue.length < 6)
        {
            this.setCardData("brand", "");
            this.setCardMetadata("cid_size", "");
            this.setCardMetadata("validation_algorithm", "");
            this.setCardMetadata("bin_calculated_for", "");
        }
    },

    /**
     * Observes card brand data to consult installments on 
     * PagSeguro web service (could update grand total before)
     */
    _consultInstallmentsOnPagSeguro: function()
    {
        if(!this.getCardData("total"))
        {
            this._clearInstallmentsOptions("Informe o valor a ser pago neste cartão para calcular as parcelas.");
            return;
        }

        if(!this.getCardData("brand"))
        {
            this._clearInstallmentsOptions("Preencha os dados do cartão para calcular as parcelas.");
            this._insert1xInstallmentsOption();
            return;
        }

        this._debug("Solicitando parcelas para o valor de " + this.getCardData("total").toFixed(2));
        this._clearInstallmentsOptions("Consultando demais parcelas na PagSeguro...");
        this.setCardMetadata("installments_description", "Buscando parcelas na PagSeguro...");
        //this._insert1xInstallmentsOption();

        var maxInstallmentNoInterest = this.config.installment_free_interest_minimum_amt === "0" ? 0 : "";
        if (this.config.installment_free_interest_minimum_amt > 0) {
            maxInstallmentNoInterest = this.getCardData("total").toFixed(2) / this.config.installment_free_interest_minimum_amt;
            maxInstallmentNoInterest = Math.floor(maxInstallmentNoInterest);
            maxInstallmentNoInterest = (maxInstallmentNoInterest > 1) ? maxInstallmentNoInterest : '';
        }

        var params =
        {
            brand: this.getCardData("brand"),
            amount: this.getCardData("total").toFixed(2),
            success: this._populateInstallments.bind(this),
            error: this._populateSafeInstallments.bind(this),
            maxInstallmentNoInterest: maxInstallmentNoInterest
        };

        this.parentObj.queuePSCall("getInstallments", params);
    },

    /**
     * Observes card number field to consult card token on PagSeguro web service
     */
    _createCardTokenOnPagSeguro: function()
    {
        var ccNumberField = this._getFieldElement("number");
        var ccCidField = this._getFieldElement("cid");
        var ccNumValidation = Validation.get("validate-rm-pagseguro-cc-number");
        var ccCidValidation = Validation.get("validate-rm-pagseguro-cc-cid");
        
        if( ccNumValidation.test($F(ccNumberField), ccNumberField) && 
            ccCidValidation.test($F(ccCidField), ccCidField) && 
            !this.requestLocks.token && 
            this.getCardData("brand") && this.getCardData("cid") && 
            this.getCardData("expMonth") && this.getCardData("expYear") )
        {
            // TO DO: add expiring time to this lock before using it, 
            // because of the PagSeguro lib instability
            //this.requestLocks.token = true;
            this._debug("Solicitando token do cartão");

            PagSeguroDirectPayment.createCardToken
            ({
                cardNumber      : this.getCardData("number"),
                brand           : this.getCardData("brand"),
                cvv             : this.getCardData("cid"),
                expirationMonth : this.getCardData("expMonth"),
                expirationYear  : this.getCardData("expYear"),
                success: (function(response)
                {
                    if(response && response.card && response.card.token)
                    {
                        this.setCardData("token", response.card.token);
                        this._debug("Token armazenado com sucesso");
                    }

                }).bind(this),
                error: (function(response)
                {
                    this.setCardData("token", "");

                    var errorsDesc = [];
                    for(var idx in response.errors)
                    {
                        errorsDesc.push(Translator.translate(response.errors[idx]));
                    }
                    
                    var message = "Por favor, reveja os dados do seu cartão, incluindo data e código de segurança.";
                    
                    if(errorsDesc.length > 0)
                    {
                        message = "Por favor, reveja os dados do seu cartão: " + errorsDesc.join("; ") + ".";
                    }
                    
                    alert(message);
                    this._debug("Erro na tentativa de obter o token do cartão:");
                    this._debug(response);

                }).bind(this),
                complete: (function()
                {
                    //this.requestLocks.token = false;

                }).bind(this)
            });
        }
        // if its not good enought to create a card token,
        // checks if its possible to consult the card brand 
        else if(this.getCardData("number").length >= 6)
        {
            this._consultCardBrandOnPagSeguro(ccNumberField);
        }
        // if the card number was cleared, clear the brand too
        else if(this.getCardData("number").length < 6)
        {
            this.setCardData("brand", "");
        }
    },

    /**
     * Callback function that populates installments
     * select box with returned options 
     * @param XMLHttpRequest response 
     */
    _populateInstallments: function(response)
    {
        var remoteInstallments = Object.values(response.installments)[0];

        // redundant verification, beacause of the possibility of callback
        // crossover on PagSeguro lib
        if( remoteInstallments.length > 0 &&
            this.getCardData("total").toFixed(2) != remoteInstallments[0].totalAmount
        ) {
            this._debug("Valor das parcelas difere do total do cartão: " + remoteInstallments[0].totalAmount + " | " + this.getCardData("total"));
            return;
        }

        this._debug("Preenchendo as parcelas para o valor de " + this.getCardData("total").toFixed(2));

        
        var maxInstallments = this.config.installment_limit;
        var selectbox = this._clearInstallmentsOptions("Selecione a quantidade de parcelas");

        for(var x = 0; x < remoteInstallments.length; x++)
        {
            if(maxInstallments && maxInstallments <= x)
            {
                break;
            }

            var qty = remoteInstallments[x].quantity;
            var value =  remoteInstallments[x].installmentAmount;
            var formmatedValue = value.toFixed(2).replace('.',',');
            var text = qty + "x de R$" + formmatedValue;
            
            text += remoteInstallments[x].interestFree
                        ? " sem juros"
                        : " com juros";
            
            text += this.config.show_total
                        ? " (total R$" + (value * qty).toFixed(2).replace('.',',') + ")"
                        : "";
            
            var option = new Element('option', {"value": qty + "|" + value.toFixed(2)});
            option.update(text);

            if(this.getCardData("installments") == qty)
            {
                option.setAttribute("selected", true);
            }

            selectbox.add(option);
        }

        if(!this.config.force_installments_selection)
        {
            this._removeEmptyInstallmentsOptions();

            if(!this.getCardData("installments"))
            {
                this.setCardData("installments", 1);
            }
        }

        // forces data binds to run
        if(this.getCardData("installments"))
        {
            this._updateInstallmentsMetadata(); 
        }
    },

    /**
     * Callback function that populates installments 
     * select box when there isn't a response from server
     * @param XMLHttpRequest response 
     */
    _populateSafeInstallments: function(response)
    {
        this._clearInstallmentsOptions("Falha ao obter demais parcelas junto ao pagseguro");
        this._insert1xInstallmentsOption();
        this._updateInstallmentsMetadata();
        
        console.error('Somente uma parcela será exibida. Erro ao obter parcelas junto ao PagSeguro:');
        console.error(response);
    },

    /**
     * Removes all options from installments select box,
     * except the one with empty value
     * 
     * @return DOMElement
     */
    _clearInstallmentsOptions(emptyOptionText = false)
    {
        var field = this._getFieldElement("installments");
        
        for(var i = 0; i < field.length; i++)
        {
            if(field.options[i].value != "")
            {
                field.remove(i);
                i--;
            }
        }

        if(field.options.length == 0)
        {
            field.add(new Element('option', {"value": ""}));
            field.options[0].text = "Por favor, preencha os dados do cartão para calcular as parcelas.";
        }

        if(emptyOptionText)
        {
            field.options[0].text = emptyOptionText;
        }

        return field;
    },

    /**
     * Removes all the empty options from installments selectbox,
     * if there are not empty options available
     * 
     * @return DOMElement
     */
    _removeEmptyInstallmentsOptions()
    {
        var field = this._getFieldElement("installments");
        var notEmptyAvailble = false;

        for(var i = 0; i < field.length; i++)
        {
            if(field.options[i].value != "")
            {
                notEmptyAvailble = true;
            }
        }

        if(!notEmptyAvailble)
        {
            return field;
        }
         
        for(var i = 0; i < field.length; i++)
        {
            if(field.options[i].value == "")
            {
                field.remove(i);
                i--;
            }
        }
 
        return field;
    },

    /**
     * Creates 1x installment option and inserts into selectbox
     * 
     * @return DOMElement
     */
    _insert1xInstallmentsOption()
    {
        var field = this._getFieldElement("installments");
        var total = this.getCardData("total").toFixed(2);

        var option = document.createElement('option');
        option.text = "1x de R$" + total.toString().replace('.',',') + " sem juros";
        option.selected = true;
        option.value = "1|" + total;
        field.add(option);

        return field;
    },

    /**
     * Captures digits inserted in the total field to update
     * progress bar
     * @param DomElement field Total field element
     */
    _instantReflectTotalInProgressBar: function(field)
    {
        var value = field.getValue() ? parseFloat(field.getValue().replaceAll(".", "").replace(",", ".").replace(/^\s+|\s+$/g,'')) : 0;
        this._recalcProgressBarFulfillment(value);
    },

    /**
     * Visual adjustment of the progress bar and remaining value
     * (based on total value)
     * @param float value
     */
    _recalcProgressBarFulfillment: function(value)
    {
        var percent = 100;
        var remainingValue = (this.grandTotal > value)
                                    ? ((value > 0) ? this.grandTotal - value : this.grandTotal)
                                    : 0;

        if(this.cardIndex == 1)
        {
            percent = value * 100 / this.grandTotal;
        }

        this.setCardMetadata("remaining_total", "R$" + this._formatCurrency(remainingValue * 100));
        this._updateProgressBar(percent);
    },

    /**
     * Visual adjustment of the progress bar (based on percentual value)
     * @param float percent
     */
    _updateProgressBar: function(percent)
    {
        if(percent > 100) percent = 100;
        
        var progress = this._getFieldElement("progress_bar")
                        .select("[data-role=progress]")
                        .first();

        $(progress).setStyle({width: percent.toFixed(2) + "%"});
    },

    /**
     * Verifies if the grand total changed 
     * @param float|string newValue 
     */
    updateGrandTotal: function(newValue, isMultiCcEnabled)
    {
        if(newValue != this.grandTotal)
        {
            this.grandTotal = newValue;
            
            if(!isMultiCcEnabled && this.state == "enabled")
            {
                this.setCardData("total", this.grandTotal);
            }
        }
    },

    /**
     * Getter and setter for card data
     */
    getCardData: function(data = false)
    {
        if(data === false)
        {
            return this.cardData;
        }

        if(this.cardData[data])
        {
            return this.cardData[data];
        }

        return false;
    },
    setCardData: function(data, newValue)
    {
        if(typeof this.cardData[data] !== "undefined")
        {
            var previousValue = this.cardData[data];
            this.cardData[data] = newValue;
            
            if(this.cardDataBinds[data])
            {
                this.cardDataBinds[data].each((function(callback)
                {
                    callback.bind(this)(newValue, previousValue);

                }).bind(this));
            }
        }

        return this;
    },

    /**
     * Getter and setter for card metadata
     */
    getCardMetadata: function(data = false)
    {
        if(data === false)
        {
            return this.cardMetadata;
        }

        if(this.cardMetadata[data])
        {
            return this.cardMetadata[data];
        }

        return false;
    },
    setCardMetadata: function(data, newValue)
    {
        this.cardMetadata[data] = newValue;
        
        if(this.cardDataBinds["metadata_" + data])
        {
            var previousValue = "";
            
            if(typeof this.cardMetadata[data] !== "undefined")
            {
                previousValue = this.cardMetadata[data];
            }

            this.cardDataBinds["metadata_" + data].each((function(callback)
            {
                callback.bind(this)(newValue, previousValue);

            }).bind(this));
        }

        return this;
    },

    /**
     * Adds callback listener to setCardData 
     * @param string data 
     * @param function callback 
     */
    addCardDataBind: function(data, callback)
    {
        if(!this.cardDataBinds[data])
        {
            this.cardDataBinds[data] = [];
        }

        this.cardDataBinds[data].push(callback);
    },

    /**
     * Updates the brand image flag on HTML form
     * @param string newBrand 
     */
    _updateBrandOnHTML(newBrand)
    {
        if(newBrand)
        {
            var urlPrefix = 'https://stc.pagseguro.uol.com.br/';
            if (this.config.stc_mirror) {
                urlPrefix = 'https://stcpagseguro.ricardomartins.net.br/';
            }
            var imageUrl = urlPrefix + "/public/img/payment-methods-flags/68x30/" + newBrand + ".png";
            this._getFieldElement("number").setStyle({ "background-image": "url('" + imageUrl + "')" });
            this._getFieldElement("brand").setValue(newBrand);
        }
        else
        {
            this._getFieldElement("number").setStyle({ "background-image": "none" });
            this._getFieldElement("brand").setValue("");
        }

        /*
        // update HTML
        if(newBrand)
        {
            var image = new Element("img", 
            {
                src: "https://stc.pagseguro.uol.com.br/public/img/payment-methods-flags/42x20/" + newBrand + ".png",
                alt: newBrand, 
                alt: newBrand
            });
            this._getFieldElement("card_brand").update().appendChild(image);
        }
        else
        {
            this._getFieldElement("card_brand").update();
        }
        */
    },

    /**
     * Updates the brand image flag on HTML form
     * @param string newValue 
     */
    _updateTotalHTMLOnSetValue(newValue)
    {
        var field = this._getFieldElement("total");
        
        if(newValue != field.getValue().replace(",", "."))
        {
            field.setValue(this._formatCurrency(newValue * 100));
        }

        this._recalcProgressBarFulfillment(newValue);
    },

    /**
     * Updates the token on HTML form hidden input
     * @param string newToken 
     */
    _updateTokenOnHTML(newToken)
    {
        // update HTML
        this._getFieldElement("token").setValue(newToken);
    },

    /**
     * Updates the CID field mask
     * @param string newValue 
     */
     _updateCidMaskOnHTML(newValue)
    {
        var cidSize = parseInt(newValue);
        var placeholder = "***";

        if(cidSize > 0)
        {
            placeholder = "";

            for(var i = 0; i < cidSize; i++)
            {
                placeholder += "*";
            }
        }

        // update HTML
        this._getFieldElement("cid").setAttribute("placeholder", placeholder);
    },

    /**
     * Splits installments value and updates card data
     * @param DOMElement field 
     */
    _updateInstallmentsdata(field)
    {
        var splitedValue = field.getValue().split("|");
        this.setCardData("installments", splitedValue[0]);
    },

    /**
     * Generates installments metadata
     */
    _updateInstallmentsMetadata()
    {
        var field = this._getFieldElement("installments");
        this.setCardMetadata("installments_description", field.options[field.selectedIndex].text);
    },

    /**
     * Generates formmated number metadata
     * @param DOMElement field 
     */
    _updateFormmatedNumberMetadata(newValue)
    {
        var formmatedNumber = "";

        if(newValue.length >= 4) { formmatedNumber += newValue.substring(0, 4); }
        if(newValue.length >= 8) { formmatedNumber += "*".repeat(4); }
        if(newValue.length >= 12) { formmatedNumber += "*".repeat(4) + newValue.substring(12); }
        
        this.setCardMetadata("formmated_number", formmatedNumber);
    },

    /**
     * Checks if the card bin has changed on card number update
     * @param String newValue
     * @param String oldValue
     */
     _verifyIfCardBinChanged(newValue, oldValue)
    {
        if( newValue.substring(0, 6) != oldValue.substring(0, 6) && 
            newValue.substring(0, 6) != this.getCardMetadata("bin_calculated_for")
        ) {
            this.setCardData("brand", "");
            this._consultCardBrandOnPagSeguro(this._getFieldElement("number"));
        }
    },

    /**
     * Returns a DOM element of an input or select
     * present in the form
     */
    _getFieldElement: function(fieldRef)
    {
        var fieldId = 
            this.paymentMethodCode + 
            "_cc" + this.cardIndex + 
            "_" + fieldRef;
        
        return $(fieldId);
    },

    /**
     * Adds an event listener to a form field element
     */
    _addFieldEventListener: function(fieldRef, eventName, callback)
    {
        var field = fieldRef;

        if(typeof fieldRef === 'string')
        {
            field = this._getFieldElement(fieldRef);
        }

        if(field && field.id)
        {
            if(!this.eventListeners[field.id])
            {
                this.eventListeners[field.id] = {};
            }

            if(!this.eventListeners[field.id][eventName])
            {
                this.eventListeners[field.id][eventName] = [];
            }

            var callbackRef = callback.bind(this, field);
            field.observe(eventName, callbackRef);

            this.eventListeners[field.id][eventName].push(callbackRef);
        }
    },

    /**
     * Shows / hides card payment fields in the form
     */
    _getCommongFields: function()
    {
        var fieldSelector = 
            "#payment_form_" + this.paymentMethodCode + " " +
                "li" + 
                "[data-field-profile=default]" +
                "[data-card-index=" + this.cardIndex + "]";
        
        return $$(fieldSelector);
    },
    showCommonFields: function()
    {
        this._getCommongFields().each(Element.show);
    },
    hideCommonFields: function()
    {
        this._getCommongFields().each(Element.hide);
    },

    /**
     * Shows / hides summary
     */
    _getSummaryLine: function()
    {
        var fieldId = 
            this.paymentMethodCode + 
            "_cc" + this.cardIndex + 
            "_summary_line";
        
        return $(fieldId);
    },
    showSummary()
    {
        if(typeof this.config._summary == "undefined" || this.config._summary !== false)
        {
            this._getSummaryLine().show();
        }
    },
    hideSummary()
    {
        this._getSummaryLine().hide();
    },

    /**
     * Shows / hides summary
     */
    _getTotalLine: function()
    {
        var fieldId = 
            this.paymentMethodCode + 
            "_cc" + this.cardIndex + 
            "_total_line";
        
        return $(fieldId);
    },
    showTotal()
    {
        this._getTotalLine().show();
    },
    hideTotal()
    {
        this._getTotalLine().hide();
    },

    /**
     * Shows form, so that user can edit 
     * its fields
     */
    openEditMode: function()
    {
        this.hideSummary();
        this.showTotal();
        this.showCommonFields();
    },

    /**
     * Closes form edition, showing just its summary
     */
    closeEditMode: function()
    {
        this.hideCommonFields();
        this.hideTotal();
        this.showSummary();
    },
    
    /**
     * Turns on form functionalities on interface
     */
    enable: function()
    {
        if(this.state == "disabled")
        {
            this.closeEditMode();
            this.state = "enabled";
        }

        if(!this.getCardData("total"))
        {
            this.setCardData("total", 0);
        }
    },

    /**
     * Turns off form functionalities on interface
     */
    disable: function()
    {
        this.hideCommonFields();
        this.hideTotal();
        this.hideSummary();

        this.state = "disabled";
    },


    /**
     * Gets the summary box
     */
    getSummaryBox: function()
    {
        var fieldId = 
            this.paymentMethodCode + 
            "_cc" + this.cardIndex + 
            "_summary_box";
        
        return $(fieldId);
    },

    /**
     * Validates HTML form fields
     */
    validate: function()
    {
        var valid = true;

        this.getHTMLFormInputsAndSelects().each(function(element)
        {
            if($(element).readAttribute("name"))
            {
                valid &= Validation.validate(element);
            }

        });

        return valid;
    },

    /**
     * Retrieves all the HTML form fields
     */
    getHTMLFormInputsAndSelects: function()
    {
        return $$
        (
            "li[data-card-index=" + this.cardIndex + "] input[type=text]",
            "li[data-card-index=" + this.cardIndex + "] input[type=number]",
            "li[data-card-index=" + this.cardIndex + "] input[type=tel]",
            "li[data-card-index=" + this.cardIndex + "] input[type=email]",
            "li[data-card-index=" + this.cardIndex + "] select"
        );
    },

    /**
     * Avoids not number chars
     * @param DOMElement field 
     * @param Event event 
     */
    _disallowNotNumbers: function(field, event)
    {
        if (event.key && !/[0-9\/]+/.test(event.key) && event.key.length === 1)
        {
            event.preventDefault();
        }
    },

    /**
     * Money format for value inputs
     * @param DOMElement field 
     */
    _formatCurrencyInput: function(field)
    {
        field.setValue(this._formatCurrency(field.getValue()));
    },

    /**
     * Money format (in cents)
     * @param String field 
     * @return String 
     */
    _formatCurrency: function(value)
    {
        // ensures that the floating point representation 
        // will be converted into an integer
        if(typeof value === "number")
        {
            value = value.toFixed(0);
        }

        if(typeof value !== "string")
        {
            value = value.toString();
        }

        var unformattedValue = value.replace(/\D/g,'');
        var formattedValue = "";
        
        // remove zeros on left side
        while(unformattedValue.length > 0 && unformattedValue.substring(0, 1) == 0)
        {
            unformattedValue = unformattedValue.substring(1);
        }

        if(unformattedValue.length == 0)
        {
            return "0,00";
        }

        // format decimals separator
        if(unformattedValue.length == 1)
        {
            formattedValue = "0,0" + unformattedValue;
        }
        else if(unformattedValue.length == 2)
        {
            formattedValue = "0," + unformattedValue;
        }
        else if(unformattedValue.length > 2)
        {
            formattedValue = unformattedValue.substring(0, unformattedValue.length - 2) + 
                             "," + 
                             unformattedValue.substring(unformattedValue.length - 2);
        }

        // format thousands separator
        var separatorIndex = 5;
        var separatorCounter = 0;

        while(unformattedValue.length > separatorIndex)
        {
            formattedValue = formattedValue.substring(0, unformattedValue.length - separatorIndex) +
                                "." + 
                                formattedValue.substring(unformattedValue.length - separatorIndex);

            separatorIndex += 3;
            separatorCounter++;
        }
        
        return formattedValue;
    },

    /**
     * Formats card number
     * @param DOMElement field 
     */
     _formatCardNumber: function(field)
    {
        var digits = field.getValue().replace(/\D/g,'');
        var formattedValue = "";
        var lastIndex = 0;
        
        if(digits.length <= 4)
        {
            field.setValue(digits);
            return;
        }
        
        formattedValue += digits.substring(0, 4);
        lastIndex = 4;

        while(digits.length > lastIndex)
        {
            formattedValue += " " + digits.substring(lastIndex, lastIndex + 4);
            lastIndex += 4;
        }
        
        field.setValue(formattedValue);
    },

    /**
     * CPF mask for value inputs
     * @param DOMElement field 
     */
    _formatDocumentInput: function(field)
    {
        var digits = field.getValue().replace(/\D/g,'');
        var formattedValue = "";
        var lastIndex = 0;
        
        if(digits.length <= 3)
        {
            return digits;
        }
        
        formattedValue += digits.substring(0, 3) + ".";
        lastIndex = 3;

        if(digits.length > 6) { formattedValue += digits.substring(3, 6) + "."; lastIndex = 6; }
        if(digits.length > 9) { formattedValue += digits.substring(6, 9) + "-"; lastIndex = 9; }
        
        formattedValue += digits.substring(lastIndex);
        
        field.setValue(formattedValue);
    },

    /**
     * Verifies if the card total is a number between 0 and the grand total
     * @return boolean
     */
    _validateTotal: function(value, elm)
    {
        var total = (value != "")
                ? parseFloat(value.replaceAll(".", "").replace(",", ".").replace(/^\s+|\s+$/g,''))
                : 0;

        if(total <= 0)
        {
            return false;
        }

        if(total >= this.grandTotal)
        {
            return false;
        }

        return true;
    },

    /**
     * Verifies if the card number is valid, testing it against the indicated 
     * algorithm by PagSeguro web service
     * @return boolean
     */
     _validateCcNumber: function(value)
    {
        value = value.replace(/\D/g,'');

        if(!this.getCardData("brand"))
        {
            return false;
        }

        var valid = true;

        switch(this.getCardMetadata("validation_algorithm"))
        {
            case "LUHN":
                valid = this._validateLuhn(value);
                break;
            
            default:
                valid = value.length >= 6;
        }

        return valid;
    },

    /**
     * Verifies if the cid is valid, accordingly to PagSeguro validation
     * @return boolean
     */
     _validateCcCid: function(value)
    {
        value = value.replace(/\D/g,'');

        if( this.getCardMetadata("cid_size") &&
            this.getCardMetadata("cid_size") != value.length
        ) {
            return false;
        }

        return true;
    },

    /**
     * Tests if a number passes the Luhn algorithm test
     * @return boolean
     */
    _validateLuhn: function(num)
    {
        var digit, digits, j, len, odd, sum;
	    odd = true;
	    sum = 0;
	    digits = (num + '').split('').reverse();
        for (j = 0, len = digits.length; j < len; j++)
        {
            digit = digits[j];
            digit = parseInt(digit, 10);
            if ((odd = !odd))
            {
                digit *= 2;
            }
            if (digit > 9)
            {
                digit -= 9;
            }
            sum += digit;
        }
        return sum % 10 === 0;
    },

    _debug: function(msg)
    {
        if(typeof msg == "string")
        {
            this.parentObj.debug("[Cartao " + this.cardIndex + "] " + msg);
            return;
        }

        this.parentObj.debug("[Cartao " + this.cardIndex + "]");
        this.parentObj.debug(msg);
    },

    /**
     * Setups a lock to avoid request duplicity
     * @param String lockName 
     */
    _setupSyncLock: function(lockName, timeout = 2000)
    {
        if(typeof this.requestLocks[lockName] === "undefined")
        {
            return;
        }

        var thisTimeoutId = setTimeout((function()
        {
            if(this.requestLocks[lockName] == thisTimeoutId)
            {
                this._removeSyncLock(lockName);
            }
        }).bind(this), timeout);

        this.requestLocks[lockName] = thisTimeoutId;
        this._debug("Travando consultas do tipo: " + lockName);
    },

    /**
     * Frees a request lock
     * @param String lockName 
     */
     _removeSyncLock(lockName)
    {
        if(typeof this.requestLocks[lockName] === "undefined")
        {
            return;
        }

        if(this.requestLocks[lockName] === false)
        {
            return;
        }

        this.requestLocks[lockName] = false;
        this._debug("Liberando consultas do tipo: " + lockName);
    },

    /**
     * Verifies if there is a request lock
     * @param String lockName 
     */
    _hasRequestLock(lockName)
    {
        if(typeof this.requestLocks[lockName] === "undefined")
        {
            return false;
        }

        return this.requestLocks[lockName];
    },

    /**
     * Imports data from older form control instance
     * @param Object importedData 
     */
     _importData: function(importedData)
    {
        if(!importedData)
        {
            return;
        }

        // fulfills the HTML form
        this._getFieldElement("number").setValue(importedData.cardData.number);
        this._getFieldElement("total").setValue(importedData.cardData.total);
        this._getFieldElement("cid").setValue(importedData.cardData.cid);
        this._getFieldElement("expiration_mth").setValue(importedData.cardData.expMonth);
        this._getFieldElement("expiration_yr").setValue(importedData.cardData.expYear);
        this._getFieldElement("owner").setValue(importedData.cardData.owner);
        
        var ownerDoc = this._getFieldElement("owner_document");
        if(ownerDoc) ownerDoc.setValue(importedData.cardData.owner_doc);

        var ownerDob = this._getFieldElement("dob_day");
        if(ownerDob)
        {
            ownerDob.setValue(importedData.cardData.dob_day);
            this._getFieldElement("dob_month").setValue(importedData.cardData.dob_month);
            this._getFieldElement("dob_year").setValue(importedData.cardData.dob_year);
        }        

        // formats card number
        this._formatCardNumber(this._getFieldElement("number"));
        this._formatCurrencyInput(this._getFieldElement("total"));
        
        // copies data and metadata
        for(var dataIdx in importedData.cardData)
        {
            this.setCardData(dataIdx, importedData.cardData[dataIdx]);
        }

        for(var dataIdx in importedData.cardMetadata)
        {
            this.setCardMetadata(dataIdx, importedData.cardMetadata[dataIdx]);
        }

        if( importedData.cardData.installments != "" && 
            importedData.oldGrandTotal && 
            importedData.oldGrandTotal != this.grandTotal )
        {
            alert("Atenção! O valor total do seu pedido foi alterado, por favor confira os valores das suas parcelas.");
        }
    },

    /**
     * Exports object data for persistency
     * @return Object
     */
     exportData: function()
    {
        return {
            "cardData"     : this.cardData,
            "cardMetadata" : this.cardMetadata
        };
    },
    
    /**
     * Prepares object to be deleted
     */
    destroy: function()
    {
        for(var fieldId in this.eventListeners)
        {
            for(var eventName in this.eventListeners[fieldId])
            {
                var callback = this.eventListeners[fieldId][eventName];

                $(fieldId).stopObserving(eventName, callback);
            }
        }
    }
});