# ADR 0002: Arquitetura de Templates, Partials e Política de Overrides

- **Status:** Aprovado
- **Data:** 2026-09-01
- **Contexto:** Customização visual e extensibilidade do GVN Checkout

---

## Contexto e Problema

O plugin necessita customizar a apresentação visual do checkout e da página de confirmação (Thank You) sem quebrar compatibilidade quando o tema do usuário tenta fazer overrides ou quando novos hooks são introduzidos pelo WooCommerce.

No código legado, o override de `thankyou.php` era realizado via filtro global indiscriminado de `wc_get_template`, afetando todos os pedidos do site.

## Decisão

1. **Partials e Hooks Próprios:** A customização visual é decomposta em partials reutilizáveis e hooks internos (`gvn_checkout_before_form`, `gvn_checkout_after_form`), preservando a chamada de todos os hooks nativos do WooCommerce.
2. **Carregamento de Templates com Namespacing:** Utilizar `wc_get_template()` apontando para `template_path = gvn-checkout/` e `default_path` apontando para o diretório do plugin, eliminando filtros globais não escopados.
3. **Versionamento e Diagnóstico de Overrides:** Todos os templates contêm a tag `@version`. O painel administrativo analisa overrides presentes no tema e alerta quando o template do tema estiver desatualizado em relação ao contrato da release ativa.
4. **Isolamento da Thank You:** A experiência personalizada da Thank You só é renderizada para pedidos cuja origem seja confirmada como `gvn-checkout` via metadados de pedido (`_gvn_checkout_version`), preservando o template padrão do WooCommerce para outros fluxos.

## Consequências

- **Positivas:** Fim de interferências globais em outras páginas; rastreabilidade de compatibilidade com temas; experiência customizada isolada.
- **Negativas:** Temas que queiram customizar o template GVN devem colocar seus arquivos no caminho específico `your-theme/gvn-checkout/`.
