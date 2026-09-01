# ADR 0001: Adoção do Checkout Clássico Nativo como Base Canônica

- **Status:** Aprovado
- **Data:** 2026-09-01
- **Contexto:** Evolução arquitetural do GVN Checkout for WooCommerce

---

## Contexto e Problema

O WooCommerce oferece atualmente dois modelos de checkout: o Checkout Clássico (baseado em shortcode, templates PHP e hooks nativos) e o Cart/Checkout Blocks (Gutenberg Blocks / Store API). O plugin necessita de um layout visual altamente customizado, compatível com o ecossistema brasileiro de gateways de pagamento (Pix, boleto bancário, cartão com tokenização e parcelamento).

Muitos gateways nacionais dependem exclusivamente dos eventos, estruturas DOM e hooks do checkout clássico (`init_checkout`, `update_checkout`, `updated_checkout`, `woocommerce_checkout_order_review`, `woocommerce_checkout_billing`).

## Decisão

Adotamos oficialmente o **Checkout Clássico do WooCommerce** como a base de operação canônica do plugin para a versão 2.0.

1. **Contexto Suportado:** O shortcode `[gvn-checkout]` é renderizado na página oficial configurada em `woocommerce_checkout_page_id`.
2. **Preservação do Fluxo Nativo:** O plugin preserva a action do formulário, os nonces nativos, os endpoints WC AJAX e a autoridade do `WC_Checkout::create_order()`.
3. **Incompatibilidade Declarada com Blocks:** O plugin declara formalmente incompatibilidade com Cart/Checkout Blocks na primeira release via `FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, false)` e exibe aviso administrativo caso blocos sejam detectados.

## Consequências

- **Positivas:** Máxima compatibilidade com gateways legados e modernos do ecossistema WooCommerce; suporte a todos os hooks nativos; integridade na criação de pedidos e cálculo de totais.
- **Negativas:** Não oferece suporte nativo ao editor de blocos de checkout nesta versão; o suporte a Blocks fica reservado como projeto futuro independente.
