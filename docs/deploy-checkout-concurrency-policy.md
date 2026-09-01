# Política de Concorrência de Deploy e Formulários em Aberto

Este documento formaliza a política de compatibilidade para formulários de checkout em andamento durante atualizações de versão (*zero-downtime deploy*) e especifica o caso de teste correspondente para o **GVN Checkout for WooCommerce** (Tarefa F0.22).

---

## 1. Cenário e Desafio Técnico

Em lojas de alto tráfego, compras acontecem continuamente. No momento exato em que uma nova versão do plugin é implantada (*deploy*), clientes podem estar com a página de checkout já renderizada no navegador (Versão N) e submeter o pedido segundos após o código novo (Versão N+1) ter entrado em operação.

### Riscos Potenciais
- Rejeição abrupta do formulário por incompatibilidade de nomes de campos no `$_POST`.
- Erro fatal por expectativa de novos parâmetros obrigatórios que a interface antiga não enviou.
- Invalidação prematura de nonces ou perda de metadados durante a gravação do pedido.

---

## 2. Regras Obrigatórias de Compatibilidade

1. **Janela de Compatibilidade de Payload (N e N+1):**
   - A versão N+1 **deve obrigatoriamente aceitar e processar** os nomes de campos e a estrutura de `$_POST` gerados pela versão N.
   - Nenhuma renomeação ou remoção de campos deve quebrar submits originados da versão imediatamente anterior.
2. **Nonces do WooCommerce:**
   - Nonces padrão do WooCommerce possuem validade temporal de 12 a 24 horas (`wp_nonce_tick`).
   - O deploy não altera a constante `NONCE_SALT` e, portanto, nonces gerados na versão N continuam válidos no recebimento pela versão N+1.
3. **Validação de Cart Hash e Totais:**
   - O núcleo do WooCommerce compara o hash do carrinho (`cart_hash`).
   - Caso uma alteração de preço ocorra durante o deploy, o WooCommerce recusa o submit e exige revisão de totais, impedindo cobranças com preços desatualizados.
4. **Assinatura de Schema Contextual:**
   - O formulário pode transportar a flag `gvn_schema_version`. Na ausência da flag (payload antigo), o backend assume o schema de campos legado e aplica os fallbacks seguros.

---

## 3. Especificação do Caso de Teste E2E (Deploy Concorrente)

| ID do Teste | `E2E-DEPLOY-CONCURRENCY-001` |
|---|---|
| **Objetivo** | Comprovar que um checkout renderizado na versão N conclui com sucesso após deploy da versão N+1. |
| **Pré-condição** | Loja com produto simples físico no carrinho e plugin ativo na versão N. |

### Passos de Execução:
1. **Passo 1 (Renderização):** O cliente acessa a página de checkout na versão N. O navegador faz o parse do HTML, inputs, valores e tokens (`woocommerce-process-checkout-nonce`).
2. **Passo 2 (Deploy do Servidor):** Sem recarregar a aba do cliente, o plugin é atualizado no servidor para a versão N+1 (arquivos substituídos e cache OPcache limpo).
3. **Passo 3 (Submissão):** O cliente preenche os dados cadastrais e clica em "Finalizar Pedido", enviando o POST gerado pelo DOM da versão N.
4. **Passo 4 (Processamento):** O servidor (Versão N+1) recebe a requisição, valida o nonce, normaliza os campos com base na camada de compatibilidade, cria o pedido `WC_Order` e aciona o gateway.

### Critérios de Sucesso (Asserts):
- [ ] Requisição HTTP retorna status 200 / JSON de sucesso do WooCommerce (`result: "success"`).
- [ ] O pedido é persistido no banco com status correto (`pending` ou `processing`).
- [ ] Todos os campos customizados e brasileiros enviados são gravados nos metadados do pedido.
- [ ] Nenhum fatal error, warning ou notice é registrado no `debug.log`.
- [ ] O cliente é redirecionado normalmente para a página de confirmação (`order-received`).
