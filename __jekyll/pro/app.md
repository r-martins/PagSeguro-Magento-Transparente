---
layout: default
title: PagSeguro Transparente para Magento PRÓ - com TEF e Boleto - by Ricardo Martins
---

# PagSeguro Transparente PRÓ *APP*
***

## Modelo de aplicação PagSeguro

### Definição
Com essa modalidade você não precisa pedir liberação de uso da API transparente para usar o módulo para Magento PRÓ.
Também é indicado para logistas que não possuem cartão de crédito para realizar a assinatura da versão PRÓ normal.

Na modalidade APP, o Módulo PRÓ para Magento (que suporta Boleto e TEF) é integrado à conta da sua loja utilizando o
modelo de aplicações do PagSeguro UOL. Diferente do modelo de assinatura, você terá que autorizar a aplicação a
acessar informações de sua conta no PagSeguro.

A forma de cobrança também muda, passando a ser cobrado uma pequena taxa de intemediação (ver abaixo).


## Valor
* 0,5% de taxa de intermediação sendo repassado pelo PagSeguro automaticamente.
* Veja detalhes das condições na tela de autorização (após o preenchimento do formulário)
* [Contrate já](#contratar)

## Garantia
As mesmas da versão PRÓ.

### Política de Devolução
Não há como realizar estornos das transações e taxas de intermediação. Mas você pode desinstalar ou remover o acesso
da aplicação à sua loja a qualquer momento no painel do PagSeguro.

## Cancelamento
Pode ser cancelado a qualquer momento diretamente no seu painel do PagSeguro, removendo o acesso à aplicação. Você
deve remover o módulo da sua loja após tal ação, pois o mesmo não funcionará se não possuir as permissões adequadas.

## Suporte e customizações
O suporte para customização e resolução de conflitos podem ser solicitado junto às comunidades e foruns magento.
A instalação profissional também pode ser contratada, com valor a combinar, visando resolver conflitos e afins.

## Loja Demo
Confira a <a href="http://pagseguro-exemplo.ricardomartins.net.br/" target="_blank">loja de demonstração</a> com Magento 1.9.0.1 e IWD Checkout.

## Contratar
<form action="http://ws.ricardomartine.net.br/pspro/v6/app/new" method="POST" target="_blank" id="formAppNew">
<table>
<tr>
<td>
E-mail do Pagseguro:
</td>
<td>
<input type="email" name="email" id="email"/>
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
<input type="submit" value="Prosseguir"/>
</td>
</tr>

</table>
*Informar o URL da loja que conterá o Módulo PagSeguro PRÓ com / no final. As notificações de pagamento de
 boletos e TEF serão enviadas para este URL.
</form>
<script type="text/javascript">
if(document.URL.search('0.0.0.0') > 0){
 document.getElementById("formAppNew").action = "http://ws.local.com.br/pspro/v6/app/new";
}
</script>