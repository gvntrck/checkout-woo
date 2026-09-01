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
