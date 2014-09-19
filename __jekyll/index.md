---
layout: default
title: PagSeguro Transparente para Magento - by Ricardo Martins
---

# PagSeguro Transparente
***

### Módulo de Checkout integrado com PagSeguro Transparente para Magento

## Recursos
* Adiciona o PagSeguro com suporte a cartão de crédito permitindo digitar os dados sem a necessidade de ser redirecionado para o site do PagSeguro
* Atributos de CPF, Data de Nascimento, rua, bairro, e outros configuráveis.
* Utiliza API v2
* Trata retorno automático com atualizações do pedido
* Modo de debug com logs detalhados
* Modo de testes usando Sandbox opcional
* Token criptografado para maior segurança
* Não salva dados do cartão, apenas os 4 últimos digitos
* Calcula parcelas de acordo com o cartão selecionado e regras configuradas no seu painel

## Loja Demo
Confira a <a href="http://pagseguro-exemplo.ricardomartins.net.br/" target="_blank">loja de demonstração</a> com Magento 1.9.0.1 e IWD Checkout.

## Doadores e Empresas Apoiadoras
* Renato Aleksander

[Contribua](https://pagseguro.uol.com.br/checkout/v2/donation.html?currency=BRL&receiverEmail=ricardo@ricardomartins.info) você também e tenha seu nome ou marca exibidos aqui.

##Telas
![Frontend](http://r-martins.github.io/PagSeguro-Magento-Transparente/images/sshot-frontend.png)
![Backend](http://r-martins.github.io/PagSeguro-Magento-Transparente/images/sshot-backend.png)

## Instalação

### Instalar com [modgit](https://github.com/jreinke/modgit)
    $ cd path/to/magento
    $ modgit init
    $ modgit clone pagseguro git@github.com:r-martins/PagSeguro-Magento-Transparente.git

### Ou faça manualmente:

* Faça download da última versão [aqui](https://github.com/r-martins/PagSeguro-Magento-Transparente/downloads)
* Descompacte na raíz da instalação da loja *mesclando com as pastas existentes*
* Limpe o cache
* Solicite autorização para uso da API transparente [aqui](https://pagseguro.uol.com.br/receba-pagamentos.jhtml#checkout-transparent)

Feito isso, configure seus dados no painel do magento em Sistema->Configuracao->Formas de Pagamento. Lembre-se de mapear os atributos de acordo com o que você utiliza na sua loja.

### Configurações no Painel do PagSeguro
* Integrações->Pagamentos pela API: desativado
* Integrações->Notificação de Transações: Ativado. Coloque o url http://www.SUALOJA.com.br/pseguro/notification
* Lembre-se de [autorizar sua loja](https://pagseguro.uol.com.br/receba-pagamentos.jhtml#checkout-transparent) a usar a api transparente. Em caso de demora ou dúvidas sobre a liberação ligue na Central de Atendimento do PagSeguro: (11)5627-3440 (Segunda a sábado das 8h às 20h30, exceto feriados). 
* Nota: muitas pessoas tem reclamado da demora na aprovação da conta para uso do checkout transparente, e do desconhecimento de membros da equipe do Pagseguro sobre tal API. Sugiro nesses casos, que reforcem seus pedidos através do [Facebook do PagSeguro](https://www.facebook.com/pagseguro) via msg privada.

### Compatibilidade com módulo do Bruno Assarisse
Sim, é possível usar o módulo em conjunto com o do Bruno Assarisse, que redireciona o usuário pro site do pagseguro e suporta os demais metodos de pagamento. Para isso, realize a configuração do módulo do Bruno normalmente, mas no pagseguro utilize a url de retorno do módulo transparente.
No seu painel do PagSeguro, configure Integrações->Retorno Automatico de Dados, a url do Bruno: http://www.SUALOJA.com.br/pagseguro/pay/return.
Repare que o módulo do Bruno é /pagseguro/ enquanto este é /pseguro/.
Compatibilidade com outros módulos do Pagseguro ainda estão em testes. Considere reportar suas experiências.

### Detecção de problemas
A maior parte dos problemas pode ser identificada ativando o modo debug no painel e vendo o arquivo de log gerado na sua pasta/do/magento/var/log/pagseguro.log. 
Nota: O modo debug não muda o funcionamento do módulo.

## Bugs?
* Relate problemas na parte de [Issues](https://github.com/r-martins/PagSeguro-Magento-Transparente/issues) aqui no github.
* A maior parte dos problemas pode ser resolvido lendo o log das transações com o modo de debug ativo.

## Suporte e customizações
O suporte pode ser obtido nas comunidades magento, ou você pode orçar uma cotação comigo pelo e-mail abaixo.
Ou consulte as [*PERGUNTAS FREQUENTES*]({{ site.baseurl }}/faq).

## Contribua
Apesar de ser open source e gratuito, o módulo não nasceu sozinho ou com suporte de nenhuma empresa e não tem vínculo com o PagSeguro (maior beneficiado). Considere [**fazer uma doação**](https://pagseguro.uol.com.br/checkout/v2/donation.html?currency=BRL&receiverEmail=ricardo@ricardomartins.info) ou contribuir com melhorias realizando um pull request.

### Termos de uso
O módulo pode ser usado com fins comerciais, desde que qualquer melhoria realizada seja publicadas com forks e pull requests. (MIT)

** Não possuí vínculo com PagSeguro UOL. **

## PagSeguro PRO (_Novidade_)
* Suporte a Boleto e TEF transparentes
* Preço promocional de lançamento R$9,90/mês (por tempo limitado)
* Instalação não incluído.
* Atualizações de correções enviadas por e-mail.

### [Saiba mais &gt;]({{ site.baseurl }}/pro)


## Instalação Profissional
* Instalação em lojas com acesso apenas FTP: R$399,00
* Instalação em lojas com acesso SSH e FTP: R$349,00
* Não contempla atualização para novas versões nem garantia de ausência de bugs futuros. A última versão do módulo será instalada, e configurada.
* Entre em contato para maiores detalhes.


## Updates
{% include newsletter.html %}

## Autor / Suporte
Antes de enviar um email, veja se sua dúvida já não foi respondida [aqui](faq).<br/>
[Ricardo Martins](http://ricardomartins.net.br/)  (<suporte@pagsegurotransparente.zendesk.com>)



<script type="text/javascript">
$(document).ready(function(){
$("nav ul").append("<li class='tag-faq'><a href='faq'>Perguntas Frequentes</a></li>");
});
</script>

