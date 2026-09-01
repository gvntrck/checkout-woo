# ADR 0003: Armazenamento Centralizado de Configurações e Migrações Aditivas

- **Status:** Aprovado
- **Data:** 2026-09-01
- **Contexto:** Gerenciamento de opções e persistência de dados do GVN Checkout

---

## Contexto e Problema

O plugin legado dispersava dezenas de opções individuais no banco de dados (`gvn_checkout_header_text`, `gvn_checkout_button_color`, etc.) através de chamadas diretas a `get_option()` e `update_option()`, sem schema unificado, tipos estritos ou versionamento de migração (`schema_version`).

## Decisão

1. **Centralização de Opções:** Todas as configurações serão estruturadas em três opções unificadas no banco:
   - `gvn_checkout_settings`: Configurações gerais, visuais, order bump e gateways.
   - `gvn_checkout_fields`: Schema descritivo dos campos de formulário e condições.
   - `gvn_checkout_schema_version`: Inteiro indicando a versão do schema de dados ativo.
2. **Camada de Repositório (`SettingsRepository`):** Nenhum módulo novo deve chamar `get_option()` diretamente. Todas as leituras e escritas passam pelo repositório central, que aplica tipagem, sanitização, validação e cache em memória.
3. **Migrações Incrementais e Aditivas:**
   - Cadeias de migração N → N+1 executadas sob lock transiente não bloqueante.
   - Opções legadas são preservadas intactas durante a janela de rollback (mínimo de 1 versão).
   - O valor de `schema_version` só é incrementado após a validação completa de todos os passos da migração.
   - Falhas mantêm as opções antigas ativas e liberam o lock para execução manual no admin.

## Consequências

- **Positivas:** Redução de queries no autoload do WordPress; garantia de valores válidos e seguros em runtime; migrações testáveis e reversíveis sem perda de dados.
- **Negativas:** Requer etapa de bootstrap de migração ao atualizar de versões muito antigas.
