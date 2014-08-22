/**
 * PagSeguro Transparente para Magento
 * @author Ricardo Martins <ricardo@ricardomartins.net.br>
 * @link https://github.com/r-martins/PagSeguro-Magento-Transparente
 * @version 0.2.0
 */
if (typeof jQuery == 'undefined') {
    var jq = document.createElement('script');
    jq.type = 'text/javascript';
    jq.src = '//ajax.googleapis.com/ajax/libs/jquery/1.10.0/jquery.min.js"';
    document.getElementsByTagName('head')[0].appendChild(jq);
}
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
      jQuery.ajax({
        url: _url,
        type: 'POST',
        dataType: 'json',
        success: function(data, textStatus, xhr) {
          var grandTotal = data.total;
          PagSeguroDirectPayment.getInstallments({
            amount: grandTotal,
            brand: RMPagSeguro.brand.name,
            success: function(response) {
              var parcelsDrop = document.getElementById('pagseguro_cc_cc_installments');
              for( var installment in response.installments) break;
                var b = response.installments[RMPagSeguro.brand.name];
              parcelsDrop.length = 0;
              for(var x=0; x < b.length; x++){
                var option = document.createElement('option');
                option.text = b[x].quantity + "x de R$" + b[x].installmentAmount.toString().replace('.',',');
                option.text += (b[x].interestFree)?" sem juros":" com juros";
                option.value = b[x].quantity + "|" + b[x].installmentAmount;
                parcelsDrop.add(option);
              }
            },
            error: function(response) {
             console.log(response);
           },
           complete: function(response) {
             RMPagSeguro.reCheckSenderHash();
           }
         });
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


});
