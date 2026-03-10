<?php
/**
 * Classe de administração do GVN Checkout.
 * Gerencia a página de configurações no painel do WordPress.
 *
 * @package GVN_Checkout
 * @version 1.0.0
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
     * Renderiza a página de configurações.
     */
    public function render_settings_page() {
        woocommerce_admin_fields( $this->get_settings() );
        $this->render_fields_manager();
    }

    /**
     * Salva as configurações.
     */
    public function save_settings() {
        woocommerce_update_options( $this->get_settings() );
    }

    /**
     * Define os campos de configuração.
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
                <button type="button" class="button button-primary" id="gvn-save-fields">💾 Salvar Campos</button>
                <span id="gvn-fields-status" style="display:none;"></span>
            </div>

            <div id="gvn-fields-list">
                <?php foreach ( $fields as $field ) : ?>
                    <div class="gvn-field-row<?php echo empty( $field['enabled'] ) ? ' gvn-field-row--disabled' : ''; ?>" data-key="<?php echo esc_attr( $field['key'] ); ?>" data-default="<?php echo $field['is_default'] ? 'true' : 'false'; ?>">
                        <div class="gvn-field-row__header">
                            <span class="gvn-field-drag" title="Arrastar para reordenar">☰</span>
                            <span class="gvn-field-pos-label">#<?php echo esc_html( $field['position'] ); ?></span>
                            <span class="gvn-field-label-display"><?php echo esc_html( $field['label'] ?: '(sem label)' ); ?></span>
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
                                <div class="gvn-field-col">
                                    <label><input type="checkbox" class="gvn-field-required" <?php checked( ! empty( $field['required'] ) ); ?> /> Obrigatório</label>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}
