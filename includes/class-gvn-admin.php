<?php
/**
 * Classe de administração do GVN Checkout.
 * Gerencia a página de configurações no painel do WordPress.
 *
 * @package GVN_Checkout
 * @version 1.13.9
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GVN_Admin {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );
        add_action( 'woocommerce_settings_tabs_gvn_checkout', array( $this, 'render_settings_page' ) );
        add_action( 'woocommerce_update_options_gvn_checkout', array( $this, 'save_settings' ) );
        add_filter( 'plugin_action_links_' . GVN_CHECKOUT_PLUGIN_BASENAME, array( $this, 'add_plugin_links' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    /**
     * Adiciona a aba "GVN Checkout" nas configurações do WooCommerce.
     */
    public function add_settings_tab( $tabs ) {
        $tabs['gvn_checkout'] = 'GVN Checkout';
        return $tabs;
    }

    /**
     * Retorna a sub-aba ativa.
     */
    private function get_current_subtab() {
        $valid = array( 'settings', 'fields', 'help' );
        $subtab = isset( $_GET['subtab'] ) ? sanitize_key( wp_unslash( $_GET['subtab'] ) ) : 'settings';
        return in_array( $subtab, $valid, true ) ? $subtab : 'settings';
    }

    /**
     * Renderiza a página de configurações com sub-abas.
     */
    public function render_settings_page() {
        $current_subtab = $this->get_current_subtab();
        $base_url = admin_url( 'admin.php?page=wc-settings&tab=gvn_checkout' );

        $subtabs = array(
            'settings' => 'Configurações',
            'fields'   => 'Campos do Formulário',
            'help'     => 'Como Usar',
        );
        ?>
        <nav class="gvn-subtabs nav-tab-wrapper">
            <?php foreach ( $subtabs as $slug => $label ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'subtab', $slug, $base_url ) ); ?>"
                   class="nav-tab <?php echo $current_subtab === $slug ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html( $label ); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php

        switch ( $current_subtab ) {
            case 'fields':
                $this->render_fields_manager();
                break;
            case 'help':
                $this->render_help_page();
                break;
            default:
                woocommerce_admin_fields( $this->get_settings() );
                break;
        }
    }

    /**
     * Salva as configurações.
     */
    public function save_settings() {
        $subtab = $this->get_current_subtab();
        if ( 'settings' === $subtab ) {
            woocommerce_update_options( $this->get_settings() );
        }
    }

    /**
     * Define os campos de configuração (sub-aba Configurações).
     */
    private function get_settings() {
        $products = $this->get_products_list();

        $settings = array(

            // Seção: Header
            array(
                'title' => 'Configurações do Header',
                'type'  => 'title',
                'desc'  => 'Personalize o cabeçalho exibido no checkout.',
                'id'    => 'gvn_checkout_header_section',
            ),
            array(
                'title'    => 'Texto do Header',
                'desc'     => 'Texto principal exibido no cabeçalho.',
                'id'       => 'gvn_checkout_header_text',
                'type'     => 'text',
                'default'  => 'EFEAD - Conectando Saberes',
                'desc_tip' => true,
            ),
            array(
                'title'    => 'Texto do Badge',
                'desc'     => 'Texto exibido no badge do cabeçalho.',
                'id'       => 'gvn_checkout_header_badge_text',
                'type'     => 'text',
                'default'  => 'COMPRA SEGURA',
                'desc_tip' => true,
            ),
            array(
                'title'   => 'Cor de Fundo do Header',
                'id'      => 'gvn_checkout_header_bg_color',
                'type'    => 'color',
                'default' => '#3a4759',
            ),
            array(
                'title'   => 'Cor do Badge',
                'id'      => 'gvn_checkout_badge_bg_color',
                'type'    => 'color',
                'default' => '#ff8a22',
            ),
            array(
                'type' => 'sectionend',
                'id'   => 'gvn_checkout_header_section',
            ),

            // Seção: Textos do Checkout
            array(
                'title' => 'Textos do Checkout',
                'type'  => 'title',
                'desc'  => 'Personalize o título e o subtítulo exibidos acima do formulário de inscrição.',
                'id'    => 'gvn_checkout_text_section',
            ),
            array(
                'title'    => 'Título principal',
                'desc'     => 'Texto principal exibido acima do formulário de inscrição.',
                'id'       => 'gvn_checkout_title_text',
                'type'     => 'text',
                'default'  => 'Finalize sua inscrição',
                'desc_tip' => true,
            ),
            array(
                'title'    => 'Subtítulo',
                'desc'     => 'Texto complementar exibido abaixo do título principal.',
                'id'       => 'gvn_checkout_subtitle_text',
                'type'     => 'text',
                'default'  => 'Acesso imediato após confirmação do pagamento',
                'desc_tip' => true,
            ),
            array(
                'type' => 'sectionend',
                'id'   => 'gvn_checkout_text_section',
            ),

            // Seção: Visual
            array(
                'title' => 'Configurações Visuais',
                'type'  => 'title',
                'desc'  => 'Cores e aparência geral do checkout.',
                'id'    => 'gvn_checkout_visual_section',
            ),
            array(
                'title'   => 'Cor Primária',
                'desc'    => 'Cor dos cabeçalhos de seção e destaques.',
                'id'      => 'gvn_checkout_primary_color',
                'type'    => 'color',
                'default' => '#0066d4',
                'desc_tip' => true,
            ),
            array(
                'title'   => 'Cor do Botão Finalizar',
                'id'      => 'gvn_checkout_button_color',
                'type'    => 'color',
                'default' => '#ff8a22',
            ),
            array(
                'title'    => 'Texto do Botão',
                'id'       => 'gvn_checkout_button_text',
                'type'     => 'text',
                'default'  => 'Finalizar pedido',
                'desc_tip' => true,
            ),
            array(
                'type' => 'sectionend',
                'id'   => 'gvn_checkout_visual_section',
            ),

            // Seção: Order Bump
            array(
                'title' => 'Order Bump (Oferta Exclusiva)',
                'type'  => 'title',
                'desc'  => 'Configure uma oferta adicional exibida antes do botão de finalizar.',
                'id'    => 'gvn_checkout_order_bump_section',
            ),
            array(
                'title'   => 'Ativar Order Bump',
                'id'      => 'gvn_checkout_order_bump_enabled',
                'type'    => 'checkbox',
                'default' => 'no',
                'desc'    => 'Exibir oferta exclusiva no checkout.',
            ),
            array(
                'title'   => 'Produto',
                'desc'    => 'Selecione o produto da oferta.',
                'id'      => 'gvn_checkout_order_bump_product_id',
                'type'    => 'select',
                'options' => $products,
                'default' => '',
                'desc_tip' => true,
            ),
            array(
                'title'    => 'Título da Oferta',
                'id'       => 'gvn_checkout_order_bump_title',
                'type'     => 'text',
                'default'  => 'Oferta Exclusiva',
                'desc_tip' => true,
            ),
            array(
                'title'    => 'Descrição',
                'id'       => 'gvn_checkout_order_bump_description',
                'type'     => 'textarea',
                'default'  => 'Adicione este item ao seu pedido com condições especiais.',
                'desc_tip' => true,
            ),
            array(
                'title'    => 'Texto do CTA',
                'id'       => 'gvn_checkout_order_bump_cta_text',
                'type'     => 'text',
                'default'  => 'Sim! Quero adicionar ao meu pedido',
                'desc_tip' => true,
            ),
            array(
                'title'    => 'Preço Promocional (R$)',
                'desc'     => 'Deixe vazio para usar o preço padrão do produto.',
                'id'       => 'gvn_checkout_order_bump_price',
                'type'     => 'text',
                'default'  => '',
                'desc_tip' => true,
            ),
            array(
                'type' => 'sectionend',
                'id'   => 'gvn_checkout_order_bump_section',
            ),
        );

        return $settings;
    }

    /**
     * Lista de produtos para o select do Order Bump.
     */
    private function get_products_list() {
        $products = array( '' => '-- Selecione um produto --' );

        $args = array(
            'status'  => 'publish',
            'limit'   => 100,
            'orderby' => 'title',
            'order'   => 'ASC',
        );

        $wc_products = wc_get_products( $args );

        if ( $wc_products ) {
            foreach ( $wc_products as $product ) {
                // Usa strip_tags para remover HTML de wc_price dentro de <option>.
                $price_html = wc_price( $product->get_price() );
                $price_text = wp_strip_all_tags( $price_html );
                $products[ $product->get_id() ] = $product->get_name() . ' (' . $price_text . ')';
            }
        }

        return $products;
    }

    /**
     * Link de configurações na lista de plugins.
     */
    public function add_plugin_links( $links ) {
        $settings_link = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=gvn_checkout' ) . '">Configurações</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Enqueue de assets do admin (apenas na aba GVN Checkout).
     */
    public function enqueue_admin_assets( $hook ) {
        if ( 'woocommerce_page_wc-settings' !== $hook ) {
            return;
        }

        if ( ! isset( $_GET['tab'] ) || 'gvn_checkout' !== sanitize_key( wp_unslash( $_GET['tab'] ) ) ) {
            return;
        }

        wp_enqueue_style(
            'gvn-admin-fields-css',
            GVN_CHECKOUT_PLUGIN_URL . 'assets/css/gvn-admin-fields.css',
            array(),
            GVN_CHECKOUT_VERSION
        );

        wp_enqueue_script( 'jquery-ui-sortable' );

        wp_enqueue_script(
            'gvn-admin-fields-js',
            GVN_CHECKOUT_PLUGIN_URL . 'assets/js/gvn-admin-fields.js',
            array( 'jquery', 'jquery-ui-sortable' ),
            GVN_CHECKOUT_VERSION,
            true
        );

        wp_localize_script( 'gvn-admin-fields-js', 'gvn_admin_params', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'gvn_admin_fields_nonce' ),
        ) );
    }

    /**
     * Renderiza o gerenciador de campos personalizados.
     */
    private function render_fields_manager() {
        $fields = GVN_Custom_Fields::get_fields();
        ?>
        <div id="gvn-fields-manager">
            <h2>Campos do Formulário</h2>
            <p class="description">Adicione, remova, reordene e configure a largura dos campos do checkout. Arraste para reordenar.</p>

            <div class="gvn-fields-toolbar">
                <button type="button" class="button button-secondary" id="gvn-add-field">+ Adicionar Campo</button>
                <button type="button" class="button button-secondary" id="gvn-import-woo-fields" title="Adiciona campos nativos de endereço do WooCommerce à lista">📥 Importar Campos Padrões do WooCommerce</button>
                <button type="button" class="button button-secondary" id="gvn-import-br-fields" title="Adiciona campos brasileiros (CPF, CNPJ, RG, etc.) à lista">🇧🇷 Importar Campos Brasileiros</button>
                <span id="gvn-fields-status" style="display:none;"></span>
            </div>

            <div id="gvn-fields-list">
                <?php foreach ( $fields as $field ) : ?>
                    <div class="gvn-field-row<?php echo empty( $field['enabled'] ) ? ' gvn-field-row--disabled' : ''; ?>" data-key="<?php echo esc_attr( $field['key'] ); ?>" data-default="<?php echo $field['is_default'] ? 'true' : 'false'; ?>" data-woo-default="<?php echo ! empty( $field['is_woo_default'] ) ? 'true' : 'false'; ?>">
                        <div class="gvn-field-row__header">
                            <span class="gvn-field-drag" title="Arrastar para reordenar">☰</span>
                            <span class="gvn-field-pos-label">#<?php echo esc_html( $field['position'] ); ?></span>
                            <span class="gvn-field-label-display"><?php echo esc_html( $field['label'] ?: '(sem label)' ); ?></span>
                            <?php if ( ! empty( $field['is_woo_default'] ) ) : ?>
                                <span class="gvn-field-badge gvn-field-badge--woo">Padrão Woo</span>
                            <?php endif; ?>
                            <span class="gvn-field-width-badge"><?php echo esc_html( $field['width'] ); ?>%</span>
                            <span class="gvn-field-row__actions">
                                <label class="gvn-field-enabled-label">
                                    <input type="checkbox" class="gvn-field-enabled" <?php checked( ! empty( $field['enabled'] ) ); ?> /> Ativo
                                </label>
                                <button type="button" class="gvn-field-toggle button-link"><span class="gvn-toggle-icon">▼</span></button>
                                <button type="button" class="gvn-field-remove button-link" title="Remover">✕</button>
                            </span>
                        </div>
                        <div class="gvn-field-row__body" style="display:none;">
                            <input type="hidden" class="gvn-field-position" value="<?php echo esc_attr( $field['position'] ); ?>" />
                            <input type="hidden" class="gvn-field-is-default" value="<?php echo $field['is_default'] ? 'true' : 'false'; ?>" />
                            <div class="gvn-field-grid">
                                <div class="gvn-field-col">
                                    <label>Chave (key)</label>
                                    <input type="text" class="gvn-field-key-input" value="<?php echo esc_attr( $field['key'] ); ?>" <?php echo $field['is_default'] ? 'readonly' : ''; ?> />
                                </div>
                                <div class="gvn-field-col">
                                    <label>Label</label>
                                    <input type="text" class="gvn-field-label-input" value="<?php echo esc_attr( $field['label'] ); ?>" />
                                </div>
                                <div class="gvn-field-col">
                                    <label>Tipo</label>
                                    <select class="gvn-field-type-select">
                                        <?php foreach ( GVN_Custom_Fields::get_field_types() as $type_key => $type_label ) : ?>
                                            <option value="<?php echo esc_attr( $type_key ); ?>" <?php selected( $field['type'], $type_key ); ?>><?php echo esc_html( $type_label ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="gvn-field-col">
                                    <label>Largura</label>
                                    <select class="gvn-field-width-select">
                                        <?php foreach ( array( '25' => '25%', '33' => '33%', '50' => '50%', '75' => '75%', '100' => '100%' ) as $w_key => $w_label ) : ?>
                                            <option value="<?php echo esc_attr( $w_key ); ?>" <?php selected( $field['width'], $w_key ); ?>><?php echo esc_html( $w_label ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="gvn-field-col">
                                    <label>Máscara</label>
                                    <select class="gvn-field-mask-select">
                                        <?php foreach ( GVN_Custom_Fields::get_available_masks() as $m_key => $m_label ) : ?>
                                            <option value="<?php echo esc_attr( $m_key ); ?>" <?php selected( $field['mask'], $m_key ); ?>><?php echo esc_html( $m_label ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="gvn-field-col">
                                    <label>Placeholder</label>
                                    <input type="text" class="gvn-field-placeholder-input" value="<?php echo esc_attr( $field['placeholder'] ); ?>" />
                                </div>
                                <div class="gvn-field-col gvn-field-col--options" style="<?php echo ( 'select' !== $field['type'] ) ? 'display:none;' : ''; ?>grid-column: 1 / -1;">
                                    <label>Opções (uma por linha, formato: <code>valor|Rótulo</code> ou apenas <code>Rótulo</code>)</label>
                                    <textarea class="gvn-field-options-input" rows="4" placeholder="opcao1|Opção 1&#10;opcao2|Opção 2&#10;opcao3|Opção 3"><?php echo esc_textarea( isset( $field['options'] ) ? $field['options'] : '' ); ?></textarea>
                                    <label style="margin-top:8px;display:block;">Valor padrão pré-selecionado</label>
                                    <select class="gvn-field-default-option-select">
                                        <option value="">-- Nenhuma opção pré-selecionada --</option>
                                        <?php
                                        $saved_default = isset( $field['default_option'] ) ? $field['default_option'] : '';
                                        $parsed_opts   = GVN_Custom_Fields::parse_select_options( isset( $field['options'] ) ? $field['options'] : '' );
                                        foreach ( $parsed_opts as $opt_val => $opt_label ) :
                                        ?>
                                            <option value="<?php echo esc_attr( $opt_val ); ?>" <?php selected( $saved_default, $opt_val ); ?>><?php echo esc_html( $opt_label ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="gvn-field-col">
                                    <label><input type="checkbox" class="gvn-field-required" <?php checked( ! empty( $field['required'] ) ); ?> /> Obrigatório</label>
                                </div>
                            </div>

                            <?php
                            $conditions = isset( $field['conditions'] ) ? $field['conditions'] : array( 'logic' => 'and', 'rules' => array() );
                            $rules      = isset( $conditions['rules'] ) ? $conditions['rules'] : array();
                            $logic      = isset( $conditions['logic'] ) ? $conditions['logic'] : 'and';
                            $all_fields = GVN_Custom_Fields::get_fields();
                            ?>
                            <div class="gvn-conditions-section">
                                <div class="gvn-conditions-header">
                                    <strong>Condições de exibição</strong>
                                    <span class="gvn-conditions-hint">Deixe vazio para sempre exibir</span>
                                </div>
                                <div class="gvn-conditions-logic" <?php echo empty( $rules ) ? 'style="display:none;"' : ''; ?>>
                                    <label>Quando</label>
                                    <select class="gvn-conditions-logic-select">
                                        <option value="and" <?php selected( $logic, 'and' ); ?>>TODAS as condições forem verdadeiras (E)</option>
                                        <option value="or" <?php selected( $logic, 'or' ); ?>>QUALQUER condição for verdadeira (OU)</option>
                                    </select>
                                </div>
                                <div class="gvn-conditions-rules">
                                    <?php foreach ( $rules as $rule ) :
                                        $rule_field    = isset( $rule['field'] ) ? $rule['field'] : '';
                                        $rule_operator = isset( $rule['operator'] ) ? $rule['operator'] : '';
                                        $rule_value    = isset( $rule['value'] ) ? $rule['value'] : '';
                                    ?>
                                        <div class="gvn-condition-rule">
                                            <select class="gvn-rule-field">
                                                <option value="">-- Campo --</option>
                                                <?php foreach ( $all_fields as $af ) : ?>
                                                    <?php if ( $af['key'] !== $field['key'] ) : ?>
                                                        <option value="<?php echo esc_attr( $af['key'] ); ?>" <?php selected( $rule_field, $af['key'] ); ?>><?php echo esc_html( $af['label'] ?: $af['key'] ); ?></option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </select>
                                            <select class="gvn-rule-operator">
                                                <option value="equals" <?php selected( $rule_operator, 'equals' ); ?>>Igual a</option>
                                                <option value="not_equals" <?php selected( $rule_operator, 'not_equals' ); ?>>Diferente de</option>
                                                <option value="filled" <?php selected( $rule_operator, 'filled' ); ?>>Preenchido</option>
                                                <option value="empty" <?php selected( $rule_operator, 'empty' ); ?>>Vazio</option>
                                                <option value="contains" <?php selected( $rule_operator, 'contains' ); ?>>Contém</option>
                                                <option value="greater" <?php selected( $rule_operator, 'greater' ); ?>>Maior que</option>
                                                <option value="less" <?php selected( $rule_operator, 'less' ); ?>>Menor que</option>
                                            </select>
                                            <input type="text" class="gvn-rule-value" value="<?php echo esc_attr( $rule_value ); ?>" placeholder="Valor" <?php echo in_array( $rule_operator, array( 'filled', 'empty' ), true ) ? 'style="display:none;"' : ''; ?> />
                                            <button type="button" class="gvn-rule-remove button-link" title="Remover condição">✕</button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="gvn-add-condition button-link">+ Adicionar condição</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Renderiza a página de ajuda "Como Usar".
     */
    private function render_help_page() {
        $checkout_page_id = wc_get_page_id( 'checkout' );
        $checkout_url     = $checkout_page_id ? get_permalink( $checkout_page_id ) : '';
        $settings_url     = admin_url( 'admin.php?page=wc-settings&tab=gvn_checkout' );
        $fields_url       = admin_url( 'admin.php?page=wc-settings&tab=gvn_checkout&subtab=fields' );
        $pages_url        = admin_url( 'edit.php?post_type=page' );
        $wc_advanced_url  = admin_url( 'admin.php?page=wc-settings&tab=advanced' );
        ?>
        <div class="gvn-help-wrap">

            <div class="gvn-help-hero">
                <h2>Como usar o GVN Checkout</h2>
                <p>Guia rápido para configurar o seu checkout personalizado em poucos minutos.</p>
            </div>

            <div class="gvn-help-grid">

                <div class="gvn-help-card">
                    <div class="gvn-help-card__icon">1</div>
                    <div class="gvn-help-card__body">
                        <h3>Criar a página de checkout</h3>
                        <p>O plugin funciona através de um shortcode nativo. Crie uma página e insira o shortcode abaixo no conteúdo:</p>
                        <code class="gvn-help-shortcode">[gvn-checkout]</code>
                        <p>Depois publique a página.</p>
                        <a href="<?php echo esc_url( $pages_url ); ?>" class="button button-secondary">Ir para Páginas</a>
                    </div>
                </div>

                <div class="gvn-help-card">
                    <div class="gvn-help-card__icon">2</div>
                    <div class="gvn-help-card__body">
                        <h3>Definir a página oficial de checkout</h3>
                        <p>Para que os clientes sejam redirecionados corretamente, configure a página criada como a página oficial de checkout do WooCommerce.</p>
                        <p class="gvn-help-tip"><strong>Dica:</strong> WooCommerce &rsaquo; Configurações &rsaquo; Avançado &rsaquo; Página de checkout.</p>
                        <a href="<?php echo esc_url( $wc_advanced_url ); ?>" class="button button-secondary">Abrir Configurações Avançadas</a>
                    </div>
                </div>

                <div class="gvn-help-card">
                    <div class="gvn-help-card__icon">3</div>
                    <div class="gvn-help-card__body">
                        <h3>Personalizar a identidade visual</h3>
                        <p>Na aba <strong>Configurações</strong> você define cores (primária, botões, cabeçalho e badges), textos do header e do botão de finalização, alinhando tudo à identidade da sua marca.</p>
                        <a href="<?php echo esc_url( $settings_url ); ?>" class="button button-secondary">Abrir Configurações</a>
                    </div>
                </div>

                <div class="gvn-help-card">
                    <div class="gvn-help-card__icon">4</div>
                    <div class="gvn-help-card__body">
                        <h3>Configurar o Order Bump</h3>
                        <p>Ofereça um produto adicional com desconto diretamente no checkout para aumentar o ticket médio. Ative o Order Bump na aba <strong>Configurações</strong>, selecione o produto, defina título, descrição, preço promocional e o texto do CTA.</p>
                    </div>
                </div>

                <div class="gvn-help-card">
                    <div class="gvn-help-card__icon">5</div>
                    <div class="gvn-help-card__body">
                        <h3>Gerenciar campos do formulário</h3>
                        <p>Na aba <strong>Campos do Formulário</strong> você adiciona, remove, reordena (arrastando) e configura a largura dos campos do checkout. Também é possível importar campos padrões do WooCommerce ou campos brasileiros (CPF, CNPJ, RG, etc.) com 1 clique.</p>
                        <a href="<?php echo esc_url( $fields_url ); ?>" class="button button-secondary">Gerenciar Campos</a>
                    </div>
                </div>

                <div class="gvn-help-card">
                    <div class="gvn-help-card__icon">6</div>
                    <div class="gvn-help-card__body">
                        <h3>Testar o checkout</h3>
                        <p>Adicione um produto ao carrinho e acesse a página de checkout para validar o layout, máscaras (CPF/Celular), autocompletar de CEP e o Order Bump.</p>
                        <?php if ( $checkout_url ) : ?>
                            <a href="<?php echo esc_url( $checkout_url ); ?>" class="button button-secondary" target="_blank">Abrir página de checkout</a>
                        <?php else : ?>
                            <span class="gvn-help-warning">Página de checkout ainda não definida. Conclua o passo 2.</span>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <div class="gvn-help-footer">
                <h3>Recursos disponíveis</h3>
                <ul>
                    <li><strong>Layout one-page checkout:</strong> focado em conversão, limpo e responsivo.</li>
                    <li><strong>Autocompletar de CEP:</strong> via ViaCEP com cache de 7 dias (Transients).</li>
                    <li><strong>Máscaras automáticas:</strong> CPF e Celular formatados em tempo real.</li>
                    <li><strong>Order Bump:</strong> oferta adicional com 1 clique para aumentar o ticket médio.</li>
                    <li><strong>Cupom dinâmico:</strong> seção de cupom com toggle moderno.</li>
                    <li><strong>Campos personalizáveis:</strong> com condições de exibição e largura por campo.</li>
                </ul>
            </div>

        </div>
        <?php
    }
}
