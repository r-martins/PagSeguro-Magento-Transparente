/**
 * PagSeguro Transparente para Magento
 * @author Ricardo Martins <ricardo@ricardomartins.net.br>
 * @link https://github.com/r-martins/PagSeguro-Magento-Transparente
 */
document.observe("dom:loaded", function() {
    RMPagSeguro = function RMPagSeguro(){};
    RMPagSeguro.updateSenderHash = function(){
        var senderHash = PagSeguroDirectPayment.getSenderHash();
        if(typeof senderHash != "undefined")
        {
            $$('input[name="payment[sender_hash]"]').first().value = senderHash;
        }
    }

    RMPagSeguro.addBrandObserver = function(){
        var elm = $$('input[name="payment[ps_cc_number]"]').first();
        Event.observe(elm, 'keyup', function(e){
            var elmValue = elm.value.replace(/^\s+|\s+$/g,'');
            if(elmValue.length >= 6){
                var cBin = elmValue.substr(0,6);
                PagSeguroDirectPayment.getBrand({
                    cardBin: cBin,
                    success: function(psresponse){
                        RMPagSeguro.brand= psresponse.brand;
                        $('card-brand').innerHTML = psresponse.brand.name;
                        $$('input[name="payment[ps_card_type]"]').first().value = psresponse.brand.name;
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
        var ccExpMo = $$('select[name="payment[ps_cc_exp_month]"]').first().value;
        var ccExpYr = $$('select[name="payment[ps_cc_exp_year]"]').first().value;
        var ccCvv = $$('input[name="payment[ps_cc_cid]"]').first().value;
        var ccTokenElm = $$('input[name="payment[credit_card_token]"]').first();

        if(ccNum.length > 6 && ccExpMo != "" && ccExpYr != "" && ccCvv.length >= 3)
        {
            PagSeguroDirectPayment.createCardToken({
                cardNumber: ccNum,
                brand: RMPagSeguro.brand.name,
                cvv: ccCvv,
                expirationMonth: ccExpMo,
                expirationYear: ccExpYr,
                success: function(psresponse){
                    ccTokenElm.value = psresponse.card.token;
                },
                error: function(psresponse){
                    console.log('Falha ao obter o token do cartao.');
                },
                complete: function(psresponse){
//                    console.log(psresponse);
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

//    RMPagSeguro.whenReady = function (){
//        var iid = setInterval(function()
//        {
//            if(typeof PagSeguroDirectPayment != "undefined" && PagSeguroDirectPayment.ready){
//                console.log('PagSeguro ready');
//
//                clearInterval(iid);
//                RMPagSeguro.updateSenderHash();
//                RMPagSeguro.addBrandObserver();
//                RMPagSeguro.addCardFieldsObserver();
//            }
//        }, 4000);
//    }

//    RMPagSeguro.whenReady();

});