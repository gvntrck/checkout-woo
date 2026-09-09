<?php

namespace GVN\Checkout\Tests\Unit;

use GVN\Checkout\Layouts\LayoutRegistry;
use GVN\Checkout\Settings\SettingsRepository;
use GVN\Checkout\Settings\SettingsSchema;
use PHPUnit\Framework\TestCase;

/**
 * Testes unitários para o seletor de layouts do checkout (classic + split).
 */
class LayoutRegistryTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        global $wp_mock_actions, $wp_mock_options, $wp_mock_filters, $post;
        $wp_mock_actions = [];
        $wp_mock_options = [];
        $wp_mock_filters = [];
        $post = null;
        LayoutRegistry::flush_cache();
        SettingsRepository::flush_cache();
    }

    public function test_builtin_layouts_contain_classic_and_split(): void {
        $all = LayoutRegistry::get_all();

        $this->assertArrayHasKey('classic', $all);
        $this->assertArrayHasKey('split', $all);
        $this->assertArrayHasKey('minimal', $all);
        $this->assertArrayHasKey('corporate', $all);
        $this->assertSame('classic', LayoutRegistry::DEFAULT_LAYOUT);
    }

    public function test_resolve_returns_valid_slug_and_falls_back_to_default(): void {
        $this->assertSame('classic', LayoutRegistry::resolve('classic'));
        $this->assertSame('split', LayoutRegistry::resolve('split'));
        $this->assertSame('minimal', LayoutRegistry::resolve('minimal'));
        $this->assertSame('corporate', LayoutRegistry::resolve('corporate'));
        $this->assertSame('classic', LayoutRegistry::resolve('nao-existe'));
        $this->assertSame('classic', LayoutRegistry::resolve(''));
        $this->assertSame('classic', LayoutRegistry::resolve(null));
        $this->assertSame('classic', LayoutRegistry::resolve('<script>alert(1)</script>'));
    }

    public function test_templates_exist_and_are_readable(): void {
        foreach (LayoutRegistry::get_ids() as $slug) {
            $template = LayoutRegistry::get_template($slug);
            $this->assertFileExists($template, "Template do layout '{$slug}' deve existir.");
            $this->assertStringEndsWith('.php', $template);
        }

        // Slug desconhecido cai para o template clássico.
        $this->assertSame(
            LayoutRegistry::get_template('classic'),
            LayoutRegistry::get_template('layout-que-nao-existe')
        );
    }

    public function test_third_layout_can_be_registered_via_filter(): void {
        add_filter('gvn_checkout_layouts', function ($layouts) {
            $layouts['futuro'] = [
                'label'    => 'Futuro',
                'template' => GVN_CHECKOUT_PLUGIN_DIR . 'templates/checkout-template.php',
                'css'      => null,
                'js'       => null,
            ];
            return $layouts;
        });
        LayoutRegistry::flush_cache();

        $this->assertTrue(LayoutRegistry::exists('futuro'));
        $this->assertSame('futuro', LayoutRegistry::resolve('futuro'));
        $this->assertContains('futuro', LayoutRegistry::get_ids());
        $this->assertArrayHasKey('futuro', LayoutRegistry::get_options_for_admin());
    }

    public function test_schema_defaults_and_sanitizes_layout(): void {
        $defaults = SettingsSchema::get_defaults();
        $this->assertArrayHasKey('checkout_layout', $defaults);
        $this->assertSame('classic', $defaults['checkout_layout']);

        $this->assertSame('split', SettingsSchema::sanitize_setting('checkout_layout', 'split'));
        $this->assertSame('classic', SettingsSchema::sanitize_setting('checkout_layout', 'invalido'));
        $this->assertSame('classic', SettingsSchema::sanitize_setting('checkout_layout', ''));
    }

    public function test_shortcode_attribute_overrides_configured_layout(): void {
        global $wp_mock_options;
        $wp_mock_options['gvn_checkout_checkout_layout'] = 'classic';
        SettingsRepository::flush_cache();

        $this->assertSame('classic', \GVN_Checkout::get_current_layout([]));
        $this->assertSame('split', \GVN_Checkout::get_current_layout(['layout' => 'split']));
        $this->assertSame('classic', \GVN_Checkout::get_current_layout(['layout' => 'invalido']));
    }

    public function test_configured_layout_is_used_when_shortcode_has_no_attribute(): void {
        global $wp_mock_options;
        $wp_mock_options['gvn_checkout_checkout_layout'] = 'split';
        SettingsRepository::flush_cache();

        $this->assertSame('split', \GVN_Checkout::get_current_layout([]));
    }

    public function test_split_template_keeps_canonical_hooks_and_js_contract(): void {
        $this->assertLayoutTemplateContract('split');
    }

    public function test_minimal_template_keeps_canonical_hooks_and_js_contract(): void {
        $this->assertLayoutTemplateContract('minimal');
    }

    public function test_corporate_template_keeps_canonical_hooks_and_js_contract(): void {
        $this->assertLayoutTemplateContract('corporate');
    }

    /**
     * Renderiza um template de layout e valida hooks canônicos + contrato com o JS.
     */
    private function assertLayoutTemplateContract(string $slug): void {
        global $checkout;
        $checkout = \WC()->checkout();

        ob_start();
        include GVN_CHECKOUT_PLUGIN_DIR . 'templates/checkout/layout-' . $slug . '.php';
        $html = ob_get_clean();

        $this->assertNotEmpty($html);

        // Hooks canônicos (mesma ordem do clássico).
        foreach ([
            'woocommerce_before_checkout_form',
            'woocommerce_checkout_before_customer_details',
            'woocommerce_after_checkout_billing_form',
            'woocommerce_checkout_after_customer_details',
            'woocommerce_checkout_before_order_review',
            'woocommerce_checkout_after_order_review',
            'woocommerce_review_order_before_payment',
            'woocommerce_checkout_terms_and_conditions',
            'woocommerce_review_order_before_submit',
            'woocommerce_review_order_after_submit',
            'woocommerce_after_checkout_form',
        ] as $hook) {
            $this->assertSame(1, did_action($hook), "Hook '{$hook}' deve disparar exatamente 1 vez no {$slug}.");
        }

        // Contrato com o JS/fragments.
        foreach ([
            'id="gvn-checkout"',
            'data-layout="' . $slug . '"',
            'name="checkout"',
            'id="customer_details"',
            'id="gvn-order-items"',
            'id="gvn-order-totals"',
            'id="gvn-subtotal"',
            'id="gvn-total"',
            'id="gvn-coupon-toggle"',
            'id="gvn-coupon-form"',
            'id="gvn-coupon-code"',
            'id="gvn-apply-coupon"',
            'id="payment"',
            'name="payment_method"',
            'id="place_order"',
            'name="woocommerce_checkout_place_order"',
            'name="woocommerce-process-checkout-nonce"',
        ] as $needle) {
            $this->assertStringContainsString($needle, $html, "Layout {$slug} deve conter '{$needle}'.");
        }
    }
}
