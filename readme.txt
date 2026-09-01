=== GVN Checkout for WooCommerce ===
Contributors: gvntrack
Tags: woocommerce, checkout, custom checkout, order bump, pix
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
WC requires at least: 7.0
WC tested up to: 9.0
Stable tag: 1.13.40
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Checkout personalizado e otimizado para WooCommerce com layout moderno, order bump e configurações avançadas.

== Description ==

O **GVN Checkout** substitui o checkout padrão do WooCommerce por um layout moderno, limpo e otimizado para conversão.

**Recursos:**

* Layout responsivo e moderno com design premium
* Header configurável (textos e cores)
* Formulário simplificado (Nome, Sobrenome, CPF, Celular, E-mail)
* Resumo do pedido dinâmico
* Cupom de desconto com toggle
* Gateways de pagamento nativos do WooCommerce com visual customizado
* Order Bump configurável pelo admin
* Máscaras automáticas para CPF e Celular
* Cores personalizáveis (primária, botão, header, badge)
* Totalmente integrado com o fluxo de checkout do WooCommerce

**Como usar:**

1. Instale e ative o plugin
2. Crie uma página e adicione o shortcode `[gvn-checkout]`
3. Configure as opções em **WooCommerce > Configurações > GVN Checkout**
4. Opcional: configure o Order Bump com um produto

== Installation ==

1. Faça upload da pasta `checkout` para o diretório `/wp-content/plugins/`
2. Ative o plugin no menu "Plugins" do WordPress
3. Vá em **WooCommerce > Configurações > GVN Checkout** para personalizar

== Referência da superfície pública (baseline F0.3) ==

Este inventário registra o comportamento observado no código canônico antes da refatoração estrutural. Ele corresponde ao commit `688d85327dbacd92fce105c6eca6796a6ce5cd55` e à versão `1.13.5`; a versão `1.13.6` atualiza somente documentação e metadados, a versão `1.13.7` adiciona somente uma fixture sintética para migração, a versão `1.13.8` define a política de rollback, a versão `1.13.9` sincroniza os comentários de versão, a versão `1.13.10` remove os arquivos compactados do controle de versão, a versão `1.13.11` adiciona aviso administrativo de conflito, a versão `1.13.12` expande as fixtures de migração e clean install, a versão `1.13.13` adiciona seed reproduzível do catálogo, a versão `1.13.14` registra o threat model operacional, a versão `1.13.15` adiciona os ADRs, a versão `1.13.16` estabelece o protocolo de anonimização, a versão `1.13.17` formaliza a política de concorrência de deploy, a versão `1.13.18` define budgets de performance e matriz de compatibilidade, a versão `1.13.19` define governança de gates, a versão `1.13.20` implementa o ferramental de qualidade, a versão `1.13.21` implementa o bootstrap estrutural, a versão `1.13.22` introduz o schema e repositório de configurações, a versão `1.13.23` implementa a migração aditiva e idempotente de opções com lock não bloqueante (Fase 3 concluída), e a versão `1.13.24` implementa a validação server-side de condições e a persistência segura e HPOS-safe de campos de checkout via WC_Order (Fase 4 concluída). A presença de um item nesta lista não é, sozinha, evidência de compatibilidade ou suporte.

= Shortcode =

* `[gvn-checkout]`: renderiza o checkout personalizado para o carrinho atual.
* O shortcode não possui atributos documentados ou processados. `product_id` e `layout`, usados no projeto histórico `checkout-woo-2`, não pertencem ao contrato canônico.
* Carrinho vazio e checkout de convidado desativado produzem os estados correspondentes. Em `order-received`, o template de confirmação é renderizado.

= Opções do plugin =

* `gvn_checkout_header_text`: texto do cabeçalho; default `EFEAD - Conectando Saberes`.
* `gvn_checkout_header_badge_text`: badge do cabeçalho; default `COMPRA SEGURA`.
* `gvn_checkout_header_bg_color`: cor de fundo do cabeçalho; default `#3a4759`.
* `gvn_checkout_badge_bg_color`: cor do badge; default `#ff8a22`.
* `gvn_checkout_title_text`: título do formulário; default `Finalize sua inscrição`.
* `gvn_checkout_subtitle_text`: subtítulo do formulário; default `Acesso imediato após confirmação do pagamento`.
* `gvn_checkout_primary_color`: cor primária; default `#0066d4`.
* `gvn_checkout_button_color`: cor do botão; default `#ff8a22`.
* `gvn_checkout_button_text`: texto do botão; default `Finalizar pedido`.
* `gvn_checkout_order_bump_enabled`: `yes`/`no`; default `no`.
* `gvn_checkout_order_bump_product_id`: ID inteiro do produto do bump; default vazio/`0`.
* `gvn_checkout_order_bump_title`: título da oferta; default `Oferta Exclusiva`.
* `gvn_checkout_order_bump_description`: descrição da oferta.
* `gvn_checkout_order_bump_cta_text`: texto da chamada para ação; default `Sim! Quero adicionar ao meu pedido`.
* `gvn_checkout_order_bump_price`: preço decimal textual ou vazio; vazio/valor não positivo usa o preço padrão do produto.
* `gvn_checkout_fields`: array de campos com `key`, `label`, `type`, `required`, `width`, `position`, `placeholder`, `enabled`, `mask`, `is_default`, `is_woo_default`, `options`, `default_option` e `conditions`.
* `gvn_checkout_default_fields_config`: opção legada de campos brasileiros, usada somente pela migração administrativa e removida depois do processamento.

A opção `woocommerce_enable_guest_checkout` é do WooCommerce e é lida para decidir se convidados podem finalizar a compra. Ela não pertence ao namespace GVN.

= Hooks e filtros =

* WordPress: `plugins_loaded`, `before_woocommerce_init`, `admin_notices`, `wp_enqueue_scripts`, `admin_enqueue_scripts` e `admin_init`.
* Administração WooCommerce: `woocommerce_settings_tabs_array`, `woocommerce_settings_tabs_gvn_checkout`, `woocommerce_update_options_gvn_checkout` e `plugin_action_links_{GVN_CHECKOUT_PLUGIN_BASENAME}`.
* Checkout/pedido: `woocommerce_checkout_fields`, `woocommerce_before_checkout_billing_form`, `woocommerce_after_checkout_billing_form`, `woocommerce_checkout_update_order_meta`, `woocommerce_after_checkout_validation`, `woocommerce_checkout_create_order`, `woocommerce_before_calculate_totals`, `woocommerce_admin_order_data_after_billing_address` e `woocommerce_admin_order_data_after_shipping_address`.
* Confirmação: `wc_get_template`, `woocommerce_thankyou_{payment_method}` e `woocommerce_thankyou`.
* Ativação e desativação usam `register_activation_hook()` e `register_deactivation_hook()`.

O plugin não define hooks próprios de negócio por `do_action( 'gvn_*' )` ou filtros equivalentes. Os métodos das classes `GVN_*` e as funções `gvn_checkout_*` são detalhes internos.

= Ações AJAX =

As ações usam `admin-ajax.php`, POST e nonce. `gvn_apply_coupon`, `gvn_remove_coupon`, `gvn_toggle_order_bump` e `gvn_cep_lookup` possuem variantes para convidados (`wp_ajax_nopriv_`). `gvn_save_fields` exige usuário com `manage_woocommerce` e não possui variante para convidados.

* `gvn_apply_coupon`: recebe `coupon_code` e altera o carrinho pela API do WooCommerce.
* `gvn_remove_coupon`: recebe `coupon_code`, remove o cupom e recalcula o carrinho.
* `gvn_toggle_order_bump`: recebe `bump_action` (`add`/`remove`) e altera o item configurado.
* `gvn_cep_lookup`: recebe `cep` com oito dígitos, consulta ViaCEP no servidor e usa transient de 7 dias.
* `gvn_save_fields`: recebe `fields` em JSON, sanitiza o schema e grava a opção de campos.

A finalização do pedido usa a action e o nonce nativos do WooCommerce; o plugin não cria endpoint próprio de criação de pedido ou pagamento.

= Dados JavaScript localizados =

* `gvn_checkout_params`: `ajax_url`, `nonce` (`gvn_checkout_nonce`) e `wc_ajax_url`, localizado no script do checkout.
* `gvn_admin_params`: `ajax_url` e `nonce` (`gvn_admin_fields_nonce`), localizado apenas na aba administrativa `gvn_checkout`.
* Alterações de cupom ou order bump disparam o evento nativo `update_checkout` do WooCommerce; não há eventos JavaScript próprios documentados.

= Metadados, carrinho e cache =

* Campos nativos do pedido ficam sob responsabilidade do WooCommerce. Campos customizados habilitados são gravados como `_{field_key}`, por exemplo `_billing_cpf`, com `WC_Order::update_meta_data()`.
* O item de order bump recebe o marcador de carrinho `gvn_order_bump`; o plugin não grava uma meta de pedido própria para esse marcador.
* Resultados de CEP usam a chave de transient `gvn_cep_{cep}` e expiração de 604800 segundos.
* `uninstall.php` remove as opções GVN conhecidas, a opção legada de campos e os transients de CEP.
* Não foi encontrada opção de versão de schema, estado de migração, marcador de origem do pedido, user meta ou cookie próprio no baseline.

== Changelog ==

= 1.13.40 =
* Inclui a cidade (`billing_city`) como campo padrão, habilitado, obrigatório e gerenciável no editor de campos, inclusive para configurações existentes.

= 1.13.39 =
* Inclui o CEP (`billing_postcode`) como campo brasileiro padrão, habilitado e obrigatório para preservar os dados exigidos por gateways de boleto e cartão.

= 1.13.38 =
* Remove o workflow automático CI Quality Gates do GitHub Actions.

= 1.13.37 =
* Move as instruções de pagamento para antes dos itens do pedido na página de confirmação e amplia o campo do código Pix Copia e Cola.

= 1.13.36 =
* Preserva scripts emitidos pelos hooks oficiais da página de pedido recebido, restaurando o código Pix Copia e Cola e o acompanhamento de status de gateways como o PagBank Connect.

= 1.13.35 =
* Corrige a largura dos cards no checkout clássico ao neutralizar, dentro do layout GVN, as larguras e floats aplicados pelo CSS de compatibilidade do WooCommerce a `#customer_details` e ao wrapper `.col2-set` do resumo do pedido.

= 1.13.34 =
* Desbloqueio e expansão universal de contêineres de temas (class-gvn-checkout.php e gvn-checkout.css): adição da classe gvn-checkout-active no body e regras de breakout automático para entry-content, site-main, wp-site-blocks e contêineres FSE, permitindo que o checkout se expanda para 1240px e aproveite a largura total sem ser estrangulado por restrições de layout do tema.

= 1.13.33 =
* Refinamento estrutural de larguras e alinhamento do grid (gvn-checkout.css): fixação de 100% de largura nos containers de cards, dynamic fields e totals, com coluna lateral em 380px encostando na margem direita e alinhamento perfeito ao cabeçalho.

= 1.13.32 =
* Ajustes visuais e refinamento estético do checkout (checkout-template.php e gvn-checkout.css): reestruturação do grid em 2 colunas equilibradas com sticky sidebar no desktop, eliminação do ponto vermelho isolado quando o título não está preenchido, supressão de aviso/formulário duplicado de cupom no topo, padronização da tipografia de inputs e observações, e polimento do resumo do pedido, badges de segurança e botão de finalizar pedido.

= 1.13.31 =
* Endurece o gerenciamento de assets e internacionalização (class-gvn-checkout.php e templates): sanitização rigorosa de variáveis de cores injetadas em CSS inline via sanitize_hex_color, integração do template com SettingsRepository e padronização do text domain gvn-checkout (Fase 11 concluída).

= 1.13.30 =
* Endurece o ciclo de vida e desinstalação segura do plugin (src/Lifecycle/Uninstaller.php e uninstall.php): remoção de SQL direto para limpeza de transients, suporte a wp_cache_flush, limpeza modular de opções canônicas e legadas sem tocar em pedidos ou dados de clientes (Fase 12 concluída).

= 1.13.29 =
* Endurece a segurança do painel administrativo (class-gvn-admin.php e class-gvn-custom-fields.php): verificação estrita de capabilities (manage_woocommerce/manage_options), nonces de proteção em formulários e requisições AJAX, sanitização centralizada, invalidação de cache do SettingsRepository e internacionalização completa de mensagens (Fase 10 concluída).

= 1.13.28 =
* Endurece a validação de CEP e endereço (class-gvn-address-validation.php): validação estrita de formato numérico de 8 dígitos, utilização de wp_safe_remote_get com timeout seguro, validação de integridade ao ler o cache transitório, mensagens claras e garantia de fallback manual caso a API externa esteja indisponível (Fase 8 concluída).

= 1.13.27 =
* Endurece a gestão de Order Bump e carrinho (class-gvn-order-bump.php e gvn-checkout.js): validação estrita server-side de produto, disponibilidade e preço via SettingsRepository e APIs do WooCommerce, mutações idempotentes de adição e remoção no carrinho, proteção no frontend contra concorrência e respostas assíncronas fora de ordem via sequence counter (Fase 7 concluída).

= 1.13.26 =
* Isola a página de confirmação de pedido (Thank You / thankyou-template.php) exclusivamente para pedidos originados pelo GVN Checkout através dos marcadores _gvn_checkout_version e _gvn_checkout, remove o override global indiscriminado em pedidos de outros checkouts, adiciona o hook canônico woocommerce_before_thankyou e implementa validação timing-safe da order_key via hash_equals (Fase 9 concluída).

= 1.13.25 =
* Restaura a ordem canônica de hooks do checkout clássico do WooCommerce (woocommerce_before_checkout_form, woocommerce_checkout_before_customer_details, woocommerce_checkout_after_customer_details, woocommerce_checkout_before_order_review, woocommerce_checkout_after_order_review, woocommerce_review_order_before_payment, woocommerce_checkout_terms_and_conditions, woocommerce_review_order_before_submit, woocommerce_order_button_html, woocommerce_review_order_after_submit, woocommerce_review_order_after_payment, woocommerce_after_checkout_form) e adiciona suporte nativo a termos de serviço e política de privacidade sem quebrar o layout visual do GVN Checkout (Fase 5 concluída).

= 1.13.24 =
* Implementa avaliação server-side de condições (FieldConditionEvaluator.php), validação de obrigatoriedade condicional (FieldValidator.php), allowlist e bloqueio de metadados reservados (FieldSecurityPolicy.php), sanitização tipada (FieldSanitizer.php) e persistência HPOS-safe em WC_Order (FieldOrderPersister.php) (Fase 4 concluída).

= 1.13.23 =
* Implementa o migrador seguro e aditivo de configurações (SettingsMigrator.php) com lock não bloqueante, read-back de validação e preservação de opções legadas para rollback seguro (Fase 3 concluída).

= 1.13.22 =
* Implementa o Schema declarativo (SettingsSchema.php) e o Repositório de Configurações (SettingsRepository.php) com leitura centralizada, fallback seguro e cache por requisição (Fase 3 mínima).

= 1.13.21 =
* Implementa Composition Root (Plugin.php), verificação estruturada de requisitos (Requirements.php), feature flags seguras (Features.php), observabilidade com redaction de PII (Logger.php) e carregamento de assets contextual (Fase 2).

= 1.13.20 =
* Implementa o ferramental completo de qualidade: composer.json com PSR-4 e scripts, phpcs.xml.dist, phpstan.neon.dist, phpunit.xml.dist com suites unit/integration/security-smoke, checagem de sintaxe, empacotamento reprodutível e GitHub Actions CI.

= 1.13.19 =
* Define os papéis de aprovação para os gates P0/P1 e a estrutura de arquivamento de evidências (docs/governance-and-gates.md).

= 1.13.18 =
* Define budgets de performance versionados (docs/performance-budgets.json) e matriz formal de compatibilidade (docs/compatibility-matrix.md).

= 1.13.17 =
* Documenta a política de concorrência durante deploy com formulários abertos e especifica o caso de teste E2E (docs/deploy-checkout-concurrency-policy.md).

= 1.13.16 =
* Documenta o protocolo operacional e regras para anonimização e sanitização de fixtures e dados de teste (docs/fixture-anonymization-process.md).

= 1.13.15 =
* Adiciona Architecture Decision Records (ADRs 0001 a 0006) em docs/adr/ para decisões arquiteturais estruturais.

= 1.13.14 =
* Registra o Threat Model operacional como documento versionado e checklist vivo de segurança (docs/threat-model.md).

= 1.13.13 =
* Adiciona fixture versionada de seed reproduzível do catálogo WooCommerce (produtos simples, virtuais, variáveis, esgotados, bump, cupons e clientes).

= 1.13.12 =
* Adiciona fixtures sintéticas de clean install, campos condicionais PF/PJ, order bump ativo e opções incompletas/corrompidas para testes de robustez e migração.

= 1.13.11 =
* Adiciona aviso administrativo de detecção de conflito quando o plugin histórico checkout-woo-2 estiver ativo simultaneamente.

= 1.13.10 =
* Remove pacotes compactados ZIP do controle de versão e adiciona configuração do .gitignore.

= 1.13.9 =
* Sincroniza versões @version em todos os templates, classes, estilos CSS e scripts JavaScript para 1.13.9.

= 1.13.8 =
* Adiciona documentação formal da política de rollback e procedimentos operacionais de reversão.

= 1.13.7 =
* Adiciona fixture sintética das opções legadas para testes de migração, sem dados de produção.

= 1.13.6 =
* Documenta os shortcodes, opções, hooks, ações AJAX e metadados existentes no plugin canônico.

= 1.13.5 =
* Remove configurações locais de análise que não fazem parte do projeto.

= 1.13.4 =
* Documenta `checkout-woo` como a base canônica do projeto.

= 1.13.0 =
* Novo: Proxy server-side para consulta de CEP (ViaCEP) com cache via transients (7 dias)
* Novo: Validação server-side de formato de CEP, UF válida e consistência CEP↔UF no checkout
* Novo: Normalização automática de UF, cidade e CEP ao salvar pedidos
* Novo: Campos de endereço (UF, cidade, bairro) são travados após auto-fill via CEP, com botão para edição manual
* Novo: Foco automático no campo "número" após preenchimento de endereço via CEP
* Novo: Alerta visual de inconsistência CEP↔UF no frontend
* Novo: Retry automático em caso de falha de rede na consulta de CEP
* Novo: Classe GVN_Address_Validation com lista completa de UFs brasileiras e mapeamento CEP→UF
* Melhoria: Consulta de CEP agora passa pelo servidor (segurança e cache)

= 1.0.0 =
* Lançamento inicial
* Checkout personalizado com shortcode [gvn-checkout]
* Header configurável
* Order Bump com produto configurável
* Integração com gateways nativos do WooCommerce
* Máscaras para CPF e Celular
* Cupom de desconto funcional
