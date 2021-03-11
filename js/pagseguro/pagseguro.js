/**
 * PagSeguro Transparente para Magento
 * @author Ricardo Martins <ricardo@ricardomartins.net.br>
 * @link https://github.com/r-martins/PagSeguro-Magento-Transparente
 * @version 3.8.2
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
        PagSeguroDirectPayment.getInstallments({
            amount: grandTotal,
            brand: brandName,
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

        if(ccNum.length >= 6){
            if (typeof RMPagSeguroObj.cardBin != "undefined" && currentBin == RMPagSeguroObj.cardBin) {
                if(typeof RMPagSeguroObj.brand != "undefined"){
                    $('card-brand').innerHTML = '<img src="https://stc.pagseguro.uol.com.br/public/img/payment-methods-flags/' +flag + '/' + RMPagSeguroObj.brand.name + '.png" alt="' + RMPagSeguroObj.brand.name + '" title="' + RMPagSeguroObj.brand.name + '"/>';
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

                        $('card-brand').innerHTML = '<img src="https://stc.pagseguro.uol.com.br/public/img/payment-methods-flags/' +flag + '/' + psresponse.brand.name + '.png" alt="' + psresponse.brand.name + '" title="' + psresponse.brand.name + '"/>';
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
        this.sequentialNumber = null;

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
     * Create class instances to control each card form
     * and show first one for conventional payment flow
     */
    _initForms: function()
    {
        this.forms["cc1"] = new RMPagSeguro_Multicc_CardForm
        ({
            cardIndex         : 1, 
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
            this._disableMultiCc();
        }

        // hook due to OSC bug (wrong grand total by not considering default shipping method value):
        // if its the first object of the page, we request a grand total update
        if(this.sequentialNumber == 1)
        {
            this.requestUpdateGrandTotal();
            this._declareValidators();
        }
    },

    /**
     * Initialize all navigation observers
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
        // TO DO !!! this kind of link must be inside a form
        this.forms["cc1"].getSummaryBox().observe('click', this._goToCard.bind(this, "cc1"));

        // sync totals between forms
        var updateOtherTotalFunc = (function(newValue) { return this.grandTotal - newValue; }).bind(this);
        this._syncData("total", "cc1", "cc2", updateOtherTotalFunc);
        this._syncData("total", "cc2", "cc1", updateOtherTotalFunc);
    },

    /**
     * 
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
     * Sync card data between two forms and avoid infinite loop
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
            if(self._hasSyncLock(data, callingForm, destForm)) return;

            var updatingValue = relationFunction(newValue, previousValue);

            if(typeof updatingValue !== "undefined")
            {
                self.forms[destForm].setCardData("total", updatingValue);
            }
        };

        this.forms[callingForm].addCardDataBind(data, passedFunction);
    },

    /**
     * Apply and remove sync lock 
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
     * Activate multi card funcionality
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

        // TO DO !!! transiction button still manual, must be 
        // something automated
        this._getGoToCard2FormButton().show();
    },

    /**
     * Deactivate multi card functionality
     */
    _disableMultiCc: function()
    {
        for(var formId in this.forms) { this.forms[formId].disable(); };
        this.forms["cc1"].openEditMode();
        this.forms["cc1"].hideTotal();
        this.forms["cc1"].setCardData("total", this.grandTotal);
        
        // TO DO !!! transiction button still manual, must be 
        // something automated
        this._getGoToCard2FormButton().hide();
    },

    /**
     * Synthesize {go to card 1 form} actions
     */
    _goToCard: function(index)
    {
        for(var formId in this.forms) { this.forms[formId].closeEditMode(); };   
        this.forms[index].openEditMode();

        // TO DO !!! transiction button still manual, must be 
        // something automated
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
     * Start system that monitors ajax requests on checkout
     */
    _startAjaxListeners: function()
    {
        var self = this;

        // override browser ajax requests object
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
                alert('a request has been initialized!');
            }, 
            onComplete: function()
            {
                alert('a request completed');
            }
        });
        */

        // register urls
        this.ajaxListeners = [];
        this._registerAjaxListener({url: "onestepcheckout/ajax/saveAddress",         callback: this._ajaxListener__tryToCaptureGrandTotal});
        this._registerAjaxListener({url: "onestepcheckout/ajax/saveFormValues",      callback: this._ajaxListener__tryToCaptureGrandTotal});
        this._registerAjaxListener({url: "onestepcheckout/ajax/saveShippingMethod",  callback: this._ajaxListener__tryToCaptureGrandTotal});
        this._registerAjaxListener({url: "onestepcheckout/ajax/applyCoupon",         callback: this._ajaxListener__tryToCaptureGrandTotal});
    },

    /**
     * Add URL to ajax listeners
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
     * Verify if the URL is monitored and run callback function
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

            for(var formId in this.forms)
            {
                this.forms[formId].updateGrandTotal(this.grandTotal);
            };
        }
    },

    /**
     * Request grand total value on server and update local forms
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

                    for(var formId in this.forms)
                    {
                        this.forms[formId].updateGrandTotal(this.grandTotal);
                    };
                }

            }).bind(this)
        });
    },

    _declareValidators: function()
    {
        var self = this;

        Validation.add('validate-rm-pagseguro-cc-number', 'Por favor, insira um número de cartão de crédito válido.', function(value, elm)
        {
            var cardIndex = $(elm).getAttribute("data-card-index");
            return self.forms["cc" + cardIndex]._validateCcNumber(value, elm);
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
        });
    }
});

RMPagSeguro_Multicc_CardForm = Class.create
({
    initialize: function(params)
    {
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
     * Add event listener and data binds to form fields
     */
    _addFieldObservers: function()
    {
        // these functions can be converted in only one observing the
        // card fields, but before we need to understand what are the 
        // default transformations and appropriate events
        this._addFieldEventListener("total",            "change", function(field){this.setCardData("total", (field.getValue() ? parseFloat(field.getValue().replace(",", ".").replace(/^\s+|\s+$/g,'')) : 0));});
        this._addFieldEventListener("number",           "change", function(field){this.setCardData("number", field.getValue().replace(/\D/g,''));});
        this._addFieldEventListener("cid",              "change", function(field){this.setCardData("cid", field.getValue().replace(/\D/g,''));});
        this._addFieldEventListener("expiration_mth",   "change", function(field){this.setCardData("expMonth", field.getValue());});
        this._addFieldEventListener("expiration_yr",    "change", function(field){this.setCardData("expYear", field.getValue());});
        this._addFieldEventListener("owner",            "change", function(field){this.setCardData("owner", field.getValue());});
        this._addFieldEventListener("owner_document",   "change", function(field){this.setCardData("owner_doc", field.getValue());});
        this._addFieldEventListener("dob_day",          "change", function(field){this.setCardData("dob_day", field.getValue());});
        this._addFieldEventListener("dob_month",        "change", function(field){this.setCardData("dob_month", field.getValue());});
        this._addFieldEventListener("dob_year",         "change", function(field){this.setCardData("dob_year", field.getValue());});

        // 'masks'
        this._addFieldEventListener("total",            "keydown", this._disallowNotNumbers);
        this._addFieldEventListener("total",            "keyup",   this._formatCurrencyInput);
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
        this.addCardDataBind("total",           this._consultInstallmentsOnPagSeguro);
        this.addCardDataBind("total",           this._updateTotalHTMLOnSetValue);
        this.addCardDataBind("number",          this._createCardTokenOnPagSeguro);
        this.addCardDataBind("number",          this._updateFormmatedNumberMetadata);
        //this.addCardDataBind("number",          this._consultInstallmentsOnPagSeguro);
        this.addCardDataBind("brand",           this._updateBrandOnHTML);
        this.addCardDataBind("brand",           this._consultInstallmentsOnPagSeguro);
        this.addCardDataBind("cid",             this._createCardTokenOnPagSeguro);
        this.addCardDataBind("expMonth",        this._createCardTokenOnPagSeguro);
        this.addCardDataBind("expYear",         this._createCardTokenOnPagSeguro);
        this.addCardDataBind("installments",    this._updateInstallmentsMetadata);
        this.addCardDataBind("token",           this._updateTokenOnHTML);

        // validate fields on blur
        this.getHTMLFormInputsAndSelects().each(function(element)
        {
            element.observe("blur", function(){ Validation.validate(element); });
        });
    },

    /**
     * Searches for elements inside a li width a declared card index
     * that has card data binds
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
        if(fieldValue.length >= 6 && !this.getCardData("brand") /* && !this.requestLocks.brand */)
        {
            // TO DO: add expiring time to this lock before using it, 
            // because of the PagSeguro lib instability
            this.requestLocks.brand = true;

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
                    }
                    else
                    {
                        this.setCardData("brand", "");
                        this.setCardMetadata("cid_size", "");
                        this.setCardMetadata("validation_algorithm", "");
                    }

                    // this validation must be here, to ensure that its going to run after 
                    // the set data (not happenned when code present on complete callback)
                    field.validate();

                }).bind(this),
                error: (function()
                {
                    this.setCardData("brand", "");
                    this.setCardMetadata("cid_size", "");
                    this.setCardMetadata("validation_algorithm", "");

                    // this validation must be here, to ensure that its going to run after 
                    // the set data (not happenned when code present on complete callback)
                    field.validate();

                }).bind(this),
                complete: (function()
                {
                    this.requestLocks.brand = false;

                }).bind(this)
            });
        }
        // clear brand if the card data became smaller than 6 digits
        else if(fieldValue.length < 6)
        {
            this.setCardData("brand", "");
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

        PagSeguroDirectPayment.getInstallments
        ({
            brand: this.getCardData("brand"),
            amount: this.getCardData("total"),
            success: this._populateInstallments.bind(this),
            error: this._populateSafeInstallments.bind(this)
        });
    },

    /**
     * Observes card number field to consult card token on PagSeguro web service
     */
    _createCardTokenOnPagSeguro: function()
    {
        var ccNumberField = this._getFieldElement("number");
        //var ccNumValidation = Validation.get("validate-cc-number");
        
        // update only if there are at least 6 digits and
        if( /*ccNumValidation.test($F(ccNumberField), ccNumberField) &&*/ 
            !this.requestLocks.token && 
            this.getCardData("brand") && this.getCardData("cid") && 
            this.getCardData("expMonth") && this.getCardData("expYear") )
        {
            // TO DO: add expiring time to this lock before using it, 
            // because of the PagSeguro lib instability
            //this.requestLocks.token = true;

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
                    }

                }).bind(this),
                error: (function()
                {
                    this.setCardData("token", "");

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
        var maxInstallments = this.config.installment_limit;
        var selectbox = this._clearInstallmentsOptions("Selecione a quantidade de parcelas");

        if(!this.config.force_installments_selection)
        {
            selectbox.remove(0);
        }

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
        
        console.error('Somente uma parcela será exibida. Erro ao obter parcelas junto ao PagSeguro:');
        console.error(response);
    },

    /**
     * Remove all options from installments select box,
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

        if(emptyOptionText)
        {
            field.options[0].text = emptyOptionText;
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

        var option = document.createElement('option');
        option.text = "1x de R$" + this.grandTotal.toFixed(2).toString().replace('.',',') + " sem juros";
        option.selected = true;
        option.value = "1|" + this.grandTotal.toFixed(2);
        field.add(option);

        return field;
    },

    /**
     * Capture digits inserted in the total field to update
     * progress bar
     * @param DomElement field Total field element
     */
    _instantReflectTotalInProgressBar: function(field)
    {
        var value = field.getValue() ? parseInt(field.getValue().replace(",", ".").replace(/^\s+|\s+$/g,'')) : 0;
        this._recalcProgressBarFulfillment(value);
    },

    /**
     * Visual adjustment of the progress bar and remaining value
     * based on total value
     * @param float value
     */
    _recalcProgressBarFulfillment: function(value)
    {
        var percent = value * 100 / this.grandTotal;
        var remainingValue = (this.grandTotal > value)
                                ? ((value > 0) ? this.grandTotal - value : this.grandTotal)
                                : 0;

        this.setCardMetadata("remaining_total", "R$" + remainingValue.toFixed(2).replace(".", ","));
        this._updateProgressBar(percent);
    },

    /**
     * Visual adjustment of the progress bar based on percentual value
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
     * Verify if the grand total changed 
     * @param float|string newValue 
     */
    updateGrandTotal: function(newValue)
    {
        if(newValue != this.grandTotal)
        {
            this.grandTotal = newValue;

            this._recalcProgressBarFulfillment(this.getCardData("total"));
            this._consultInstallmentsOnPagSeguro();
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
     * Add callback listener to setCardData 
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
     * Update the brand image flag on HTML form
     * @param string newBrand 
     */
    _updateBrandOnHTML(newBrand)
    {
        if(newBrand)
        {
            var imageUrl = "https://stc.pagseguro.uol.com.br/public/img/payment-methods-flags/42x20/" + newBrand + ".png";
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
     * Update the brand image flag on HTML form
     * @param string newValue 
     */
    _updateTotalHTMLOnSetValue(newValue)
    {
        var field = this._getFieldElement("total");
        
        if(newValue != field.getValue())
        {
            field.setValue(newValue.toFixed(2).replace(".", ","));
        }

        this._recalcProgressBarFulfillment(newValue);
    },

    /**
     * Update the token on HTML form hidden input
     * @param string newToken 
     */
    _updateTokenOnHTML(newToken)
    {
        // update HTML
        this._getFieldElement("token").setValue(newToken);
    },

    /**
     * Split installments value and update card data
     * @param DOMElement field 
     */
    _updateInstallmentsdata(field)
    {
        var splitedValue = field.getValue().split("|");
        this.setCardData("installments", splitedValue[0]);
    },

    /**
     * Generate installments metadata
     */
    _updateInstallmentsMetadata()
    {
        var field = this._getFieldElement("installments");
        this.setCardMetadata("installments_description", field.options[field.selectedIndex].text);
    },

    /**
     * Generate formmated number metadata
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
     * Add and event listener to a form field element
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
     * Show / hide card payment fields in the form
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
     * Show / hide summary
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
     * Show / hide summary
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
     * Show form, so that user can edit 
     * its fields
     */
    openEditMode: function()
    {
        this.hideSummary();
        this.showTotal();
        this.showCommonFields();
    },

    /**
     * Close form edition, showing just its summary
     */
    closeEditMode: function()
    {
        this.hideCommonFields();
        this.hideTotal();
        this.showSummary();
    },
    
    /**
     * Turn on form functionalities on interface
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
     * Turn off form functionalities on interface
     */
    disable: function()
    {
        this.hideCommonFields();
        this.hideTotal();
        this.hideSummary();

        this.state = "disabled";
    },


    /**
     * Get the summary box
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
     * Validate HTML form fields
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
     * Retrieve all the HTML form fields
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
     * Avoid not number chars
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
        var formattedValue = field.getValue().replace(/\D/g,'');
            
        while(formattedValue.length > 0 && formattedValue.substring(0, 1) == 0)
        {
            formattedValue = formattedValue.substring(1);
        }

        if(formattedValue.length == 1)
        {
            formattedValue = "0,0" + formattedValue;
        }
        else if(formattedValue.length == 2)
        {
            formattedValue = "0," + formattedValue;
        }
        else if(formattedValue.length > 2)
        {
            formattedValue = formattedValue.substring(0, formattedValue.length - 2) + 
                             "," + 
                             formattedValue.substring(formattedValue.length - 2);
        }
        
        field.setValue(formattedValue);
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
        
        formattedValue += digits.substring(0, 4) + " ";
        lastIndex = 4;

        while(digits.length > lastIndex)
        {
            formattedValue += " " + digits.substring(lastIndex, lastIndex + 4);
            lastIndex += 4;
        }
        
        field.setValue(formattedValue);
    },

    /**
     * CPF format for value inputs
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
                ? parseFloat(value.replace(",", ".").replace(/^\s+|\s+$/g,''))
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
     * Test if a number passes the Luhn algorithm test
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

    /**
     * 
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
        this._getFieldElement("owner_document").setValue(importedData.cardData.owner_doc);
        this._getFieldElement("dob_day").setValue(importedData.cardData.dob_day);
        this._getFieldElement("dob_month").setValue(importedData.cardData.dob_month);
        this._getFieldElement("dob_year").setValue(importedData.cardData.dob_year);

        // format card number
        this._formatCardNumber(this._getFieldElement("number"));
        this._formatCurrencyInput(this._getFieldElement("total"));
        
        // copy data and metadata
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
     * Prepare object to be deleted
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