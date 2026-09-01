# ADR 0004: Gerenciador Canônico de Campos e Tratamento de Dados Brasileiros

- **Status:** Aprovado
- **Data:** 2026-09-01
- **Contexto:** Customização de formulário e conformidade fiscal/LGPD no GVN Checkout

---

## Contexto e Problema

O formulário de checkout necessita gerenciar campos específicos para o Brasil (CPF, CNPJ, Razão Social, Inscrição Estadual, Celular, Número e Bairro) e suportar regras condicionais (exibir CPF apenas se Pessoa Física; exibir CNPJ se Pessoa Jurídica), sem gerar incompatibilidades com outros plugins brasileiros instalados ou permitir que campos ocultos causem erros de validação.

## Decisão

1. **Schema Canônico Declarativo:** Cada campo possui ID imutável, nome enviado, tipo allowlisted, rótulo, contexto (`billing`, `shipping`, `order`), regras de visibilidade e máscaras visuais.
2. **Integração com Hooks Oficiais:**
   - Registrar campos exclusivamente via filtro `woocommerce_checkout_fields`.
   - Persistir metadados no hook `woocommerce_checkout_create_order` utilizando setters da classe `WC_Order` (compatibilidade total com HPOS).
   - Eliminar o uso do hook legado `woocommerce_checkout_update_order_meta`.
3. **Avaliação Server-Side de Condições:** O motor de condições (`ConditionEvaluator`) é compartilhado no PHP e reproduzido no frontend. Se um campo estiver oculto por condição no momento do submit, ele é considerado opcional no servidor e não é gravado com dados residuais.
4. **Proteção contra Mass Assignment:** O plugin nunca itera diretamente sobre `$_POST` para salvar metadados; apenas campos ativos do schema efetivo com chaves não reservadas são persistidos.
5. **Precedência com Plugins Brasileiros:** Quando plugins como *Claudio Sanches - Brazilian Market on WooCommerce* estiverem ativos, o GVN Checkout reutiliza os campos nativos do ecossistema e não duplica inputs de CPF/CNPJ.

## Consequências

- **Positivas:** Conformidade com HPOS; integridade dos dados no pedido; respeito à privacidade e minimização de dados; zero conflito com plugins BR consolidados.
- **Negativas:** Requer sincronização precisa entre os scripts de frontend e a validação do servidor.
