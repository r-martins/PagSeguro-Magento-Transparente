---
layout: default
title: PagSeguro Transparente para Magento PRÓ - com TEF e Boleto - by Ricardo Martins
---

# PagSeguro Transparente PRÓ APP
***

* Sem necessidade de cartão de crédito
* Mesmos recursos da Versão PRO (suporte boleto e TEF/Débito)

## Modelo de aplicação PagSeguro

### Definição
Com essa modalidade você não precisa pedir liberação de uso da API transparente para usar o módulo para Magento PRÓ.
Também é indicado para lojistas que não possuem cartão de crédito para realizar a assinatura da versão PRÓ normal.

Na modalidade APP, o Módulo PRÓ para Magento (que suporta Boleto e TEF) é integrado à conta da sua loja utilizando o
modelo de aplicações do PagSeguro UOL. Diferente do modelo de assinatura, você terá que autorizar a aplicação a
acessar informações de sua conta no PagSeguro.

A forma de cobrança também muda, passando a ser cobrado uma pequena taxa de intemediação junto com a taxa do PagSeguro.

![Licença Aplicação]({{ site.baseurl }}/images/sshot-app.png)

## Valor
* 0,5% de taxa de intermediação sendo repassado pelo PagSeguro automaticamente para vendas aprovadas.
* Veja detalhes das condições na tela de autorização (após o preenchimento do formulário de contratação)
* [Vamos lá!](#contratar)

## Garantia
As mesmas da versão PRÓ.

### Política de Devolução
Não há como realizar estornos das transações e taxas de intermediação. Mas você pode desinstalar ou remover o acesso
da aplicação à sua loja a qualquer momento no painel do PagSeguro.

## Cancelamento
Pode ser cancelado a qualquer momento diretamente no seu painel do PagSeguro (ou <a href="https://pagseguro.uol.com.br/aplicacao/listarAutorizacoes.jhtml" target="_blank">neste link</a>), removendo o acesso à aplicação. Você
deve remover o módulo da sua loja após tal ação, pois o mesmo não funcionará se não possuir as permissões adequadas.

## Suporte e customizações
O suporte para customização e resolução de conflitos podem ser solicitado junto às comunidades e foruns magento.
A instalação profissional também pode ser contratada, com valor a combinar, visando resolver conflitos e afins.

## Loja Demo
Confira a <a href="http://pagseguro-exemplo.ricardomartins.net.br/" target="_blank">loja de demonstração</a> com Magento 1.9.0.1 e IWD Checkout.

## Contratar

* Preencha o formulário abaixo para ser redirecionado ao PagSeguro
* Revise as condições de taxas aplicadas à sua conta e autorize a aplicação
* Você receberá o módulo do PagSeguro PRO por e-mail neste momento.
* Instale, e configure o módulo com a chave fornecida, no modelo de aplicação como mostrado na primeira imagem desta página.

<form action="http://ws.ricardomartins.net.br/pspro/v6/app/new" method="POST" target="_blank" id="formAppNew">
<table>
<tr>
<td>
E-mail da conta Pagseguro:
</td>
<td>
<input type="email" name="email" id="email"/> *
</tr>

<tr>
<td>
URL da Loja:
</td>
<td>
<input type="url" name="url" id="url"/> *
<br/>
</td>
</tr>

<tr>
<td colspan="2">
<input type="button" value="Avançar" onclick="validateAndSubmit();"/>
</td>
</tr>

<tr>
 <td colspan="2">
 Para revogar um acesso existente acesse <a href="https://pagseguro.uol.com.br/aplicacao/listarAutorizacoes.jhtml" target="_blank">este link</a>.
 </td>
</tr>

</table>
</form>
<script type="text/javascript">
if(document.URL.search('0.0.0.0') > 0){
 document.getElementById("formAppNew").action = "http://ws.local.com.br/pspro/v6/app/new";
}
var validateAndSubmit = function(){
 var email = document.getElementById("email").value;
 var url = document.getElementById("url").value;
 if(!validateEmail(email)){alert('Email inválido.'); return false;}
 if(!validateUrl(url)){alert('URL inválido.'); return false;}
 document.getElementById("formAppNew").submit();
}
var validateEmail = function(email) { 
    var re = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(email);
} 

var validateUrl = function(url) {
 var re = /^(?:(?:https?|ftp):\/\/)(?:\S+(?::\S*)?@)?(?:(?!10(?:\.\d{1,3}){3})(?!127(?:\.\d{1,3}){3})(?!169\.254(?:\.\d{1,3}){2})(?!192\.168(?:\.\d{1,3}){2})(?!172\.(?:1[6-9]|2\d|3[0-1])(?:\.\d{1,3}){2})(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))|(?:(?:[a-z\u00a1-\uffff0-9]+-?)*[a-z\u00a1-\uffff0-9]+)(?:\.(?:[a-z\u00a1-\uffff0-9]+-?)*[a-z\u00a1-\uffff0-9]+)*(?:\.(?:[a-z\u00a1-\uffff]{2,})))(?::\d{2,5})?(?:\/[^\s]*)?$/i;
 return re.test(url);
}
</script>