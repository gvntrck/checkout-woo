<?php
/**
 * Classe de administração do GVN Checkout.
 * Gerencia a página de configurações no painel do WordPress.
 *
 * @package GVN_Checkout
 * @version 1.0.8
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GVN_Admin {

    private static $instance = null;

    const DEFAULT_FIELDS_OPTION = 'gvn_checkout_default_fields_config';

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
        add_action( 'wp_ajax_gvn_save_default_fields_config', array( $this, 'ajax_save_default_fields_config' ) );
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
        $valid = array( 'settings', 'fields', 'default_fields' );
        $subtab = isset( $_GET['subtab'] ) ? sanitize_key( $_GET['subtab'] ) : 'settings';
        return in_array( $subtab, $valid, true ) ? $subtab : 'settings';
    }

    /**
     * Renderiza a página de configurações com sub-abas.
     */
    public function render_settings_page() {
        $current_subtab = $this->get_current_subtab();
        $base_url = admin_url( 'admin.php?page=wc-settings&tab=gvn_checkout' );

        $subtabs = array(
            'settings'       => 'Configurações',
            'fields'         => 'Campos do Formulário',
            'default_fields' => 'Campos Padrão',
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
            case 'default_fields':
                $this->render_default_fields_tab();
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
            'status' => 'publish',
            'limit'  => 100,
            'orderby' => 'title',
            'order'   => 'ASC',
        );

        $wc_products = wc_get_products( $args );

        if ( $wc_products ) {
            foreach ( $wc_products as $product ) {
                $products[ $product->get_id() ] = $product->get_name() . ' (' . wc_price( $product->get_price() ) . ')';
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

        if ( ! isset( $_GET['tab'] ) || 'gvn_checkout' !== $_GET['tab'] ) {
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

        // Prepara campos padrão para o JS usar nas condições de exibição
        $flat_fields = self::get_flat_default_fields();
        $df_for_js   = array();
        foreach ( $flat_fields as $f ) {
            $df_for_js[] = array(
                'key'   => $f['key'],
                'label' => $f['label'],
            );
        }

        wp_localize_script( 'gvn-admin-fields-js', 'gvn_admin_params', array(
            'ajax_url'       => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'gvn_admin_fields_nonce' ),
            'default_fields' => $df_for_js,
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
                <button type="button" class="button button-primary" id="gvn-save-fields">💾 Salvar Campos</button>
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
                                    <?php foreach ( $rules as $rule ) : ?>
                                        <div class="gvn-condition-rule">
                                            <select class="gvn-rule-field">
                                                <option value="">-- Campo --</option>
                                                <?php foreach ( $all_fields as $af ) : ?>
                                                    <?php if ( $af['key'] !== $field['key'] ) : ?>
                                                        <option value="<?php echo esc_attr( $af['key'] ); ?>" <?php selected( $rule['field'], $af['key'] ); ?>><?php echo esc_html( $af['label'] ?: $af['key'] ); ?></option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </select>
                                            <select class="gvn-rule-operator">
                                                <option value="equals" <?php selected( $rule['operator'], 'equals' ); ?>>Igual a</option>
                                                <option value="not_equals" <?php selected( $rule['operator'], 'not_equals' ); ?>>Diferente de</option>
                                                <option value="filled" <?php selected( $rule['operator'], 'filled' ); ?>>Preenchido</option>
                                                <option value="empty" <?php selected( $rule['operator'], 'empty' ); ?>>Vazio</option>
                                                <option value="contains" <?php selected( $rule['operator'], 'contains' ); ?>>Contém</option>
                                                <option value="greater" <?php selected( $rule['operator'], 'greater' ); ?>>Maior que</option>
                                                <option value="less" <?php selected( $rule['operator'], 'less' ); ?>>Menor que</option>
                                            </select>
                                            <input type="text" class="gvn-rule-value" value="<?php echo esc_attr( $rule['value'] ); ?>" placeholder="Valor" <?php echo in_array( $rule['operator'], array( 'filled', 'empty' ), true ) ? 'style="display:none;"' : ''; ?> />
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
     * Definições de todos os campos padrão brasileiros configuráveis.
     */
    public static function get_default_field_definitions() {
        return array(
            'billing' => array(
                'label'  => 'Campos de Cobrança (Billing)',
                'groups' => array(
                    'identification' => array(
                        'label'  => 'Identificação Pessoal / Empresarial',
                        'icon'   => '🪪',
                        'fields' => array(
                            'billing_persontype' => array(
                                'label'       => 'Tipo de Pessoa',
                                'description' => 'Pessoa Física ou Jurídica',
                                'type'        => 'select',
                                'options'     => "pf|Pessoa Física\npj|Pessoa Jurídica",
                                'mask'        => '',
                                'default_enabled'  => false,
                                'default_required' => false,
                                'can_disable'      => true,
                            ),
                            'billing_cpf' => array(
                                'label'       => 'CPF',
                                'description' => 'Para pessoa física',
                                'type'        => 'text',
                                'options'     => '',
                                'mask'        => 'cpf',
                                'placeholder' => '000.000.000-00',
                                'default_enabled'  => true,
                                'default_required' => true,
                                'can_disable'      => true,
                                'depends_on'       => array( 'field' => 'billing_persontype', 'value' => 'pf' ),
                            ),
                            'billing_rg' => array(
                                'label'       => 'RG',
                                'description' => 'Opcional, configurável',
                                'type'        => 'text',
                                'options'     => '',
                                'mask'        => 'rg',
                                'placeholder' => '',
                                'default_enabled'  => false,
                                'default_required' => false,
                                'can_disable'      => true,
                            ),
                            'billing_cnpj' => array(
                                'label'       => 'CNPJ',
                                'description' => 'Para pessoa jurídica',
                                'type'        => 'text',
                                'options'     => '',
                                'mask'        => 'cnpj',
                                'placeholder' => '00.000.000/0000-00',
                                'default_enabled'  => false,
                                'default_required' => false,
                                'can_disable'      => true,
                                'depends_on'       => array( 'field' => 'billing_persontype', 'value' => 'pj' ),
                            ),
                            'billing_ie' => array(
                                'label'       => 'Inscrição Estadual',
                                'description' => 'Opcional, configurável',
                                'type'        => 'text',
                                'options'     => '',
                                'mask'        => '',
                                'placeholder' => '',
                                'default_enabled'  => false,
                                'default_required' => false,
                                'can_disable'      => true,
                            ),
                        ),
                    ),
                    'demographic' => array(
                        'label'  => 'Demográficos',
                        'icon'   => '👤',
                        'fields' => array(
                            'billing_birthdate' => array(
                                'label'       => 'Data de Nascimento',
                                'description' => 'Opcional, configurável',
                                'type'        => 'text',
                                'options'     => '',
                                'mask'        => 'date',
                                'placeholder' => 'DD/MM/AAAA',
                                'default_enabled'  => false,
                                'default_required' => false,
                                'can_disable'      => true,
                            ),
                            'billing_gender' => array(
                                'label'       => 'Gênero',
                                'description' => 'Prefiro não dizer, Feminino, Masculino, Outro',
                                'type'        => 'select',
                                'options'     => "prefiro_nao_dizer|Prefiro não dizer\nfeminino|Feminino\nmasculino|Masculino\noutro|Outro",
                                'mask'        => '',
                                'placeholder' => '',
                                'default_enabled'  => false,
                                'default_required' => false,
                                'can_disable'      => true,
                            ),
                        ),
                    ),
                    'address' => array(
                        'label'  => 'Endereço',
                        'icon'   => '📍',
                        'fields' => array(
                            'billing_number' => array(
                                'label'       => 'Número',
                                'description' => 'Número do endereço (obrigatório)',
                                'type'        => 'text',
                                'options'     => '',
                                'mask'        => '',
                                'placeholder' => '',
                                'default_enabled'  => false,
                                'default_required' => true,
                                'can_disable'      => true,
                            ),
                            'billing_neighborhood' => array(
                                'label'       => 'Bairro',
                                'description' => 'Obrigatoriedade configurável',
                                'type'        => 'text',
                                'options'     => '',
                                'mask'        => '',
                                'placeholder' => '',
                                'default_enabled'  => false,
                                'default_required' => false,
                                'can_disable'      => true,
                            ),
                        ),
                    ),
                    'contact' => array(
                        'label'  => 'Contato',
                        'icon'   => '📱',
                        'fields' => array(
                            'billing_cellphone' => array(
                                'label'       => 'Celular',
                                'description' => 'Opcional, configurável',
                                'type'        => 'tel',
                                'options'     => '',
                                'mask'        => 'phone',
                                'placeholder' => '(00) 00000-0000',
                                'default_enabled'  => false,
                                'default_required' => false,
                                'can_disable'      => true,
                            ),
                        ),
                    ),
                ),
            ),
            'shipping' => array(
                'label'  => 'Campos de Entrega (Shipping)',
                'groups' => array(
                    'shipping_address' => array(
                        'label'  => 'Endereço',
                        'icon'   => '🚚',
                        'fields' => array(
                            'shipping_number' => array(
                                'label'       => 'Número',
                                'description' => 'Número do endereço (obrigatório)',
                                'type'        => 'text',
                                'options'     => '',
                                'mask'        => '',
                                'placeholder' => '',
                                'default_enabled'  => false,
                                'default_required' => true,
                                'can_disable'      => true,
                            ),
                            'shipping_neighborhood' => array(
                                'label'       => 'Bairro',
                                'description' => 'Obrigatoriedade configurável',
                                'type'        => 'text',
                                'options'     => '',
                                'mask'        => '',
                                'placeholder' => '',
                                'default_enabled'  => false,
                                'default_required' => false,
                                'can_disable'      => true,
                            ),
                        ),
                    ),
                ),
            ),
        );
    }

    /**
     * Retorna as configurações salvas dos campos padrão.
     */
    public static function get_saved_default_fields_config() {
        return get_option( self::DEFAULT_FIELDS_OPTION, array() );
    }

    /**
     * Achata todas as definições de campos padrão em uma lista plana ordenada.
     */
    public static function get_flat_default_fields() {
        $definitions  = self::get_default_field_definitions();
        $saved_config = self::get_saved_default_fields_config();
        $flat         = array();
        $index        = 0;

        foreach ( $definitions as $section_key => $section ) {
            foreach ( $section['groups'] as $group_key => $group ) {
                foreach ( $group['fields'] as $field_key => $field_def ) {
                    $config   = isset( $saved_config[ $field_key ] ) ? $saved_config[ $field_key ] : array();
                    $flat[] = array(
                        'key'         => $field_key,
                        'label'       => $field_def['label'],
                        'description' => $field_def['description'],
                        'type'        => $field_def['type'],
                        'group_icon'  => $group['icon'],
                        'group_label' => $group['label'],
                        'section'     => $section_key,
                        'enabled'     => isset( $config['enabled'] ) ? (bool) $config['enabled'] : $field_def['default_enabled'],
                        'required'    => isset( $config['required'] ) ? (bool) $config['required'] : $field_def['default_required'],
                        'position'    => isset( $config['position'] ) ? intval( $config['position'] ) : $index,
                    );
                    $index++;
                }
            }
        }

        usort( $flat, function ( $a, $b ) {
            return $a['position'] - $b['position'];
        } );

        return $flat;
    }

    /**
     * Renderiza a aba de campos padrão.
     */
    private function render_default_fields_tab() {
        $fields = self::get_flat_default_fields();
        ?>
        <div id="gvn-default-fields-manager">
            <h2>Campos Padrão do Checkout Brasileiro</h2>
            <p class="description">Ative ou desative os campos padrão brasileiros no checkout. Arraste para reordenar a posição no formulário de checkout.</p>

            <div class="gvn-default-fields-toolbar">
                <button type="button" class="button button-primary" id="gvn-save-default-fields">💾 Salvar Configurações</button>
                <span id="gvn-default-fields-status" style="display:none;"></span>
            </div>

            <div id="gvn-df-sortable-list">
                <?php foreach ( $fields as $pos => $field ) : ?>
                    <div class="gvn-df-field <?php echo $field['enabled'] ? 'gvn-df-field--active' : ''; ?>" data-field-key="<?php echo esc_attr( $field['key'] ); ?>">
                        <div class="gvn-df-field__main">
                            <span class="gvn-df-field__drag" title="Arrastar para reordenar">☰</span>
                            <span class="gvn-df-field__pos">#<?php echo esc_html( $pos + 1 ); ?></span>
                            <label class="gvn-df-field__toggle">
                                <input type="checkbox" class="gvn-df-enabled" <?php checked( $field['enabled'] ); ?> />
                                <span class="gvn-df-toggle-slider"></span>
                            </label>
                            <div class="gvn-df-field__info">
                                <span class="gvn-df-field__label"><?php echo esc_html( $field['label'] ); ?></span>
                                <span class="gvn-df-field__key"><?php echo esc_html( $field['key'] ); ?></span>
                                <span class="gvn-df-field__desc"><?php echo esc_html( $field['description'] ); ?></span>
                            </div>
                            <span class="gvn-df-field__section-badge gvn-df-field__section-badge--<?php echo esc_attr( $field['section'] ); ?>">
                                <?php echo esc_html( $field['group_icon'] . ' ' . $field['group_label'] ); ?>
                            </span>
                            <div class="gvn-df-field__options">
                                <label class="gvn-df-field__required-label">
                                    <input type="checkbox" class="gvn-df-required" <?php checked( $field['required'] ); ?> />
                                    Obrigatório
                                </label>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Salva configurações de campos padrão.
     */
    public function ajax_save_default_fields_config() {
        check_ajax_referer( 'gvn_admin_fields_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Permissão negada.' ) );
        }

        $raw = isset( $_POST['config'] ) ? wp_unslash( $_POST['config'] ) : '';
        $config = json_decode( $raw, true );

        if ( ! is_array( $config ) ) {
            wp_send_json_error( array( 'message' => 'Dados inválidos.' ) );
        }

        $definitions = self::get_default_field_definitions();
        $valid_keys  = array();

        foreach ( $definitions as $section ) {
            foreach ( $section['groups'] as $group ) {
                foreach ( $group['fields'] as $key => $def ) {
                    $valid_keys[] = $key;
                }
            }
        }

        $sanitized = array();
        foreach ( $config as $key => $values ) {
            $key = sanitize_key( $key );
            if ( ! in_array( $key, $valid_keys, true ) ) {
                continue;
            }
            $sanitized[ $key ] = array(
                'enabled'  => ! empty( $values['enabled'] ),
                'required' => ! empty( $values['required'] ),
                'position' => isset( $values['position'] ) ? intval( $values['position'] ) : 999,
            );
        }

        update_option( self::DEFAULT_FIELDS_OPTION, $sanitized );

        wp_send_json_success( array( 'message' => 'Campos padrão salvos com sucesso!' ) );
    }
}
