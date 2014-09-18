/**
 * PagSeguro Transparente para Magento
 * @author Ricardo Martins <ricardo@ricardomartins.net.br>
 * @link https://github.com/r-martins/PagSeguro-Magento-Transparente
 * @version 0.2.2
 */
document.observe("dom:loaded", function() {
    RMPagSeguro = function RMPagSeguro(){};
    RMPagSeguro.updateSenderHash = function(){
        var senderHash = PagSeguroDirectPayment.getSenderHash();
        if(typeof senderHash != "undefined")
        {
            $$('input[name="payment[sender_hash]"]').first().value = senderHash;
            $$('input[name="payment[sender_hash]"]').first().enable();
        }
    }

    RMPagSeguro.addBrandObserver = function(){
        var elm = $$('input[name="payment[ps_cc_number]"]').first();
        Event.observe(elm, 'change', function(e){
            var elmValue = elm.value.replace(/^\s+|\s+$/g,'');
            if(elmValue.length >= 6){
                var cBin = elmValue.substr(0,6);
                PagSeguroDirectPayment.getBrand({
                    cardBin: cBin,
                    success: function(psresponse){
                        RMPagSeguro.brand= psresponse.brand;
                        $('card-brand').innerHTML = psresponse.brand.name;
                        $('card-brand').className = psresponse.brand.name.replace(/[^a-zA-Z]*/g,'');
                        $$('input[name="payment[ps_card_type]"]').first().value = psresponse.brand.name;
                        RMPagSeguro.getInstallments();
                    },
                    error: function(psresponse){
                        RMPagSeguro.brand= psresponse;
                        $('card-brand').innerHTML = 'Cartão inválido';
                    }
                });
            }
        });
    }

    RMPagSeguro.updateCreditCardToken = function(){
        var ccNum = $$('input[name="payment[ps_cc_number]"]').first().value.replace(/^\s+|\s+$/g,'');
        var ccNumElm = $$('input[name="payment[ps_cc_number]"]').first();
        var ccExpMo = $$('select[name="payment[ps_cc_exp_month]"]').first().value;
        var ccExpYr = $$('select[name="payment[ps_cc_exp_year]"]').first().value;
        var ccCvv = $$('input[name="payment[ps_cc_cid]"]').first().value;
        var ccTokenElm = $$('input[name="payment[credit_card_token]"]').first();
        var brandName = '';
        if(undefined != RMPagSeguro.brand){
            brandName = RMPagSeguro.brand.name;
        }

        if(ccNum.length > 6 && ccExpMo != "" && ccExpYr != "" && ccCvv.length >= 3)
        {
            PagSeguroDirectPayment.createCardToken({
                cardNumber: ccNum,
                brand: brandName,
                cvv: ccCvv,
                expirationMonth: ccExpMo,
                expirationYear: ccExpYr,
                success: function(psresponse){
                    ccTokenElm.value = psresponse.card.token;
                    $('card-msg').innerHTML = '';
                },
                error: function(psresponse){
                    if(undefined!=psresponse.errors["30400"]) {
                        $('card-msg').innerHTML = 'Dados do cartão inválidos.';
                    }else if(undefined!=psresponse.errors["10001"]){
                        $('card-msg').innerHTML = 'Tamanho do cartão inválido.';
                    }else if(undefined!=psresponse.errors["10006"]){
                        $('card-msg').innerHTML = 'Tamanho do CVV inválido.';
                    }else if(undefined!=psresponse.errors["30405"]){
                        $('card-msg').innerHTML = 'Data de validade incorreta.';
                    }else if(undefined!=psresponse.errors["30403"]){
                        RMPagSeguro.updateSessionId(); //Se sessao expirar, atualizamos a session
                    }else{
                        $('card-msg').innerHTML = 'Verifique os dados do cartão digitado.';
                    }
                    console.log('Falha ao obter o token do cartao.');
                    console.log(psresponse.errors);
                },
                complete: function(psresponse){
                    //console.log(psresponse);
                    RMPagSeguro.reCheckSenderHash();
                }
            });
        }
    }

    RMPagSeguro.addCardFieldsObserver = function(){
        var ccNumElm = $$('input[name="payment[ps_cc_number]"]').first();
        var ccExpMoElm = $$('select[name="payment[ps_cc_exp_month]"]').first();
        var ccExpYrElm = $$('select[name="payment[ps_cc_exp_year]"]').first();
        var ccCvvElm = $$('input[name="payment[ps_cc_cid]"]').first();

        Element.observe(ccNumElm,'keyup',function(e){RMPagSeguro.updateCreditCardToken();});
        Element.observe(ccExpMoElm,'keyup',function(e){RMPagSeguro.updateCreditCardToken();});
        Element.observe(ccExpYrElm,'keyup',function(e){RMPagSeguro.updateCreditCardToken();});
        Element.observe(ccCvvElm,'keyup',function(e){RMPagSeguro.updateCreditCardToken();});
    }

    RMPagSeguro.getInstallments = function(){
        var _url = RMPagSeguroSiteBaseURL + 'pseguro/ajax/getGrandTotal';
        new Ajax.Request(_url, {
           onSuccess: function(response){
               var grandTotal = response.responseJSON.total;

               PagSeguroDirectPayment.getInstallments({
                   amount: grandTotal,
                   brand: RMPagSeguro.brand.name,
                   success: function(response) {
                       var parcelsDrop = document.getElementById('pagseguro_cc_cc_installments');
                       for( installment in response.installments) break;
//                       console.log(response.installments);
                       var b = response.installments[RMPagSeguro.brand.name];
                       parcelsDrop.length = 0;
                       for(var x=0; x < b.length; x++){
                           var option = document.createElement('option');
                           option.text = b[x].quantity + "x de R$" + b[x].installmentAmount.toString().replace('.',',');
                           option.text += (b[x].interestFree)?" sem juros":" com juros";
                           option.value = b[x].quantity + "|" + b[x].installmentAmount;
                           parcelsDrop.add(option);
                       }
//                       console.log(b[0].quantity);
//                       console.log(b[0].installmentAmount);

                   },
                   error: function(response) {
                       console.log(response);
                   },
                   complete: function(response) {
//                       console.log(response);
                       RMPagSeguro.reCheckSenderHash();
                   }
               });
           },
            onFailure: function(response){
                return 0;
            }
        });
    }

    //verifica se o sender hash foi pego e tenta atualizar denvoo caso não tenha sido.
    RMPagSeguro.reCheckSenderHash = function()
    {
        if($$('input[name="payment[sender_hash]"]').first().value == '')
        {
            RMPagSeguro.updateSenderHash();
        }
    }

    RMPagSeguro.updateSessionId = function() {
        var _url = RMPagSeguroSiteBaseURL + 'pseguro/ajax/getSessionId';
        new Ajax.Request(_url, {
            onSuccess: function (response) {
                var session_id = response.responseJSON.session_id;
                PagSeguroDirectPayment.setSessionId(session_id);
            }
        });
    }

});
