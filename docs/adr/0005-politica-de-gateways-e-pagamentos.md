# ADR 0005: Política de Gateways de Pagamento e Integridade Financeira

- **Status:** Aprovado
- **Data:** 2026-09-01
- **Contexto:** Compatibilidade de gateways e processamento de pagamentos no GVN Checkout

---

## Contexto e Problema

O projeto histórico `checkout-woo-2` utilizava práticas frágeis de integração com gateways de pagamento, tais como:
- Monkey-patch em métodos do jQuery (`$.fn.val`, `$.fn.html`);
- Substituição global de `$.ajax`;
- Variáveis globais artificiais para forçar comportamento do PagSeguro;
- Campos ocultos preenchidos com dados falsos (`N/A`, `00000-000`, `SP`) para satisfazer validações de gateways terceiros;
- Aceite automático forçado de termos e condições.

Essas práticas violam as diretrizes do WooCommerce, quebram outros gateways e introduzem severos riscos de cobranças indevidas ou dados fiscais corrompidos.

## Decisão

1. **Proibição Absoluta de Hacks:** Fica estritamente proibido qualquer monkey-patching em bibliotecas globais, APIs do navegador ou nos objetos de gateways.
2. **Preservação do Ciclo de Vida Nativo dos Gateways:**
   - O wrapper de pagamento deve renderizar o container `#payment` oficial e disparar os eventos JavaScript nativos (`init_checkout`, `payment_method_selected`, `updated_checkout`).
   - Respeitar os textos dos botões definidos por cada gateway (`order_button_text`).
   - Não interceptar nem forjar respostas de redirecionamento externo ou desafios 3DS.
3. **Isolamento de Webhooks e Callbacks:** O plugin GVN Checkout **não processa webhooks nem callbacks** de pagamento; essa responsabilidade permanece integralmente sob o gateway e o WooCommerce.
4. **Critério Formal de Homologação:** Um gateway só recebe status "suportado" após aprovação na matriz de testes com fluxos completos de cartão com tokenização/parcelas, Pix com QR Code e boleto bancário no ambiente sandbox verificado.

## Consequências

- **Positivas:** Estabilidade operacional máxima; ausência de falsos positivos ou falhas misteriosas em atualizações de gateways; conformidade com os padrões oficiais do WooCommerce.
- **Negativas:** Gateways que utilizem DOM invasivo ou dependências não documentadas precisarão de correções no próprio gateway ou serão marcados como incompatíveis na matriz.
