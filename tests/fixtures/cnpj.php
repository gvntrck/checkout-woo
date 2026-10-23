<?php
// Local browser-test fixture; never used by the plugin runtime.
require dirname(__DIR__) . '/bootstrap.php';

update_option(\GVN_Custom_Fields::OPTION_KEY, [[
    'key' => 'billing_cnpj', 'label' => 'CNPJ', 'type' => 'text',
    'mask' => 'cnpj', 'enabled' => true, 'required' => true,
]]);
WC()->cart->add_to_cart(1);
$checkout = WC()->checkout();
include GVN_CHECKOUT_PLUGIN_DIR . (($argv[1] ?? '') === 'split'
    ? 'templates/checkout/layout-split.php'
    : 'templates/checkout-template.php');
