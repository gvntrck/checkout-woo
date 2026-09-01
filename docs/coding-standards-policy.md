# Política de Padrões de Código e Diretrizes para `phpcs:ignore`

Este documento estabelece as diretrizes de qualidade de código e a política obrigatória para o uso de anotações de supressão de análise estática e lint (`phpcs:ignore` e `@phpstan-ignore`) no projeto **GVN Checkout for WooCommerce** (Tarefa F1.13).

---

## 1. Regra Geral

**Proibição de Supressões Genéricas:** É terminantemente proibido utilizar comentários de supressão genéricos como `// phpcs:ignore` ou `// @phpstan-ignore-next-line` sem especificar a regra exata violada e uma justificativa técnica clara.

Supressões amplas mascaram vulnerabilidades de segurança (ex: XSS, ausência de escaping ou de nonces) e dívidas técnicas silenciosas.

---

## 2. Requisitos para Uso de `phpcs:ignore`

Caso seja estritamente necessário suprimir um aviso ou erro do PHPCS:

1. **Especificar a Regra Exata:** A supressão deve nomear o *sniff* ou regra pontual:
   ```php
   // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce já verificado pelo WooCommerce na rota pai.
   ```
2. **Justificativa Obrigatória na Mesma Linha:** Incluir comentário explicativo após `--` detalhando por que a regra não se aplica e qual controle compensatório foi implementado.
3. **Escopo Mínimo:** Preferir `phpcs:ignore` de linha única em vez de `phpcs:disable` de arquivo inteiro. O uso de `phpcs:disable` é reservado exclusivamente para templates legados temporários e exige aprovação técnica formal.

---

## 3. Categorias Proibidas de Supressão

Nunca suprima as seguintes regras sem refatoração do código:

- `WordPress.Security.EscapeOutput`: A saída deve ser sempre escapada no contexto apropriado (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`).
- `WordPress.Security.ValidatedSanitizedInput`: Toda entrada de superglobais deve ser sanitizada com função adequada antes do uso.
- `WordPress.DB.PreparedSQL`: Queries SQL dinâmicas devem obrigatoriamente utilizar `$wpdb->prepare()`.
