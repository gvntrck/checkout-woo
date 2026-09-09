<?php

namespace GVN\Checkout\Layouts;

/**
 * Registro central de layouts do checkout.
 *
 * Cada layout é descrito por:
 *  - label:    rótulo exibido na tela de configurações.
 *  - template: caminho absoluto do template PHP do layout.
 *  - css:      caminho relativo (a partir da raiz do plugin) do CSS exclusivo, ou null.
 *  - js:       caminho relativo (a partir da raiz do plugin) do JS exclusivo, ou null.
 *
 * Para criar um novo layout no futuro:
 *  1. Crie `templates/checkout/layout-{slug}.php` mantendo o contrato
 *     (form[name=checkout], nonce, payment_method, hooks do Woo + IDs
 *     #gvn-order-items, #gvn-order-totals, #payment, #place_order).
 *  2. Registre via filtro `gvn_checkout_layouts` ou edite `get_builtin_layouts()`.
 *  3. (Opcional) Adicione CSS/JS exclusivos em `assets/`.
 *
 * @since 1.14.0
 */
final class LayoutRegistry {

    const DEFAULT_LAYOUT = 'classic';

    /**
     * @var array<string, array<string, mixed>>|null Cache por requisição.
     */
    private static $cache = null;

    /**
     * Limpa o cache em memória (útil para testes ou após filtrar layouts).
     */
    public static function flush_cache(): void {
        self::$cache = null;
    }

    /**
     * Layouts nativos do plugin.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function get_builtin_layouts(): array {
        $base = defined( 'GVN_CHECKOUT_PLUGIN_DIR' ) ? GVN_CHECKOUT_PLUGIN_DIR : '';

        return array(
            'classic' => array(
                'label'    => __( 'Clássico (padrão)', 'gvn-checkout' ),
                'template' => $base . 'templates/checkout-template.php',
                'css'      => null,
                'js'       => null,
            ),
            'split'   => array(
                'label'    => __( 'Dividido (resumo lateral)', 'gvn-checkout' ),
                'template' => $base . 'templates/checkout/layout-split.php',
                'css'      => 'assets/css/gvn-checkout-layout-split.css',
                'js'       => null,
            ),
            'minimal' => array(
                'label'    => __( 'Minimalista (claro)', 'gvn-checkout' ),
                'template' => $base . 'templates/checkout/layout-minimal.php',
                'css'      => 'assets/css/gvn-checkout-layout-minimal.css',
                'js'       => null,
            ),
            'corporate' => array(
                'label'    => __( 'Corporativo (institucional)', 'gvn-checkout' ),
                'template' => $base . 'templates/checkout/layout-corporate.php',
                'css'      => 'assets/css/gvn-checkout-layout-corporate.css',
                'js'       => null,
            ),
        );
    }

    /**
     * Todos os layouts disponíveis (nativos + registrados via filtro).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function get_all(): array {
        if ( null !== self::$cache ) {
            return self::$cache;
        }

        $layouts = self::get_builtin_layouts();

        if ( function_exists( 'apply_filters' ) ) {
            $filtered = apply_filters( 'gvn_checkout_layouts', $layouts );
            if ( is_array( $filtered ) ) {
                $layouts = $filtered;
            }
        }

        // Remove entradas malformadas sem quebrar os demais layouts.
        foreach ( $layouts as $slug => $layout ) {
            if ( ! is_string( $slug ) || '' === $slug || ! is_array( $layout )
                || empty( $layout['label'] ) || empty( $layout['template'] ) || ! is_string( $layout['template'] ) ) {
                unset( $layouts[ $slug ] );
            }
        }

        // O layout padrão nunca pode ficar ausente.
        if ( ! isset( $layouts[ self::DEFAULT_LAYOUT ] ) ) {
            $builtin = self::get_builtin_layouts();
            $layouts[ self::DEFAULT_LAYOUT ] = $builtin[ self::DEFAULT_LAYOUT ];
        }

        self::$cache = $layouts;
        return self::$cache;
    }

    /**
     * Slugs de todos os layouts registrados.
     *
     * @return string[]
     */
    public static function get_ids(): array {
        return array_keys( self::get_all() );
    }

    /**
     * Verifica se um slug corresponde a um layout registrado.
     *
     * @param string $slug
     * @return bool
     */
    public static function exists( string $slug ): bool {
        $all = self::get_all();
        return isset( $all[ $slug ] );
    }

    /**
     * Normaliza um valor bruto para um slug de layout válido.
     * Qualquer valor desconhecido cai para o layout padrão (fail-safe).
     *
     * @param mixed $raw
     * @return string
     */
    public static function resolve( $raw ): string {
        $slug = function_exists( 'sanitize_key' )
            ? sanitize_key( (string) $raw )
            : strtolower( (string) preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $raw ) );

        if ( '' === $slug || ! self::exists( $slug ) ) {
            return self::DEFAULT_LAYOUT;
        }

        return $slug;
    }

    /**
     * Opções prontas para o campo `select` da tela de configurações.
     *
     * @return array<string, string> slug => label
     */
    public static function get_options_for_admin(): array {
        $options = array();
        foreach ( self::get_all() as $slug => $layout ) {
            $options[ $slug ] = (string) $layout['label'];
        }
        return $options;
    }

    /**
     * Caminho absoluto do template de um layout, com suporte a override pelo tema.
     *
     * Ordem de resolução:
     *  1. `gvn-checkout/layout-{slug}.php` no tema (filho ou pai);
     *  2. Template registrado no plugin;
     *  3. Template do layout padrão (fail-safe).
     *
     * @param string $slug Slug já resolvido via `resolve()`.
     * @return string Caminho absoluto do arquivo PHP a incluir.
     */
    public static function get_template( string $slug ): string {
        $all    = self::get_all();
        $layout = isset( $all[ $slug ] ) ? $all[ $slug ] : $all[ self::DEFAULT_LAYOUT ];

        $theme_template = '';
        if ( function_exists( 'locate_template' ) ) {
            $theme_template = locate_template( array( 'gvn-checkout/layout-' . $slug . '.php' ), false, false );
        }

        $template = ( is_string( $theme_template ) && '' !== $theme_template )
            ? $theme_template
            : (string) $layout['template'];

        if ( ! is_readable( $template ) ) {
            $template = (string) $all[ self::DEFAULT_LAYOUT ]['template'];
        }

        if ( function_exists( 'apply_filters' ) ) {
            $filtered = apply_filters( 'gvn_checkout_template_path', $template, $slug );
            if ( is_string( $filtered ) && is_readable( $filtered ) ) {
                $template = $filtered;
            }
        }

        return $template;
    }

    /**
     * URL do CSS exclusivo do layout, ou string vazia quando não há.
     *
     * @param string $slug
     * @return string
     */
    public static function get_css_url( string $slug ): string {
        $all = self::get_all();
        if ( ! isset( $all[ $slug ] ) || empty( $all[ $slug ]['css'] ) ) {
            return '';
        }

        $relative = ltrim( (string) $all[ $slug ]['css'], '/' );

        if ( defined( 'GVN_CHECKOUT_PLUGIN_DIR' ) && ! is_readable( GVN_CHECKOUT_PLUGIN_DIR . $relative ) ) {
            return '';
        }

        if ( ! defined( 'GVN_CHECKOUT_PLUGIN_URL' ) ) {
            return '';
        }

        return GVN_CHECKOUT_PLUGIN_URL . $relative;
    }

    /**
     * URL do JS exclusivo do layout, ou string vazia quando não há.
     *
     * @param string $slug
     * @return string
     */
    public static function get_js_url( string $slug ): string {
        $all = self::get_all();
        if ( ! isset( $all[ $slug ] ) || empty( $all[ $slug ]['js'] ) ) {
            return '';
        }

        $relative = ltrim( (string) $all[ $slug ]['js'], '/' );

        if ( defined( 'GVN_CHECKOUT_PLUGIN_DIR' ) && ! is_readable( GVN_CHECKOUT_PLUGIN_DIR . $relative ) ) {
            return '';
        }

        if ( ! defined( 'GVN_CHECKOUT_PLUGIN_URL' ) ) {
            return '';
        }

        return GVN_CHECKOUT_PLUGIN_URL . $relative;
    }
}
