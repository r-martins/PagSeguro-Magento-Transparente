---
layout: default
title: PagSeguro Transparente para Magento PRÓ - com TEF e Boleto - by Ricardo Martins
---

# PagSeguro Transparente PRÓ *APP*
***

## Aplicação autorizada!

Sua aplicação foi autorizada com sucesso.

Sua chave de acesso, o módulo PagSeguro PRO e instruções foram enviadas pra você para o e-mail informado no primeiro passo.

<p id="chave">
</p>
<input type="button" onclick="location.href='../../'" value="Voltar"/>

<script type="text/javascript">
function getParameterByName(name) {
    name = name.replace(/[\[]/, "\\[").replace(/[\]]/, "\\]");
    var regex = new RegExp("[\\?&]" + name + "=([^&#]*)"),
        results = regex.exec(location.search);
    return results === null ? "" : decodeURIComponent(results[1].replace(/\+/g, " "));
}

var chave = getParameterByName('publicKey');

if(chave !== null)
{
	document.getElementById('chave').innerHTML = 'Se você já tem o módulo PRO instalado, configure a chave a seguir em Formas de Pagamento -> PagSeguro PRO - Licença. <br/><strong>Sua chave: '+ chave + '</strong>';
}

</script>