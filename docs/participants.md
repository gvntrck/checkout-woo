# Participantes / Alunos

Em **WooCommerce → Configurações → GVN Checkout → Participantes / Alunos**:

1. Ative o recurso (desligado por padrão, inclusive após atualizar).
2. Busque e selecione os cursos. Selecionar um produto pai inclui suas variações.
3. Escolha se o comprador pode ocupar a primeira vaga de cada curso.
4. Para nome, e-mail e CPF, escolha obrigatório (padrão), opcional ou não solicitar.
5. Salve as alterações.

Cada unidade comprada gera um participante, por item do carrinho. Outros produtos e order bumps não geram participantes. Antes dos campos, a pergunta **Quem vai participar do curso?** mostra, por curso:

- Uma vaga: **Eu mesmo** ou **Outra pessoa**.
- Várias vagas: **Eu e outras pessoas** ou **Somente outras pessoas**.

A partir da versão 1.16.34, **Eu mesmo** (uma vaga) ou **Eu e outras pessoas** (várias vagas) vem selecionado inicialmente. A escolha explícita por outras pessoas é preservada nas atualizações AJAX. Se a configuração não permitir comprador como aluno, apenas a opção para outras pessoas fica disponível e selecionada. Escolhendo participar, a primeira vaga usa nome, e-mail e CPF do comprador, resolvidos e validados no servidor. Não há um segundo checkbox na parte inferior. Se CPF for obrigatório, mantenha o campo de cobrança `billing_cpf` habilitado ou preencha a vaga manualmente.

Os dados de cobrança continuam sendo os dados do pagador, sob o título **Dados do comprador / responsável pelo pagamento**. Nos carrinhos com cursos configurados, os labels exibidos passam a ser **Nome do comprador**, **Sobrenome do comprador**, **CPF do comprador/pagador** e **E-mail do comprador**. Os campos dos alunos são identificados como dados do participante. Não altera chaves de campos nem labels salvos no editor. Sem cursos configurados no carrinho ou com o recurso desligado, mantém os labels existentes.

Desde a versão 1.16.33, os três títulos **Quem vai participar do curso?**, **Dados do comprador / responsável pelo pagamento** e **Informações dos participantes** usam a configuração **Títulos de seção (px)** na aba Configurações, nos dois layouts GVN. Não usam **Título principal (px)**. Deixando o tamanho em branco, seguem o tamanho padrão de seção do preset de tipografia.

Funciona nos layouts clássico e split do GVN e no checkout clássico do WooCommerce. Não suporta Checkout Blocks. Desative o modo **produto único**, que limita o carrinho a uma unidade. O atalho de compra direta também inicia a compra com uma unidade; para várias vagas, use o fluxo de carrinho com quantidade. O produto não pode estar marcado como **Vendido individualmente** no WooCommerce. Há um link na seção de participantes para alterar a quantidade pelo carrinho.

Ao mudar quantidades no carrinho, o checkout reflete a quantidade atual. Atualizações AJAX de cupom/pagamento preservam os campos preenchidos em memória enquanto a página estiver aberta. Recarregar ou navegar ao carrinho pode perder dados ainda não enviados. A quantidade é alterada pelo carrinho, não por um novo controle no checkout.

E-mail e dígitos verificadores de CPF são validados no servidor quando preenchidos. Campos obrigatórios faltantes e requisições sem nonce válido bloqueiam a compra apenas para os produtos configurados. Valores excedentes enviados pelo navegador são ignorados: a quantidade do carrinho é a autoridade.

Os participantes são salvos no metadado privado `_gvn_participants` de cada item do pedido (HPOS via API WooCommerce). Consulte-os nos itens do pedido no painel administrativo, mesmo após desativar o recurso. Não são publicados nos e-mails ou na página de obrigado para evitar exposição de CPF. Nenhuma conta ou matrícula é criada automaticamente. Os dados acompanham a retenção do pedido; considere isso na política de privacidade da loja.

## Liberação segura

Não é necessário reconfigurar os sites existentes: sem ativação explícita, nenhum campo, asset ou validação de checkout deste módulo é aplicado. Antes de distribuir por atualização automática, homologue em staging com os temas, gateways e plugins de campos reais. Desative os snippets antigos de participantes no site de homologação para evitar coleta duplicada. Não migra metadados dos snippets antigos.

### Testes automatizados

- PHP: `php vendor/bin/phpunit --testsuite unit --filter ParticipantsTest`.
- Navegador: `node --test tests/checkout-participants.test.cjs tests/checkout-containers.test.cjs tests/checkout-field-groups.test.cjs`, com `GVN_PLAYWRIGHT_PATH` e, se necessário, `GVN_CHROMIUM_PATH` definidos conforme o `AGENTS.md`.
- `ParticipantsTest` cobre isolamento desligado (HTML idêntico nos dois layouts), produtos não selecionados, variações, exclusão de order bumps, quantidade autoritativa, comprador como aluno, dados inválidos, configuração opcional/oculta, nonces, permissões administrativas, persistência privada por item, valores AJAX e escape de HTML.
- O teste de navegador cobre ambos os layouts nas larguras 320, 390, 768 e 1280 px, uma e várias vagas, pergunta antes dos campos, labels do comprador, alternância do rádio e preservação de rascunhos após substituição de fragmentos AJAX.

Esses testes usam fixtures e não substituem uma compra de homologação com o gateway real.

### Resultado da comparação inicial (1.16.31) com a versão anterior

Verificado em 23/10/2026, PHP 8.3 e Chromium local, comparando com uma cópia de `HEAD` sem as alterações deste recurso:

- PHPUnit anterior: 178 testes, 813 asserções, 2 erros e 3 falhas.
- PHPUnit com participantes: 193 testes, 878 asserções, os mesmos 2 erros e 3 falhas. Os 15 testes novos passaram (65 asserções).
- Falhas preexistentes: dois testes de assets usam um mock sem `get_cart_contents_count()`; há uma expectativa antiga sobre o HTML de privacidade e duas expectativas antigas sobre sanitização das configurações.
- PHPStan global: os mesmos 3 erros da versão anterior. Análise específica de `Participants.php`: sem erros.
- A falha de `checkout-steps.test.cjs` no teste de erro inline também ocorre na versão anterior.
- Navegador: os 8 testes de participantes, contêineres e grupos passaram, sem skips, incluindo os dois layouts e as quatro larguras documentadas.
- Sintaxe PHP, sintaxe JavaScript, verificação de compatibilidade PHP 7.4 e sniffs de segurança do novo módulo passaram. PHPCS completo continua apontando divergências do padrão WPCS; o módulo segue o estilo local de `src/`.

Não foram identificadas novas falhas nessa comparação automatizada. Isso não certifica os temas e gateways reais: a publicação automática permanece condicionada à homologação em staging.

### Clareza do comprador (1.16.32)

- Rádio por curso antes dos dados de cobrança, substituindo o checkbox inferior, com opções adaptadas a uma ou várias vagas.
- Labels de comprador aplicados apenas à exibição, nos layouts GVN e nos campos nativos do checkout clássico; configurações salvas preservadas.
- 18 testes do módulo passaram (83 asserções). Suíte completa: 196 testes, 896 asserções, os mesmos 2 erros e 3 falhas preexistentes listados acima.
- Testes de navegador passaram nos dois layouts, com uma e várias vagas, quatro larguras, escolha por rádio e preservação de rascunhos após AJAX. Sintaxe, PHPStan do módulo, compatibilidade PHP 7.4 e sniffs de segurança passaram.
