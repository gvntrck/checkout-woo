# Campos condicionais — exibir/ocultar campos por condição de outro campo

Status: concluído (implementado em 2026-09-09; checklist manual §12 pendente do lojista)

## 1. Contexto e estado atual (auditado em 2026-09-09)

O recurso **já existe de ponta a ponta em forma funcional**, mas com dívidas e
inconsistências. Este plano consolida o que falta para considerar o recurso
completo, seguro e mantível. Não recomeçar do zero.

O que já existe e funciona:

- Modelo de dados: `field['conditions'] = { logic: 'and'|'or', rules: [{ field, operator, value }] }`
  com defaults normalizados em `GVN_Custom_Fields::get_fields()` (`includes/class-gvn-custom-fields.php:346`).
- Sanitização server-side: `GVN_Custom_Fields::sanitize_conditions()` (`includes/class-gvn-custom-fields.php:489`)
  com allowlist de operadores `equals, not_equals, filled, empty, contains, greater, less`.
- Avaliador server-side: `src/Fields/FieldConditionEvaluator.php` (`evaluate_rule`, `evaluate`, `is_field_visible`).
- Validação condicional: `src/Fields/FieldValidator.php:37` — campo oculto nunca é exigido;
  campo visível + `required` é exigido; e-mail validado quando preenchido.
- Persistência condicional HPOS-safe: `src/Fields/FieldOrderPersister.php:39` — campo oculto
  tem o meta deletado; visível é sanitizado via `FieldSanitizer` e persistido.
- Registro no Woo: `register_custom_fields_with_woo()` (`includes/class-gvn-custom-fields.php:541`)
  registra condicionais como `required=false` no Woo e delega obrigatoriedade ao `FieldValidator`.
- Frontend: `assets/js/gvn-checkout.js:234` (`bindConditionalFields`, `evaluateAllConditions`,
  `evaluateRules`, `show/hideConditionalField`) + CSS `.gvn-field--conditional-hidden { display:none !important }`
  (`assets/css/gvn-checkout.css:343`).
- Templates: os 4 layouts renderizam `data-conditions` + `data-required` + classe
  `gvn-field--conditional` (`templates/checkout-template.php:134`, `layout-split.php:139`,
  `layout-minimal.php:142`, `layout-corporate.php:155`).
- Admin: seção "Condições de exibição" por campo com lógica E/OU, add/remove de regras,
  trigger `payment_method` + campos da lista (`includes/class-gvn-admin.php:652`,
  `assets/js/gvn-admin-fields.js:478` `buildConditionsSection`, `collectConditions`).
- Precedência de gateway: `isGatewayRequiredField()` no JS (`gvn-checkout.js:284`) e
  `validate_gateway_requirements()` no PHP (`FieldValidator.php:92`) — requisito confirmado
  do gateway selecionado força exibição/validação mesmo contra condição visual.
- Testes: `tests/Unit/FieldConditionEvaluatorTest.php` cobre operadores e lógica E/OU.

Lacunas e dívidas mapeadas (é isto que o plano resolve):

1. **Defaults divergentes.** `SettingsSchema::get_default_fields()` tem CPF/CNPJ/empresa/IE
   com `conditions` PF/PJ (`value 1/2`), enquanto `GVN_Custom_Fields::get_default_fields()`
   não tem nenhuma `conditions` e usa valores `pf/pj`. Duas fontes de verdade.
2. **Aliases não documentados.** O avaliador aceita `==, !=, eq, neq, >, <`, mas o admin e o
   `sanitize_conditions` só aceitam os canônicos. Comportamento para dado legado é implícito.
3. **Sem proteção contra ciclo/auto-referência.** Nada impede `campo A` depender de `A`,
   nem `A→B→A`. O JS faz 2 passagens fixas "para cascata" (`gvn-checkout.js:245-253`) —
   frágil, sem convergência garantida.
4. **Trigger fantasma sem semântica definida.** Regra apontando para chave inexistente/desativada
   hoje avalia como `''` — na prática oculta o campo para quase todos os operadores, sem avisar o admin.
5. **Valor da regra é texto livre.** Para trigger `select`/`payment_method` o admin digita o valor
   à mão (erro de digitação = campo eternamente oculto). Sem validação cruzada contra `options` do trigger.
6. **`hideConditionalField` limpa valor + dispara `change`** (`gvn-checkout.js:398`) — pode gerar
   reavaliação recursiva e corre contra ViaCEP/autofill; sem guard/debounce.
7. **Re-render do Woo (`updated_checkout`)**: o listener reavalia (`gvn-checkout.js:804`), mas se o
   fragmento substituir `.gvn-fields-dynamic` os bindings delegados precisam ser confirmados por layout.
8. **Acessibilidade.** Campo oculto sai da tela via `display:none` (ok), mas sem `aria-hidden`/
   `tabindex` management nem `disabled` — input continua focável via teclado em alguns layouts
   antes do `slideUp` terminar.
9. **Cobertura de testes incompleta.** Faltam testes para `sanitize_conditions`, `has_conditions`,
   `FieldValidator` com campo oculto, `FieldOrderPersister` deletando meta oculto, precedência de
   gateway, e nenhum teste JS.
10. **Docs/UX.** `readme.md:141` documenta operadores, mas a aba Ajuda não menciona condicionais;
    sem exemplo canônico nem guia de `payment_method`.

## 2. Objetivo

Permitir que o lojista configure, por campo, **regras de exibição baseadas no valor de outro
campo** (incluindo `payment_method`), com avaliação idêntica no frontend (tempo real) e no
backend (validação + persistência), sem quebrar os 4 layouts nem o contrato do template clássico.

Exemplos canônicos:

- `billing_cpf` visível se `billing_persontype equals pf`.
- `billing_cnpj` + `billing_company` visíveis se `billing_persontype equals pj`.
- `billing_ie` visível se `billing_persontype equals pj AND billing_has_ie equals yes` (lógica `and`).
- Campo "Observação de entrega" visível se `payment_method equals bacs OR shipping_method contains motoboy` (lógica `or`).

## 3. Escopo

- Modelo `conditions { logic, rules[] }` com operadores canônicos:
  `equals, not_equals, filled, empty, contains, greater, less`.
- Lógica `and` (TODAS) / `or` (QUALQUER) por campo.
- Triggers: qualquer campo habilitado da lista + `payment_method` (+ variantes de gateway
  via `GatewayRequirementsResolver` quando aplicável).
- Avaliação em 3 camadas com a mesma semântica:
  1. JS tempo real (mostrar/ocultar, gerenciar `required`, limpar valor oculto).
  2. PHP validação (`FieldValidator` — oculto nunca exigido).
  3. PHP persistência (`FieldOrderPersister` — oculto nunca persistido, meta residual removido).
- Editor admin por campo (regras dinâmicas, sem reload), com validações de sanidade.
- Precedência preservada: requisito **confirmado** do gateway selecionado força exibição/validação
  (nunca permitir bypass escondendo o campo).
- Contrato de template inegociável mantido em todos os layouts:
  `form[name=checkout]`, nonce, 15 hooks na ordem do clássico, `payment_method` +
  `payment_fields()`, IDs `#gvn-order-items`, `#gvn-order-totals`, `#payment`, `#place_order`,
  `#gvn-coupon-*`, `#gvn-bump-checkbox`, `#customer_details`; CSS escopado `.gvn-layout-{slug}`, sem CDN.

## 4. Fora do escopo

- Condicionais por papel de usuário, carrinho (subtotal, cupom, categoria), geolocalização ou data —
  apenas valor de outro campo do formulário + `payment_method`.
- Condicionar seções inteiras, gateways, order bump ou cupom (só campos de `gvn_checkout_fields`).
- Obrigatoriedade condicional independente da visibilidade (ex.: "visível mas opcional, obrigatório
  só se X") — neste ciclo, `required` vale **somente quando visível**.
- Regras aninhadas com parênteses/grupos (só um nível: lista de regras + `and`/`or`).
- Alterar fluxo, tokenização, 3DS, webhooks ou retorno de gateways terceiros.
- Migrar para Checkout Blocks.

## 5. Contrato e arquitetura

### 5.1 Modelo de dados (única fonte de verdade)

```php
'conditions' => [
    'logic' => 'and' | 'or',          // default 'and'
    'rules' => [
        ['field' => 'billing_persontype', 'operator' => 'equals', 'value' => 'pf'],
        // ...
    ],
],
```

- `field`: `sanitize_key`, não vazio, `!==` da própria chave (auto-referência rejeitada).
- `operator`: allowlist canônica `equals, not_equals, filled, empty, contains, greater, less`.
  Aliases legados (`==, !=, eq, neq, >, <`) continuam avaliados pelo `FieldConditionEvaluator`
  por compatibilidade, mas o sanitizador os **normaliza para o canônico** na escrita.
- `value`: `sanitize_text_field`; ignorado (forçado `''`) para `filled`/`empty`.
- Ausente/vazio = sempre visível (`is_field_visible` retorna `true`).
- Formato legado (lista numérica direta sem envelope `logic/rules`) continua avaliado como `and`
  na leitura, mas é normalizado para o envelope na escrita.

### 5.2 Semântica de avaliação (idêntica PHP/JS)

| Operador | Semântica |
|---|---|
| `equals` | comparação estrita de string `strval(trigger) === value` |
| `not_equals` | `!==` |
| `filled` | `trim(strval) !== ''` |
| `empty` | `trim(strval) === ''` |
| `contains` | case-insensitive substring; `value === ''` ⇒ `true` |
| `greater` / `less` | ambos numéricos (`is_numeric`/`parseFloat`) e comparação float; senão `false` |
| desconhecido | `false` (fail-closed: não exibe) |

- `and`: todas as regras verdadeiras ⇒ visível. `or`: qualquer uma ⇒ visível.
- Regra com `field` vazio é **ignorada** (não conta); se nenhuma regra válida restar ⇒ visível.
- Trigger inexistente/desativado no POST ⇒ valor `''` (documentado; admin avisa — ver tarefa 3).
- Leitura de valor no JS: `select/input/textarea` do wrapper `[data-field-key]`; `checkbox` ⇒
  `val()` se checked senão `''`; `payment_method` ⇒ `input[name=payment_method]:checked`
  (fallback para fora do container, pois gateways ficam fora de `.gvn-fields-dynamic` em alguns layouts).
- Leitura no PHP: `$data[$trigger] ?? ''`, com `$data = array_merge($data_wc, $_POST_unslashed)`
  nos hooks de validação/persistência.

### 5.3 Camadas e arquivos

- `src/Fields/FieldConditionEvaluator.php` — avaliar (adicionar normalizador + detector de ciclo).
- `includes/class-gvn-custom-fields.php` — `sanitize_conditions()` (endurecer), `has_conditions()`,
  `register_custom_fields_with_woo()`, validação/persistência (inalterados na essência).
- `src/Fields/FieldValidator.php`, `src/Fields/FieldOrderPersister.php` — inalterados, cobertos por testes novos.
- `templates/checkout-template.php` + `templates/checkout/layout-{split,minimal,corporate}.php` —
  manter `data-conditions` (JSON escapado), `data-required`, classe `gvn-field--conditional`.
- `assets/js/gvn-checkout.js` — reescrever `bind/evaluateAllConditions` com convergência limitada.
- `assets/js/gvn-admin-fields.js` + `includes/class-gvn-admin.php:652` — editor com validações e
  value-select inteligente.
- `assets/css/gvn-checkout.css` + `gvn-admin-fields.css` — manter `.gvn-field--conditional-hidden`,
  adicionar badge "Condicional" na lista e aviso de trigger inválido.
- `src/Settings/SettingsSchema.php` — unificar/remover `get_default_fields()` duplicado (ver tarefa 1).

## 6. Áreas afetadas

`src/Fields/`, `src/Settings/SettingsSchema.php`, `includes/class-gvn-custom-fields.php`,
`includes/class-gvn-admin.php`, `templates/checkout-template.php`, `templates/checkout/layout-*.php`,
`assets/js/gvn-checkout.js`, `assets/js/gvn-admin-fields.js`, `assets/css/gvn-checkout.css`,
`assets/css/gvn-admin-fields.css`, `tests/Unit/`, `readme.md`, `readme.txt`.

## 7. Critérios de aceite (globais)

1. Lojista cria campo "CNPJ" com regra `billing_persontype equals pj`; no checkout ele aparece só
   quando `pj` está selecionado, em **tempo real e sem reload**, nos 4 layouts.
2. Campo oculto com `required=true`: submit passa sem ele; campo visível vazio: submit falha com
   `O campo "X" é obrigatório.` (server-side, mesmo com JS desativado).
3. Campo oculto com valor forjado no POST: **não** é persistido (`_meta` ausente) e se havia meta
   residual ele é removido; campo visível é sanitizado por tipo e persistido.
4. `payment_method` como trigger funciona trocando de gateway sem reload da página.
5. Requisito confirmado do gateway selecionado impede ocultação e exige o campo no servidor.
6. Auto-referência e ciclo `A↔B` são rejeitados no save com mensagem; trigger para chave
   inexistente/desativada gera aviso (não erro bloqueante) no admin e no relatório.
7. `update_checkout` (cupom, bump, gateway) não quebra avaliação nem perde `required` restaurado.
8. Sem JS: todos os campos condicionais são exibidos e a validação server-side decide (progressive enhancement).
9. Suite unitária verde via WSL + sem regressão nos testes que travam hooks/output do clássico.

## 8. Tarefas

### Tarefa 1 — Unificar defaults e normalizar aliases (pré-requisito)
**Problema:** `SettingsSchema::get_default_fields()` (com `conditions`, valores `1/2`) e
`GVN_Custom_Fields::get_default_fields()` (sem `conditions`, valores `pf/pj`) divergem.
**Fazer:**
- Decidir **uma** fonte: `GVN_Custom_Fields::get_default_fields()` (é a usada pelo checkout/admin).
  `SettingsSchema::get_default_fields()` passa a delegar ou é removida (verificar usos + fixture
  `tests/fixtures/options/legacy-1.13.6-defaults.json` antes).
- Alinhar `billing_persontype`: `options "pf|Pessoa Física\npj|Pessoa Jurídica"`, `default_option pf`,
  e `billing_cpf` com regra `persontype equals pf`, `billing_cnpj/company/ie` com `equals pj`
  (padrão desligado/`enabled=false` para não mudar checkout de quem já usa, exceto CPF que já é ativo).
- Em `FieldConditionEvaluator`, extrair `normalize_operator()` (`==→equals`, `!=→not_equals`,
  `eq→equals`, `neq→not_equals`, `>→greater`, `<→less`) e usar em `evaluate_rule()`.
- Em `sanitize_conditions()`, normalizar aliases para o canônico na escrita.
**Aceite:** nenhum teste de fixture quebra; `composer dump-autoload` + `phpunit --testsuite unit` verde;
diferença documentada no plano como nota de migração (sem migração automática de dados legados —
leitura tolerante basta).

### Tarefa 2 — Endurecer `sanitize_conditions()` + `has_conditions()`
**Arquivo:** `includes/class-gvn-custom-fields.php:489`.
**Fazer:**
- Rejeitar regra com `field` vazio, `operator` fora da allowlist, ou `field === própria key`
  (retornar erro AJAX `Campo "X": condição ignora o próprio campo` — coleta todos os erros, não só o primeiro).
- Detectar ciclo direto `A→B→A` no lote do save (mapa `key → triggers`); ciclo indireto longo:
  validar por DFS limitado (profundidade ≤ 10) e rejeitar com mensagem `Ciclo detectado entre campos: A → B → A`.
- `filled`/`empty` forçam `value=''`.
- Cap de regras por campo (ex.: 10) contra abuso.
- Avisos não-bloqueantes (retornados no JSON do save + badge no admin): trigger inexistente na lista,
  trigger desativado (`enabled=false`), trigger condicional (cascata — permitido, mas avisado).
- `value` para trigger `select` com `options` conhecidas: avisar se o valor não está entre as opções
  parseadas (não bloquear — `default_option`/variante podem divergir).
**Aceite:** testes unitários `sanitize_conditions`: auto-ref rejeitada, ciclo `A↔B` rejeitado,
operador inválido descartado, `filled` zera valor, cap respeitado, aviso de trigger fantasma emitido.

### Tarefa 3 — Editor admin: value-select inteligente + validações visíveis
**Arquivos:** `includes/class-gvn-admin.php:652`, `assets/js/gvn-admin-fields.js:478`.
**Fazer:**
- Ao trocar o trigger (`.gvn-rule-field`), se o trigger for `select` da lista (ler `options` da linha
  correspondente) ou `payment_method` (ler gateways do `GatewayRequirementsResolver` via `gvn_admin_params`),
  trocar o input texto por `<select>` com os valores válidos; para `filled/empty` esconder o valor (já existe).
- Excluir a própria chave da lista de triggers (já existe no PHP; replicar no `getFieldOptionsForConditions`
  dinâmico ao adicionar linha nova — hoje usa DOM, conferir).
- Badge "Condicional" no header da linha quando `rules.length > 0`; ícone de aviso quando trigger
  inválido/desativado (tooltip com causa).
- Re-render das options de trigger ao renomear/adicionar/remover campo (hoje `buildConditionsSection`
  é estático por linha; adicionar `refreshAllConditionFieldOptions()` no save/add/remove/rename).
- Manter escaping (`escAttr/escHtml` no JS, `esc_attr/selected` no PHP) e `check_ajax_referer` +
  `manage_woocommerce|manage_options` (já existem).
**Aceite:** criar regra sem digitar valor à mão para `select`; renomear trigger atualiza os selects
dependentes; regra inválida mostra motivo inline; teste manual nos 4 cenários (texto, select, payment_method, filled).

### Tarefa 4 — Frontend: avaliação com convergência + guards
**Arquivo:** `assets/js/gvn-checkout.js:234`.
**Fazer:**
- Substituir as 2 passagens fixas por loop `do { avaliar todos } while (mudou && iterações < 5)`
  (cascata `A→B→C` converge; ciclo — que o backend agora impede — estaciona no cap sem travar).
- Guard de reentrância: flag `evaluating`; `hideConditionalField` limpa valor **sem** `trigger('change')`
  síncrono — agenda reavaliação via `requestAnimationFrame`/microtask ou chama `evaluateAllConditions`
  uma vez ao fim do lote (evita cascata exponencial de eventos).
- `showConditionalField`: restaurar `required` de `data-required`; `hideConditionalField`: `prop('required', false)`
  + limpar valor + `aria-hidden="true"` + `tabindex=-1` nos inputs; ao mostrar, remover.
- Escutar também `change` em `input[name=payment_method]` fora do container (já existe em `bindGateways`;
  garantir após `updated_checkout`, pois o Woo re-renderiza `#payment`).
- `getFieldValue`: estender para `radio` (checked do grupo) e `number` (`val()`); manter fallback
  para input externo ao container por `name`.
- Sem JS (noscript): nenhuma classe hidden aplicada no HTML inicial — todos visíveis; o JS aplica o
  estado inicial em `bindConditionalFields` (já é assim; **não** pré-ocultar no PHP para não quebrar no-JS).
**Aceite:** cascata de 3 níveis funciona digitando só no primeiro campo; trocar gateway atualiza
condicionais; abrir DevTools → Network → submit com campo oculto forjado não persiste (ver tarefa 6);
sem erros de recursão no console; `updated_checkout` (aplicar cupom) mantém estado correto.

### Tarefa 5 — Templates: paridade total + `aria`
**Arquivos:** os 4 templates (§1).
**Fazer:**
- Extrair o bloco de render do campo condicional para `templates/checkout/partials/field-wrapper-open.php`
  (só se tocar nos 4 — evita a 4ª cópia do bloco; dívida consciente citada no AGENTS.md manda extrair no 3º,
  já passou: fazer agora).
- Garantir em todos: `data-field-key`, `data-mask`, `data-conditions` (JSON), `data-required="1|0"`,
  classe `gvn-field--conditional` só quando `has_conditions()`.
- Adicionar `aria-hidden` inicial `false` (JS gerencia); **não** adicionar `hidden`/`style` inicial no PHP.
**Aceite:** diff dos 4 layouts mostra o mesmo wrapper; teste `CheckoutHooksTest` (hooks/output do clássico)
verde; validação manual HTML sem `data-conditions` quebrado (aspas escapadas).

### Tarefa 6 — Validação + persistência: cobertura de precedência
**Arquivos:** `FieldValidator.php`, `FieldOrderPersister.php` (código quase inalterado; foco em testes).
**Fazer:**
- Confirmar e travar por teste: oculto nunca exigido nem persistido (mesmo forjado); visível+required
  vazio falha; `select` com valor fora das `options` sanitiza para `''` (via `FieldSanitizer`) e então
  falha se required.
- Travar precedência de gateway: campo com condição falsa mas requisito confirmado para o
  `payment_method` selecionado continua exigido (PHP) e visível (JS).
- Verificar `$_POST` merge: `validate_custom_fields_after` usa `array_merge($data, $_POST)` —
  documentar que `$_POST` ganha (é o dado cru do submit).
**Aceite:** testes novos em `FieldValidatorTest` + `FieldOrderPersisterTest` para os 4 casos acima.

### Tarefa 7 — Testes automatizados
- **PHP (WSL):** estender `FieldConditionEvaluatorTest` (aliases, lista legada direta, regra sem `field`
  ignorada, trigger ausente ⇒ `''`); novo `CustomFieldsConditionsTest` para `sanitize_conditions()`
  (auto-ref, ciclo, cap, normalização de alias, `filled` zera valor) e `has_conditions()`; estender
  `FieldValidatorTest`/`FieldOrderPersisterTest` (§6).
- **JS:** sem runner JS no repo — cobertura via checklist manual documentado + considerar teste
  de fumaça com HTML estático (fora deste ciclo se pesar; registrar como dívida).
- **Comandos:** `wsl bash -c 'cd <wsl-path> && php vendor/bin/phpunit --testsuite unit'`,
  `php vendor/bin/phpstan analyse`, `bash scripts/syntax-check.sh`; `composer dump-autoload` antes
  se criar classe.
- **Matriz manual obrigatória** (anotar resultado no PR): PF mostra CPF/esconde CNPJ; PJ inverso;
  `payment_method` bacs vs pix; cascata 2 níveis; campo oculto forjado via DevTools; sem JS;
  `updated_checkout` após cupom; 4 layouts.

### Tarefa 8 — Docs e ajuda
- `readme.md:141` + `readme.txt:80`: documentar envelope `{logic, rules}`, operadores com semântica,
  trigger `payment_method`, precedência de gateway, limite de 10 regras, sem grupos aninhados.
- Aba Ajuda (`render_help_page`): adicionar card "Campos condicionais" com exemplo PF/PJ.
- Este plano sai de `planejado` para `concluído` ao fechar.

## 9. Testes e segurança (transversal)

- **AuthZ:** save só com `manage_woocommerce|manage_options` + `check_ajax_referer('gvn_admin_fields_nonce')`
  (já existe — não remover, travar por teste `AdminTest` se houver gancho).
- **XSS:** `data-conditions` via `wp_json_encode` + `esc_attr`; labels/valores via `esc_html/esc_attr`
  no PHP e `escAttr/escHtml` no JS; mensagens ViaCEP como texto (padrão já adotado).
- **Bypass:** nunca confiar no JS — validação e persistência reavaliam no servidor com dado cru;
  campo oculto forjado é descartado, não apenas ignorado na UI.
- **Dados sensíveis:** nunca logar valores de campo (só keys e contagem de regras) — `Logger` só com contexto seguro.
- **DoS/robustez:** cap de regras, DFS limitado, loop JS limitado, `is_numeric` guard em greater/less,
  `mb_stripos` com fallback (já existe).
- **Compatibilidade:** HPOS (só `WC_Order` API), Woo 7+, PHP 7.4+ (sem sintaxe 8.x em `src/`), sem CDN,
  phpcs segue estilo local (4 espaços; phpcs global está vermelho por pré-existentes — não reformatar).

## 10. Riscos e decisões

| Risco | Mitigação |
|---|---|
| Ciclo `A↔B` trava UI | Rejeitado no save (tarefa 2) + cap de iterações no JS (tarefa 4) |
| Renomear trigger quebra regras | Aviso de trigger fantasma + `refreshAllConditionFieldOptions` (tarefa 3) |
| Woo re-renderiza `#payment` e perde listener | Listeners delegados em `document`/container estável + reavaliação em `updated_checkout` |
| Divergência PHP×JS | Tabela de semântica única (§5.2) + mesmos casos nos testes PHP e no checklist manual JS |
| Mudança de defaults afeta lojistas | Novos defaults condicionais vêm `enabled=false` (opt-in); sem migração automática |

**Decisão pendente:** unificar `SettingsSchema::get_default_fields()` vs `GVN_Custom_Fields::get_default_fields()`
(tarefa 1) — verificar primeiro se `SettingsSchema` é usado por instalador/migrador antes de remover.

## 11. Ordem de execução sugerida

1. Tarefa 1 (defaults/aliases) → 2. Tarefa 2 (sanitização) → 3. Tarefa 6+7 PHP (travar servidor) →
4. Tarefa 4 (JS) → 5. Tarefa 3 (admin UX) → 6. Tarefa 5 (partials) → 7. Tarefa 7 manual + 8 docs.

## 12. Registro de implementação (2026-09-09)

Todas as tarefas foram implementadas. QA automatizado verde:
`phpunit --testsuite unit` (147 testes, 688 assertions), `phpstan analyse` sem erros,
`bash scripts/syntax-check.sh` OK, `node --check` nos dois JS alterados OK.

Arquivos tocados:

- `src/Fields/FieldConditionEvaluator.php` — `OPERATOR_ALIASES`, `CANONICAL_OPERATORS`,
  `normalize_operator()`; `evaluate_rule()` só com casos canônicos.
- `src/Settings/SettingsSchema.php` — defaults `billing_persontype` com `pf/pj` e operadores `equals`.
- `includes/class-gvn-custom-fields.php` — `MAX_CONDITION_RULES` (10),
  `CONDITION_CYCLE_MAX_DEPTH` (10), `sanitize_conditions($conditions, $own_key)` com normalização
  de alias, descarte de auto-referência, cap e `value=''` para `filled/empty`; novos
  `validate_conditions_batch()` (erros: ciclo; avisos: trigger inexistente/desativado, cascata,
  valor fora das opções) e `find_condition_cycle()` (DFS limitado); `ajax_save_fields` retorna
  erro bloqueante (auto-ref/ciclo) e `warnings` no payload de sucesso.
- `assets/js/gvn-checkout.js` — `evaluateAllConditionalFields()` com loop até convergir (teto 5),
  guard de reentrância, listener delegado no `document` (sobrevive a `updated_checkout`),
  `hideConditionalField` sem `trigger('change')` síncrono, `aria-hidden`/`tabindex`, suporte a `radio`.
- `assets/js/gvn-admin-fields.js` — value-select inteligente (`renderRuleValueControl`,
  `getTriggerInfo`, `getConditionGatewayOptions`), `refreshAllConditionFieldOptions()`,
  badges "Condicional"/⚠ (`updateConditionBadges`), exibição de `warnings` do save.
- `includes/class-gvn-admin.php` — badges server-rendered + card de ajuda "Campos condicionais".
- `assets/css/gvn-admin-fields.css` — estilos dos badges.
- `templates/checkout/partials/field.php` (novo) — wrapper único usado pelos 4 layouts;
  `aria-hidden="false"` inicial; sem pré-ocultar no PHP.
- Testes: `CustomFieldsConditionsTest` (novo), extensões em `FieldConditionEvaluatorTest`,
  `FieldValidatorTest` (valor forjado ignorado, precedência de gateway via filtro de extensão),
  `FieldOrderPersisterTest` (condição atendida persiste, `select` forjado sanitiza para `''`),
  `SettingsSchemaTest` (defaults `pf/pj`).
- Docs: `readme.md` (seção "Campos condicionais"), `readme.txt`, ajuda admin.

Desvio deliberado do plano (documentado): os defaults de `GVN_Custom_Fields::get_default_fields()`
foram mantidos **sem** `conditions`. Adicioná-las ali esconderia o CPF em instalações novas, pois o
trigger `billing_persontype` vem `enabled=false` e trigger desativado avalia como `''`. Os defaults
condicionais vivem em `SettingsSchema::get_default_fields()` (caminho do migrador), agora alinhados
a `pf/pj`. Sem migração automática de dados legados (leitura tolerante basta).

Checklist manual pendente (anotar no PR): PF mostra CPF/esconde CNPJ; PJ inverso; trigger
`payment_method` bacs vs pix; cascata 2 níveis; campo oculto forjado via DevTools não persiste;
sem JS todos visíveis + servidor decide; `updated_checkout` após cupom mantém estado; 4 layouts.
