<?php
// Local browser-test fixture; never used by the plugin runtime.
require dirname(__DIR__) . '/bootstrap.php';

update_option('gvn_checkout_participants_enabled', 'yes');
update_option('gvn_checkout_participants_products', [1]);
$module = new \GVN\Checkout\Checkout\Participants();
WC()->cart->add_to_cart(1, max(1, (int) ($argv[2] ?? 2)));
$checkout = WC()->checkout();
include GVN_CHECKOUT_PLUGIN_DIR . (($argv[1] ?? '') === 'split'
    ? 'templates/checkout/layout-split.php'
    : 'templates/checkout-template.php');
