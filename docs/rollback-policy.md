# Política de Rollback — GVN Checkout for WooCommerce

Esta política define os procedimentos operacionais, garantias de integridade e requisitos técnicos para execução de rollback do plugin **GVN Checkout for WooCommerce**.

---

## 1. Princípios Fundamentais

1. **Integridade de Dados e Pedidos:** O rollback de uma versão do plugin **nunca** deve exigir a restauração de um backup completo do banco de dados sobre pedidos novos. Pedidos gerados durante o período em que a versão mais recente esteve ativa possuem validade jurídica, fiscal e operacional.
2. **Migrações Aditivas:** Todas as migrações de dados e configurações do plugin são aditivas e preservam as opções legadas durante a janela de rollback (mínimo de 1 versão anterior). A remoção definitiva de opções antigas só ocorre após a janela de suporte ser encerrada.
3. **Pacote Anterior Preservado:** Toda release deve manter arquivado o pacote instalável (.zip) da versão imediatamente anterior acompanhado de seu respectivo checksum SHA-256 verificado.
4. **Acionamento Imediato em Incidentes Críticos (P0/P1):** Qualquer falha que cause cobrança incorreta, pedido duplicado, quebra no fluxo de pagamento ou vazamento de dados pessoais aciona imediatamente o protocolo de rollback.

---

## 2. Critérios de Acionamento (Gatilhos de Rollback)

O rollback de código e desativação de flags de novos recursos devem ser acionados imediatamente nas seguintes condições:

| Severidade | Condição / Gatilho | Ação Obrigatória |
|---|---|---|
| **P0 (Crítica)** | Total do pedido divergente ou cálculo de preço/cupom corrompido | Rollback imediato para a versão estável anterior |
| **P0 (Crítica)** | Cobrança ou criação de pedidos duplicados atribuível ao checkout | Rollback imediato |
| **P0 (Crítica)** | Exposição de PII (dados pessoais / documentos / cartões) em logs ou respostas públicas | Rollback imediato |
| **P1 (Alta)** | Falha generalizada em gateway homologado (ex: quebra de tokenização / 3DS / Webhook) | Rollback imediato |
| **P1 (Alta)** | Migração de opções com falha irrecuperável que impeça carregamento do checkout | Rollback e manutenção do caminho legado |
| **P2 (Média)** | Incompatibilidade visual pontual com tema ou aviso administrativo não impeditivo | Rollback não obrigatório; investigação ou correção em patch |

---

## 3. Preparação Pré-Deploy (Requisitos Obrigatórios)

Antes de qualquer atualização do plugin em ambiente de produção ou homologação:

1. **Arquivo do Pacote Anterior:** Confirmar disponibilidade do `.zip` da versão atual (ex: `gvn-checkout-1.13.7.zip`) e registrar seu hash SHA-256.
2. **Exportação / Backup das Opções do Plugin:**
   Exportar cirurgicamente as opções gerenciadas pelo plugin sem necessidade de dump total:
   ```bash
   wp option get gvn_checkout_settings --format=json > backup_gvn_checkout_settings.json
   wp option get gvn_checkout_fields --format=json > backup_gvn_checkout_fields.json
   wp option get gvn_checkout_schema_version > backup_gvn_checkout_schema_version.txt
   ```
   Em ambientes legados sem options unificadas, exportar as chaves `gvn_checkout_*`.
3. **Verificação da Janela de Rollback:** Garantir que o código da versão anterior consegue operar com o banco no estado atual (as chaves legadas continuam intactas).

---

## 4. Procedimento Operacional de Rollback

Em caso de necessidade de reversão:

### Passo 1: Interrupção Imediata e Isolamento
Se o plugin estiver configurado com feature flags, desative imediatamente a flag problemática (ex: `native_checkout_flow_v2 = no`, `fields_v2 = no` ou fallback nativo).

### Passo 2: Substituição do Pacote do Plugin
1. Fazer o deploy/instalação do pacote `.zip` da versão estável anterior via WP-CLI, FTP ou painel administrativo do WordPress:
   ```bash
   wp plugin install /caminho/para/gvn-checkout-versao-anterior.zip --force --activate
   ```
2. Limpar caches de opcode (OPcache) e caches de objeto (Redis/Memcached/Transients):
   ```bash
   wp cache flush
   ```

### Passo 3: Verificação de Estado das Opções
1. Confirmar que as opções legadas continuam presentes e íntegras.
2. Caso uma migração parcial tenha ocorrido, garantir que o `schema_version` ou flags não apontem para recursos inexistentes na versão restaurada.

### Passo 4: Tratamento dos Pedidos Criados Durante a Janela
1. **Não cancelar pedidos:** Manter todos os pedidos gerados na versão recente.
2. **Verificar metadados:** Os metadados gravados com getters/setters nativos do WooCommerce e HPOS permanecem legíveis.
3. **Callbacks pendentes:** Confirmar que webhooks e transições de status assíncronas do gateway continuam sendo processados normalmente pelo WooCommerce.

---

## 5. Checklist de Validação Pós-Rollback

Após a execução do rollback, os seguintes itens devem ser verificados:

- [ ] Página de checkout (`woocommerce_checkout_page_id`) renderiza normalmente.
- [ ] Formulário clássico exibe campos obrigatórios e máscaras esperadas.
- [ ] Gateways de pagamento ativos carregam sem erros de JavaScript no console.
- [ ] Cálculo de frete, impostos e cupons opera com precisão.
- [ ] Teste de pedido ponta a ponta (smoke test) concluído com sucesso em ambiente de homologação/sandbox.
- [ ] Nenhum fatal error, warning ou notice registrado no `debug.log`.
- [ ] Registro do incidente formalizado contendo causa raiz, versão afetada e plano de ação.
