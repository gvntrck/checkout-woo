# Diagnóstico de requisitos de gateways

Status: planejado

## Objetivo

Exibir, no gerenciador de campos, os requisitos conhecidos dos gateways de pagamento ativos e alertar conflitos com a configuração do checkout, sem forjar dados nem alterar gateways de terceiros.

## Escopo

- Modal "Requisitos dos gateways" com gateway, variante quando conhecida, campo, regra, fonte e confiança.
- Resolver que agrega campos padrão do checkout, adaptadores próprios e uma extensão pública por filtro.
- Alertas para campos ausentes, desativados, ocultos ou não obrigatórios quando o requisito for confirmado.
- Proteção no checkout: campos configurados pelo administrador continuam submetidos e a validação do GVN respeita a condição `payment_method`.

## Fora do escopo

- Inferir universalmente validadores PHP/JavaScript de qualquer gateway ou preencher valores fictícios.
- Modificar fluxo, tokenização, 3DS, webhooks ou retorno de pagamento de terceiros.
- Homologar gateways não selecionados, Checkout Blocks ou criar regras persistentes por clique no modal.

## Contrato e arquitetura

- Criar `GatewayRequirementsResolver` em `src/Payments/`, registrado no `Plugin`.
- Resultado normalizado: `gateway_id`, `variant|null`, `field_key`, `requirement` (`required` ou `present`), `source` (`woocommerce`, `adapter`, `extension`), `confidence` (`confirmed` ou `unknown`) e mensagem.
- Campos gerais vêm de `woocommerce_checkout_fields`; requisito por variante só vem de adaptador/integração que o declare.
- Expor `gvn_checkout_gateway_requirements` para adaptadores terceiros; validar estritamente o schema recebido.
- Os adaptadores iniciais são definidos somente após escolher gateways e versões da matriz de compatibilidade.

## Áreas afetadas

`src/Plugin.php`, novo módulo `src/Payments/`, `includes/class-gvn-custom-fields.php`, `includes/class-gvn-admin.php`, `assets/js/gvn-admin-fields.js`, `assets/css/gvn-admin-fields.css`, testes e matriz de compatibilidade.

## Critérios de aceite

- Administrador vê todos os gateways registrados/ativos e requisitos gerais conhecidos, sem credenciais ou PII.
- Gateway sem declaração aparece como "não declarado; teste de homologação necessário", nunca como compatível.
- Um adaptador pode informar CPF para boleto sem impor CPF a cartão/Pix do mesmo gateway.
- Campo em conflito aparece com causa e link/ação para editar; o modal não altera configurações sozinho.
- O checkout nunca oculta campo marcado como requisito confirmado da forma de pagamento selecionada; a regra é validada no servidor.
- Nenhum dado sintético é enviado e o ciclo nativo do gateway permanece intacto.

## Tarefas

1. Criar DTO/normalizador e resolver; mapear gateways registrados e aplicar a fonte padrão, adaptadores e filtro de extensão. Critério: saída determinística, deduplicada e sem requisitos inventados.
2. Integrar o resolvedor à decisão de ocultar campos e à validação condicional por `payment_method`. Critério: conflito confirmado preserva o input e falha corretamente se faltar valor.
3. Adicionar o modal e os badges de conflito ao editor existente. Critério: acessível por teclado, escapado, responsivo e somente leitura.
4. Documentar o contrato do filtro, adaptadores homologados e resultado no `docs/compatibility-matrix.md`. Critério: cada gateway/variante é declarado como suportado, não testado ou não declarado.

## Testes e segurança

- Unitários: normalização, precedência, deduplicação, requisito por variante e ignorar extensão inválida.
- Integração/E2E: gateway fake e cada adaptador homologado; trocar método, submeter vazio/preenchido e confirmar que não há mutação do fluxo do gateway.
- Manual sandbox: cartão, Pix e boleto para toda combinação marcada como suportada na matriz.
- Exigir `manage_woocommerce`/`manage_options`, nonce se houver endpoint, sanitizar/escapar saída e nunca registrar CPF/endereço, tokens ou credenciais.

## Dependências e decisão pendente

Antes da implementação, definir os gateways e versões da primeira rodada de homologação; cada adaptador entra como tarefa separada, não como detecção genérica.
