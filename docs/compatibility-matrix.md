# Matriz de Compatibilidade e Ambientes Homologados

Este documento registra a matriz formal de compatibilidade do **GVN Checkout for WooCommerce** (Seção 4.5 do plano de evolução).
Nenhuma célula permanece em branco: combinações sem execução em ambiente de homologação/CI são explicitamente marcadas como **Não testado**.

---

## 1. Matriz de Plataformas e Runtime

| Componente | Versão Alvo | Status Atual | Suite Requerida | Evidência / Artefato | Data |
|---|---|---|---|---|---|
| **WordPress** | 6.0 (mínima) | Não testado | Syntax, Unit, Integração | Aguardando staging/CI | 2026-09-01 |
| **WordPress** | 6.7 (estável) | Não testado | Suite completa | Aguardando staging/CI | 2026-09-01 |
| **WooCommerce** | 7.0 (mínima) | Não testado | Syntax, Unit, Integração | Aguardando staging/CI | 2026-09-01 |
| **WooCommerce** | 9.0+ (estável) | Não testado | Suite completa | Aguardando staging/CI | 2026-09-01 |
| **PHP** | 7.4 (mínima) | Não testado | Syntax, Unit | Job CI | 2026-09-01 |
| **PHP** | 8.0, 8.1, 8.2 | Não testado | Syntax, Unit | Jobs CI | 2026-09-01 |
| **PHP** | 8.3 (moderna) | ✅ Passou (Sintaxe) | Syntax | `php -l` via WSL CLI | 2026-09-01 |
| **HPOS** | Desligado (posts) | Não testado | CRUD e Queries | Teste de integração | 2026-09-01 |
| **HPOS** | Ligado com sync | Não testado | CRUD e Consistência | Teste de integração | 2026-09-01 |
| **HPOS** | Ligado sem sync | Não testado | Suite de pedidos | Teste de integração | 2026-09-01 |
| **Checkout Clássico** | Página oficial | Suporte pretendido | E2E completo | Execução manual/E2E | 2026-09-01 |
| **Checkout Blocks** | Qualquer versão | 🚫 Incompatível declarado | Declaração e notice | `FeaturesUtil::declare_compatibility` | 2026-09-01 |
| **Multisite** | Network active | A decidir | Ativação/Uninstall | Execução | 2026-09-01 |
| **Tema Padrão WP** | Twenty Twenty-Four | Não testado | Visual e Fluxo | E2E visual | 2026-09-01 |
| **Tema Popular** | Astra / Storefront | Não testado | Visual e Fluxo | E2E visual | 2026-09-01 |
| **Plugin BR Mercado** | Claudio Sanches BR | Não testado | Campos e Pedidos | Teste de integração | 2026-09-01 |

---

## 2. Matriz de Gateways de Pagamento

| Gateway | Plugin / Versão | Cartão / Tokenização | Pix (QR Code) | Boleto | Webhook / Status | Status Geral |
|---|---|---|---|---|---|---|
| **BACS (Transferência)** | Core WooCommerce | N/A | N/A | N/A | N/A | Não testado |
| **Cheque / COD** | Core WooCommerce | N/A | N/A | N/A | N/A | Não testado |
| **Mercado Pago** | A definir em O-01 | Não testado | Não testado | Não testado | Não testado | Não testado |
| **PagBank / PagSeguro** | A definir em O-01 | Não testado | Não testado | Não testado | Não testado | Não testado |
| **Asaas / Woovi / Pagar.me**| A definir em O-01 | Não testado | Não testado | Não testado | Não testado | Não testado |

O diagnóstico de campos segue a mesma regra de cobertura: BACS, Cheque/COD e os demais
gateways acima permanecem **não declarados** até que um adaptador com versão homologada
seja adicionado. Não há gateway/variante marcada como **suportada** nesta versão; uma
declaração recebida pelo filtro sem evidência de homologação é exibida como **não testada**
e nunca é convertida automaticamente em compatibilidade.

---

## 3. Matriz de Navegadores e Dispositivos

| Família / Navegador | Viewport | Cobertura | Status |
|---|---|---|---|
| **Chromium Desktop** | 1920x1080 | Versão estável | Não testado |
| **Firefox Desktop** | 1920x1080 | Versão estável | Não testado |
| **Safari macOS** | 1440x900 | Versão estável | Não testado |
| **Safari iOS (Mobile)** | 390x844 | iOS estável | Não testado |
| **Chrome Android (Mobile)**| 412x915 | Android estável | Não testado |

---

## Regra de Atualização
Esta matriz deve ser atualizada a cada rodada de homologação e a cada execução da suite automatizada de testes (F1/F13).

## 5. Contrato de Requisitos de Campos

O GVN Checkout agrega os campos obrigatórios gerais expostos por `woocommerce_checkout_fields` e declara requisitos específicos de gateways através do filtro `gvn_checkout_gateway_requirements`.

Integrações devem retornar uma lista com `field_key`, `requirement` (`required` ou `present`), `variant` opcional, `confidence` (`confirmed` ou `unknown`), `source` (`adapter` ou `extension`) e `message` opcional. Regras condicionais podem usar `when` no mesmo formato de condições dos campos (`logic` e `rules`).

Somente requisitos com confiança `confirmed` participam da proteção server-side. Um gateway sem declaração específica aparece no painel como **não declarado** e exige homologação; o GVN não infere requisitos lendo JavaScript, executando validadores de cobrança ou preenchendo dados fictícios.

### Cobertura por gateway/variante

| Declaração | Cobertura | Comportamento no painel e checkout |
|---|---|---|
| Adaptador homologado | Suportada | Requisitos confirmados preservam o campo e são validados no servidor. |
| Integração declarada, sem homologação registrada | Não testada | A fonte e a confiança aparecem no modal; o administrador deve validar em sandbox. |
| Nenhuma declaração específica | Não declarada | O gateway aparece como não declarado e não recebe requisitos inventados. |

As linhas específicas de uma variante (por exemplo, `boleto`) devem informar `variant` no
item do filtro. O resolver deduplica declarações por gateway/variante/campo, priorizando
adaptador sobre extensão, confiança confirmada sobre desconhecida e `required` sobre
`present`. Em gateways que reutilizam o mesmo ID para mais de uma modalidade, o adaptador
deve enviar `payment_method_variant`, `gateway_variant` ou `payment_variant` no contexto;
sem essa seleção, uma variante específica não é aplicada. Itens sem `field_key`,
`requirement`, `confidence` ou `source` válidos (inclusive payloads embrulhados em uma
chave `requirements`) são ignorados integralmente.
