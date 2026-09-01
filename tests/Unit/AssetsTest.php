<?php

namespace GVN\Checkout\Tests\Unit;

use GVN_Checkout;
use GVN\Checkout\Settings\SettingsRepository;
use PHPUnit\Framework\TestCase;

/**
 * Testes unitários para a Fase F11: Assets, enfileiramento contextual e sanitização de CSS inline.
 */
class AssetsTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        global $wp_mock_options, $wp_mock_enqueued_styles, $wp_mock_enqueued_scripts, $wp_mock_inline_styles, $post;
        $wp_mock_options = [];
        $wp_mock_enqueued_styles = [];
        $wp_mock_enqueued_scripts = [];
        $wp_mock_inline_styles = [];
        $post = null;
        SettingsRepository::flush_cache();
    }

    public function test_enqueue_assets_does_not_enqueue_on_generic_pages(): void {
        global $post, $wp_mock_enqueued_styles;

        $post = new class {
            public $post_content = '<p>Página comum sem shortcode</p>';
        };

        $checkout = GVN_Checkout::get_instance();
        $checkout->enqueue_assets();

        $this->assertArrayNotHasKey('gvn-checkout-css', $wp_mock_enqueued_styles);
    }

    public function test_enqueue_assets_enqueues_on_checkout_page_with_sanitized_colors(): void {
        global $post, $wp_mock_enqueued_styles, $wp_mock_enqueued_scripts, $wp_mock_inline_styles;

        $post = (object) [
            'post_content' => '[gvn-checkout]',
        ];

        // Configura uma cor válida e uma cor com tentativa de injeção maliciosa de CSS
        update_option('gvn_checkout_primary_color', '#123456');
        update_option('gvn_checkout_button_color', '#ff0000; background: url(https://evil.com/xss);');

        $checkout = GVN_Checkout::get_instance();
        $checkout->enqueue_assets();

        // 1. Assets enfileirados
        $this->assertArrayHasKey('gvn-checkout-css', $wp_mock_enqueued_styles);
        $this->assertArrayHasKey('gvn-checkout-js', $wp_mock_enqueued_scripts);

        // 2. CSS inline sanitizado
        $this->assertArrayHasKey('gvn-checkout-css', $wp_mock_inline_styles);
        $inline_css = implode("\n", $wp_mock_inline_styles['gvn-checkout-css']);

        $this->assertStringContainsString('--gvn-primary: #123456;', $inline_css);
        // Cor maliciosa deve ser rejeitada e cair no default seguro (#ff8a22)
        $this->assertStringNotContainsString('evil.com', $inline_css);
        $this->assertStringContainsString('--gvn-button: #ff8a22;', $inline_css);
    }
}
