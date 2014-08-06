---
layout: default
title: PagSeguro Transparente para Magento - Perguntas Frequentes

---

# Perguntas Frequentes
***

## Geral

* O que significa *transparente*? <br/>
Basicamente significa que o usuário não precisa sair da sua loja para realizar o pagamento, o que ocorre usando alguns meios de pagamento.

* O módulo é mesmo gratuito?<br/>
Sim. É gratuito porém ao usar, você aceita que deverá contribuir divulgando suas melhorias e alterações, preferencialmente fazendo um pull request no projeto.<br/>
A versão gratuita oferece suporte à cartão de crédito.

* Qual a diferença da versão gratuita e da versão PRÓ (paga)? <br/>
A versão Pró possuí suporte à TEF (Debito online) e Boleto bancário. Veja mais detalhes [aqui]({{ site.baseurl }}/pro)

* É seguro? <br/>
Sempre há risco. No entanto, nenhum dado de cartão de crédito do seu cliente é armazenado na loja. No momento que o cliente termina de preencher os dados do cartão, estes são enviados via HTTPS (seguro) via JavaScript para o PagSeguro, que devolve uma chave única de segurança para aquela transação. <br/>
Em versões futuras os dados do cartão sequer serão enviados para seu site.

* O código é aberto?<br/>
Sim, tanto no módulo PRÓ quanto no módulo free, não há criptografia nenhuma no código nem obfuscação de código fonte.

***

## Instalação

* Qual a diferença entre baixar o módulo no Github e fazer a instalação no Magento Connect? <br/>
Fazendo o download no Github (links do topo) você terá a versão mais recente. Isso porque é mais fácil publicar a nova versão aqui do que no Magento Connect.

* Como sei que versão estou usando? <br/>
Abrindo o arquivo app/code/community/RicardoMartins/PagSeguro/etc/config.xml do seu módulo e vendo o trecho *<version>XXX</version>*.

* Existe suporte para instalação? <br/>
Você pode contratar o serviço de instalação profissional a partir de R$349,00 se possuir acesso ssh ao servidor da loja, ou R$399,00 caso possua apenas FTP.<br/>
A instalação é feita em até 15 dias após a constatação do pagamento e da liberação da conta para uso do checkout transparente pelo PagSeguro. Entre em contato para mais detalhes.

* Não gostei, não funcionou, deu conflito, não quero mais brincar. Como desinstalo? <br/>
Basta remover os arquivos do módulo que adicionou. O módulo não altera sua base, nem cria atributos, ou algo assim. Talvez encontre problemas apenas para visualizar pedidos antigos realizados com o módulo, assim como ocorreria com outros métodos de pagamento.

***

## Problemas comuns

### A maioria dos problemas podem ser diagnosticados lendo o log do módulo, localizado na pasta var/log/pagseguro.log.

* As parcelas/bandeira do cartão não carregam. <br/>
Geralmente ocorre porque sua loja/conta não está autorizada pelo PagSeguro a utilizar o checkout transparente. Uma mensagem constatando esse erro será exibida no arquivo de log.

* Falha ao obter o token do cartão ou sender_hash.<br/>
Veja se não excluiu os dados de campos hidden no template de cartão, localizado em app/design/frontend/base/default/template/ricardomartins_pagseguro/form/cc.phtml. <br/>

* Meu cartão não está sendo aceito no modo de testes (Sandbox). <br/>
No modo sandbox utilize cartão visa 4111111111111111 com qualquer cvv de 3 digitos e qualquer data de validade futura. <br/>

* Gostaria de exibir a bandeira dos cartões como imagens. Como faço? <br/>
No momento o modulo exibe apenas a legenda, você precisa customizar do seu jeito via css por exemplo.<br/>
Pra facilitar, cada bandeira ganha a própria classe css no objeto que guarda o nome do cartão. Ex: &lt;span id="card-brand" class="visa"... <br/>
Basta configurar seu css para exibir do jeito que desejar para cada uma das bandeiras.<br/>

* Todos os pagamentos que faço mesmo com meu cartão são negados. Porque?<br/>
O PagSeguro nega qualquer pedido feito pelo próprio dono da loja. Se o cartão for do mesmo titular do dono, ou se o acesso partir da mesma máquina que um dia logou na administração do pagseguro, eles tendem a recusar e negar a transação. <br/>

* Uso uma tela de sucesso personalizada, e no módulo PRO o botão de impressão de boleto ou TEF não aparece. <br/>
Se você usa o módulo pró, e tem uma tela de sucesso customizada, basta adicionar o trecho de código abaixo no phtml de sucesso de seu tema:
<pre>
&lt;?php echo $this->getLayout()->createBlock('ricardomartins_pagseguropro/payment_info', 'paymentInfo')->setTemplate('ricardomartins_pagseguropro/payment/info.phtml')->toHtml();?>
</pre>

* Após algum dos reloads do meu modulo de OneStepCheckout parece que o modulo pára de funcionar. <br/>
Isso ocorre por uma falha na renderização dos javascripts que ocorre em alguns módulos desse tipo. Basta adicionar a chamada JS na area de 'success' do ajax que atualiza as formas de pagamento.
<pre>
RMPagSeguro.addBrandObserver();
</pre>

* Como sei se minha conta tem suporte ou está habilitada pra usar o checkout transparente? <br/>
Utilize a ferramenta abaixo se desejar.

	<input type="email" placeholder="email da conta" id="email"/>
	<input type="token" placeholder="token" id="token"/>
	<input type="button" onclick="testToken()" value="Testar"/>
	<br/>
	<span id="resultado"></span>
	
	<script type="text/javascript">
	var testToken = function(){
		$.ajax({
			url: 'http://ws.ricardomartins.net.br/pspro/v1/token/test/' + $('#email').val() + '/'+ $('#token').val(),
			dataType: 'json',
			crossDomain: true,
			}).done(function(data){
				if(data.valid){
					$('#resultado').html('Habilitado. Você já pode usar o checkout transparente.');
				}else{
					$('#resultado').html('Checkout transparente <strong>não habilitado</strong>. Faça sua requisição ou tente novamente amanhã.');
				}
		})
	}
	</script>
