# ADR 0006: Experiência da Página de Confirmação (Thank You / Order Received)

- **Status:** Aprovado
- **Data:** 2026-09-01
- **Contexto:** Apresentação pós-compra, instruções de pagamento e segurança contra enumeração

---

## Contexto e Problema

A página de confirmação de pedido precisa exibir um design moderno e convidativo, além de apresentar instruções claras para pagamentos pendentes (código Pix Copia e Cola, QR Code, linha digitável de boleto ou confirmação de cartão).

No entanto, implementações ingênuas de Thank You correm riscos de:
- Vulnerabilidade de enumeração de pedidos (exposição de dados de outros clientes por varredura de IDs);
- Supressão involuntária de instruções de gateways ou hooks de conversão/telemetria (`woocommerce_thankyou`, `woocommerce_before_thankyou`);
- Quebra de redirecionamentos externos retornados por gateways de terceiros.

## Decisão

1. **Permanência no Endpoint Nativo:** O plugin opera sobre o endpoint nativo `order-received` do WooCommerce, consumindo o objeto `$order` já devidamente autenticado e validado pelo núcleo do WooCommerce via chave segura `order_key` (`hash_equals`).
2. **Preservação Obrigatória de Hooks:**
   - O template customizado executa obrigatoriamente:
     - `do_action('woocommerce_before_thankyou', $order->get_id());`
     - `do_action('woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id());`
     - `do_action('woocommerce_thankyou', $order->get_id());`
   - Isso assegura que pixels de afiliados, tags de analytics e instruções de gateway continuem operando perfeitamente.
3. **Escaping Rigoroso de Dados:** Todos os dados do pedido (nome, produtos, totais, endereços) são escapados tardiamente com `esc_html` e `esc_attr`. A saída dos hooks oficiais de gateway preserva o contrato nativo do WooCommerce, inclusive scripts necessários às instruções e à atualização de status; essa saída é uma fronteira confiável de plugins instalados no servidor e não deve passar por `wp_kses_post()`, que remove as tags de script e expõe seu conteúdo como texto.
4. **Política para URL Customizada Externa:** Caso o lojista configure uma URL customizada de agradecimento, essa funcionalidade é tratada como um CTA pós-confirmação e não substitui o processamento nativo nem expõe parâmetros não assinados.

## Consequências

- **Positivas:** Proteção total contra enumeração e vazamento de PII; suporte integral aos plugins de analytics e aos gateways brasileiros; visual alinhado à identidade visual do checkout.
- **Negativas:** Requer que o endpoint `order-received` permaneça ativo na loja.
