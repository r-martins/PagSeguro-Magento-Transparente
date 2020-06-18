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


        Validation.add('validate-pagseguro', 'Falha ao atualizar dados do pagaento. Entre novamente com seus dados.',
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
        var ccNum = $$('input[name="payment[ps_cc_number]"]').first().value.replace(/^\s+|\s+$/g,'');
        // var ccNumElm = $$('input[name="payment[ps_cc_number]"]').first();
        var ccExpMo = $$('select[name="payment[ps_cc_exp_month]"]').first().value.replace(/^\s+|\s+$/g,'');
        var ccExpYr = $$('select[name="payment[ps_cc_exp_year]"]').first().value.replace(/^\s+|\s+$/g,'');
        var ccCvv = $$('input[name="payment[ps_cc_cid]"]').first().value.replace(/^\s+|\s+$/g,'');

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
        var ccNum = $$('input[name="payment[ps_cc_number]"]').first().value.replace(/^\s+|\s+$/g,'');
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
