=== GVN Checkout for WooCommerce ===
Contributors: gvntrack
Tags: woocommerce, checkout, custom checkout, order bump, pix
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
WC requires at least: 7.0
WC tested up to: 9.0
Stable tag: 1.13.4
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Checkout personalizado e otimizado para WooCommerce com layout moderno, order bump e configurações avançadas.

== Description ==

O **GVN Checkout** substitui o checkout padrão do WooCommerce por um layout moderno, limpo e otimizado para conversão.

**Recursos:**

* Layout responsivo e moderno com design premium
* Header configurável (textos e cores)
* Formulário simplificado (Nome, Sobrenome, CPF, Celular, E-mail)
* Resumo do pedido dinâmico
* Cupom de desconto com toggle
* Gateways de pagamento nativos do WooCommerce com visual customizado
* Order Bump configurável pelo admin
* Máscaras automáticas para CPF e Celular
* Cores personalizáveis (primária, botão, header, badge)
* Totalmente integrado com o fluxo de checkout do WooCommerce

**Como usar:**

1. Instale e ative o plugin
2. Crie uma página e adicione o shortcode `[gvn-checkout]`
3. Configure as opções em **WooCommerce > Configurações > GVN Checkout**
4. Opcional: configure o Order Bump com um produto

== Installation ==

1. Faça upload da pasta `checkout` para o diretório `/wp-content/plugins/`
2. Ative o plugin no menu "Plugins" do WordPress
3. Vá em **WooCommerce > Configurações > GVN Checkout** para personalizar

== Changelog ==

= 1.13.4 =
* Documenta `checkout-woo` como a base canônica do projeto.

= 1.13.0 =
* Novo: Proxy server-side para consulta de CEP (ViaCEP) com cache via transients (7 dias)
* Novo: Validação server-side de formato de CEP, UF válida e consistência CEP↔UF no checkout
* Novo: Normalização automática de UF, cidade e CEP ao salvar pedidos
* Novo: Campos de endereço (UF, cidade, bairro) são travados após auto-fill via CEP, com botão para edição manual
* Novo: Foco automático no campo "número" após preenchimento de endereço via CEP
* Novo: Alerta visual de inconsistência CEP↔UF no frontend
* Novo: Retry automático em caso de falha de rede na consulta de CEP
* Novo: Classe GVN_Address_Validation com lista completa de UFs brasileiras e mapeamento CEP→UF
* Melhoria: Consulta de CEP agora passa pelo servidor (segurança e cache)

= 1.0.0 =
* Lançamento inicial
* Checkout personalizado com shortcode [gvn-checkout]
* Header configurável
* Order Bump com produto configurável
* Integração com gateways nativos do WooCommerce
* Máscaras para CPF e Celular
* Cupom de desconto funcional
