# Multistep no card Seus Dados

Status: planejado

## Objetivo

Permitir que o administrador organize os campos GVN em qualquer quantidade de etapas dentro de `#customer_details`, com navegação elegante e responsiva, sem reload nem AJAX próprio no frontend.

Referência de UX: [Multi-Step Checkout for WooCommerce](https://br.wordpress.org/plugins/wp-multi-step-checkout/); aproveitar o conceito de reorganizar campos existentes, responsividade e teclado, sem copiar código ou ampliar o wizard para pagamento/resumo.

## Escopo

- Etapas ordenadas, com título editável, criadas no gerenciador; campos arrastáveis entre etapas e ordenáveis dentro delas.
- Wizard ativado automaticamente quando houver 2+ etapas; com uma etapa, manter aparência e comportamento atuais.
- Exibir título, `Etapa X de N`, `Voltar` e `Avançar`; indicadores não clicáveis e textos fixos traduzíveis.
- Validar a etapa atual ao avançar; ao finalizar, abrir a primeira etapa com campo inválido, focá-lo e usar `reportValidity()` para o destaque HTML5.
- Pular dinamicamente etapas sem campos ativos/visíveis e recalcular progresso sem perder a etapa atual.
- Suportar layouts `classic` e `split`; campos externos aos gerenciados pelo GVN ficam visíveis fora do wizard, ainda dentro de “Seus Dados”.

## Fora do escopo

- Transformar resumo, cupom, order bump, pagamento, termos ou botão `#place_order` em etapas.
- Criar endpoint, salvar progresso parcial, mudar validação server-side ou disparar `update_checkout` ao navegar.
- Instalar dependência, copiar o plugin de referência ou permitir navegação direta pelos indicadores.

## Contrato e dados

- Criar a opção `gvn_checkout_field_steps`: lista ordenada de `{id,title}`; cada item de `gvn_checkout_fields` recebe `step_id`.
- IDs são estáveis e sanitizados; títulos são texto simples; sempre existe ao menos uma etapa.
- Configuração antiga recebe fallback “Dados pessoais”; campos sem `step_id` entram nela. Persistir somente no próximo salvamento do gerenciador.
- Etapa só pode ser excluída vazia. Campos desativados não tornam uma etapa visível; condições existentes continuam controlando inputs e obrigatoriedade.
- Preservar formulário, nonce, hooks, IDs canônicos e submissão única do WooCommerce.

## Áreas afetadas

`includes/class-gvn-custom-fields.php`, `includes/class-gvn-admin.php`, `src/Lifecycle/Uninstaller.php`, `assets/js/gvn-admin-fields.js`, `assets/css/gvn-admin-fields.css`, `assets/js/gvn-checkout.js`, `assets/css/gvn-checkout.css`, os dois templates, testes e changelog.

## Tarefas

1. Adicionar leitura, fallback e sanitização de etapas/`step_id`; estender o AJAX atual mantendo nonce e capacidades. Aceite: payload inválido não cria IDs arbitrários, órfãos ou zero etapas.
2. Trocar a lista única do admin por contêineres de etapas conectados ao `jquery-ui-sortable` já carregado; incluir criar, renomear, reordenar e excluir vazia. Aceite: salvar/reabrir preserva etapas, campos e ordem.
3. Agrupar campos por etapa nos dois templates, preservando a subdivisão atual de endereço e todos os hooks/IDs. Aceite: uma etapa não exibe controles; 2+ exibem somente a etapa ativa.
4. Implementar navegação DOM no JS existente, integrada aos campos condicionais e a `updated_checkout`, sem requisição própria. Aceite: valores, máscaras, CEP e estado da etapa sobrevivem às mudanças normais do checkout.
5. Implementar validação com APIs HTML5 no avanço e antes da submissão. Aceite: primeiro inválido revela sua etapa, recebe foco/destaque e o pedido não duplica submissão.
6. Estilizar desktop/mobile com progresso compacto, botões claros, foco visível, área de toque mínima de 44px e sem rolagem horizontal. Aceite: uso confortável em 320px e navegação completa por teclado.
7. Atualizar documentação, versão/changelog e remoção da nova opção no uninstall.

## Testes e segurança

- Unitários: fallback legado, sanitização, associação órfã, ordem, etapa mínima e exclusão da opção.
- Templates: ambos os layouts, 1 versus múltiplas etapas, hooks uma vez, IDs/nonce/campos condicionais preservados.
- Integração/E2E em sandbox: avançar/voltar, campo obrigatório em etapa anterior/posterior, etapa condicional vazia, CEP, campo duplicado, Pix/cartão/boleto e submissão única.
- Mobile: 320px, 375px e 768px; teclado, foco, leitores de tela, sem overflow ou salto de layout relevante.
- Reutilizar nonce/capability atuais; sanitizar `id/title/step_id`, escapar atributos/textos e nunca alterar ou registrar PII, tokens ou payload de pagamento.
