<?php

namespace GVN\Checkout\Checkout;

use GVN\Checkout\Fields\FieldValidator;

/** Optional classic-checkout module. No checkout hooks or assets when disabled. */
final class Participants {
    const PREFIX = 'gvn_checkout_participants_';

    public function __construct() {
        // Historical participants remain visible even after the feature is disabled.
        add_action('woocommerce_after_order_itemmeta', [$this, 'admin_details'], 10, 3);
        if (get_option(self::PREFIX . 'enabled', 'no') !== 'yes') {
            return;
        }
        add_action('woocommerce_after_checkout_billing_form', [$this, 'render']);
        add_action('woocommerce_before_checkout_billing_form', [$this, 'render_intent']);
        add_filter('gvn_checkout_field_label', [$this, 'buyer_label'], 10, 2);
        add_filter('woocommerce_form_field_args', [$this, 'native_label'], 20, 2);
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
        add_filter('woocommerce_update_order_review_fragments', [$this, 'fragments']);
        add_action('woocommerce_after_checkout_validation', [$this, 'validate'], 20, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'save'], 20, 4);
    }

    public static function settings(): array {
        $products = [];
        foreach ((array) get_option(self::PREFIX . 'products', []) as $id) {
            $product = is_scalar($id) ? wc_get_product((int) $id) : false;
            if ($product) {
                $products[$product->get_id()] = $product->get_formatted_name();
            }
        }
        $settings = [
            ['type' => 'title', 'id' => self::PREFIX . 'section', 'title' => __('Participantes / Alunos', 'gvn-checkout'),
                'desc' => __('Cada unidade dos cursos selecionados corresponde a uma vaga. Desligado por padrão. Compatível com os layouts GVN e o checkout clássico do WooCommerce, não com Checkout Blocks. Desative “produto único” para vender várias vagas. A matrícula na plataforma de cursos não é automática.', 'gvn-checkout')],
            ['type' => 'checkbox', 'id' => self::PREFIX . 'enabled', 'default' => 'no', 'title' => __('Ativar recurso', 'gvn-checkout'), 'desc' => __('Solicitar os dados dos participantes nos produtos selecionados.', 'gvn-checkout')],
            ['type' => 'multiselect', 'id' => self::PREFIX . 'products', 'title' => __('Cursos / produtos', 'gvn-checkout'), 'default' => [],
                'class' => 'wc-product-search', 'css' => 'min-width:300px;', 'options' => $products,
                'custom_attributes' => ['data-action' => 'woocommerce_json_search_products_and_variations', 'data-placeholder' => __('Busque pelo nome do curso', 'gvn-checkout')],
                'desc' => __('Selecionar o produto pai inclui todas as suas variações. Order bumps não geram participantes.', 'gvn-checkout')],
            ['type' => 'checkbox', 'id' => self::PREFIX . 'buyer', 'default' => 'yes', 'title' => __('Comprador também é aluno', 'gvn-checkout'), 'desc' => __('Permitir usar os dados do comprador na primeira vaga de cada curso.', 'gvn-checkout')],
        ];
        foreach (self::labels() as $field => $label) {
            $settings[] = ['type' => 'select', 'id' => self::PREFIX . $field, 'title' => $label, 'default' => 'required',
                'options' => ['required' => __('Obrigatório', 'gvn-checkout'), 'optional' => __('Opcional', 'gvn-checkout'), 'hidden' => __('Não solicitar', 'gvn-checkout')]];
        }
        $settings[] = ['type' => 'sectionend', 'id' => self::PREFIX . 'section'];
        return $settings;
    }

    private static function labels(): array {
        return ['name' => __('Nome completo', 'gvn-checkout'), 'email' => __('E-mail', 'gvn-checkout'), 'cpf' => __('CPF', 'gvn-checkout')];
    }

    private function mode(string $field): string {
        $mode = get_option(self::PREFIX . $field, 'required');
        return in_array($mode, ['required', 'optional', 'hidden'], true) ? $mode : 'required';
    }

    public function groups(): array {
        if (get_option(self::PREFIX . 'enabled', 'no') !== 'yes' || !function_exists('WC') || !WC()->cart) {
            return [];
        }
        $ids = array_filter(array_map('intval', array_filter((array) get_option(self::PREFIX . 'products', []), 'is_scalar')), static function ($id) { return $id > 0; });
        $groups = [];
        foreach (WC()->cart->get_cart() as $key => $item) {
            if (!empty($item['gvn_order_bump']) || empty($item['data']) || (int) ($item['quantity'] ?? 0) < 1) {
                continue;
            }
            if (!in_array((int) ($item['product_id'] ?? 0), $ids, true) && !in_array((int) ($item['variation_id'] ?? 0), $ids, true)) {
                continue;
            }
            $groups[$key] = ['name' => $item['data']->get_name(), 'quantity' => (int) $item['quantity']];
        }
        return $groups;
    }

    public function enqueue(): void {
        global $post;
        $gvn = is_object($post) && isset($post->post_content) && has_shortcode($post->post_content, 'gvn-checkout');
        if ((!$gvn && !(function_exists('is_checkout') && is_checkout())) || (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received')) || !$this->groups()) {
            return;
        }
        wp_enqueue_style('gvn-participants', GVN_CHECKOUT_PLUGIN_URL . 'assets/css/gvn-participants.css', [], GVN_CHECKOUT_VERSION);
        wp_enqueue_script('gvn-participants', GVN_CHECKOUT_PLUGIN_URL . 'assets/js/gvn-participants.js', ['jquery', 'wc-checkout'], GVN_CHECKOUT_VERSION, true);
    }

    private function request(): array {
        // WooCommerce verifies the checkout/update-order-review nonce before using these values.
        $data = wp_unslash($_POST); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only rendering; validate/save verify the participant nonce, WooCommerce verifies its own request nonce.
        if (isset($data['post_data']) && is_string($data['post_data'])) {
            parse_str($data['post_data'], $data);
        }
        return is_array($data) ? $data : [];
    }

    public function render(): void {
        if (!$this->groups()) {
            return;
        }
        echo $this->html($this->request()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- html() escapes each attribute and text value; includes trusted form markup.
    }

    public function fragments(array $fragments): array {
        if ($this->groups()) {
            $fragments['#gvn-participants'] = $this->html($this->request());
            $fragments['#gvn-participants-intent'] = $this->intent_html($this->request());
        } elseif (isset($this->request()['gvn_participants_nonce'])) {
            $fragments['#gvn-participants'] = '<section id="gvn-participants" class="gvn-participants"></section>';
            $fragments['#gvn-participants-intent'] = '<div id="gvn-participants-intent"></div>';
        }
        return $fragments;
    }

    public function buyer_label($label, $key): string {
        if (!$this->groups()) {
            return (string) $label;
        }
        $labels = [
            'billing_first_name' => __('Nome do comprador', 'gvn-checkout'),
            'billing_last_name' => __('Sobrenome do comprador', 'gvn-checkout'),
            'billing_cpf' => __('CPF do comprador/pagador', 'gvn-checkout'),
            'billing_email' => __('E-mail do comprador', 'gvn-checkout'),
        ];
        return $labels[$key] ?? (string) $label;
    }

    public function native_label(array $args, $key): array {
        if (!$this->groups()) {
            return $args;
        }
        $args['label'] = $this->buyer_label($args['label'] ?? '', $key);
        return $args;
    }

    public function render_intent(): void {
        echo $this->intent_html($this->request()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside intent_html().
    }

    public function intent_html(array $data = []): string {
        $groups = $this->groups();
        if (!$groups) {
            return '';
        }
        ob_start();
        ?>
        <div id="gvn-participants-intent" class="gvn-participants">
            <h3 class="gvn-section__title"><?php esc_html_e('Quem vai participar do curso?', 'gvn-checkout'); ?></h3>
            <?php foreach ($groups as $key => $group) : ?>
                <fieldset class="gvn-participants__intent" data-course="<?php echo esc_attr($key); ?>">
                    <legend><?php echo esc_html($group['name']); ?></legend>
                    <?php if (get_option(self::PREFIX . 'buyer', 'yes') === 'yes') : ?>
                        <label><input type="radio" name="gvn_participants[<?php echo esc_attr($key); ?>][buyer]" value="1" <?php checked($this->is_buyer($data, $key)); ?>> <?php echo esc_html($group['quantity'] === 1 ? __('Eu mesmo', 'gvn-checkout') : __('Eu e outras pessoas', 'gvn-checkout')); ?></label>
                    <?php endif; ?>
                    <label><input type="radio" name="gvn_participants[<?php echo esc_attr($key); ?>][buyer]" value="0" <?php checked(!$this->is_buyer($data, $key)); ?>> <?php echo esc_html($group['quantity'] === 1 ? __('Outra pessoa', 'gvn-checkout') : __('Somente outras pessoas', 'gvn-checkout')); ?></label>
                </fieldset>
            <?php endforeach; ?>
            <h3 class="gvn-section__title"><?php esc_html_e('Dados do comprador / responsável pelo pagamento', 'gvn-checkout'); ?></h3>
            <p><?php esc_html_e('Informe os dados de quem está realizando a compra, mesmo que não participe do curso.', 'gvn-checkout'); ?></p>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public function html(array $data = []): string {
        $groups = $this->groups();
        if (!$groups) {
            return '';
        }
        ob_start();
        ?>
        <section id="gvn-participants" class="gvn-participants" aria-labelledby="gvn-participants-title">
            <h3 id="gvn-participants-title" class="gvn-section__title"><?php esc_html_e('Informações dos participantes', 'gvn-checkout'); ?></h3>
            <p><?php esc_html_e('Informe os dados de quem fará o curso. Cada unidade comprada corresponde a uma vaga.', 'gvn-checkout'); ?></p>
            <?php if (function_exists('wc_get_cart_url')) : ?>
                <p><a href="<?php echo esc_url(wc_get_cart_url()); ?>"><?php esc_html_e('Alterar quantidade de vagas no carrinho', 'gvn-checkout'); ?></a></p>
            <?php endif; ?>
            <?php wp_nonce_field('gvn_participants', 'gvn_participants_nonce', false); ?>
            <?php foreach ($groups as $key => $group) : ?>
                <fieldset class="gvn-participants__course" data-course="<?php echo esc_attr($key); ?>">
                    <legend><?php echo esc_html($group['name']); ?> (<?php echo esc_html($group['quantity']); ?>)</legend>
                    <?php for ($index = 0; $index < $group['quantity']; $index++) : ?>
                        <fieldset class="gvn-participants__person" data-index="<?php echo esc_attr($index); ?>">
                            <legend><?php echo esc_html(sprintf(__('Participante %d', 'gvn-checkout'), $index + 1)); ?></legend>
                            <p class="gvn-participants__buyer-note" hidden><?php esc_html_e('Esta vaga usará seu nome, e-mail e CPF informados nos dados de cobrança.', 'gvn-checkout'); ?></p>
                            <div class="gvn-participants__fields">
                            <?php foreach (self::labels() as $field => $label) :
                                if ($this->mode($field) === 'hidden') {
                                    continue;
                                }
                                $id = 'gvn-participant-' . $key . '-' . $index . '-' . $field;
                                $value = $data['gvn_participants'][$key]['people'][$index][$field] ?? '';
                                $value = is_scalar($value) ? (string) $value : '';
                                $required = $this->mode($field) === 'required';
                                ?>
                                <p class="form-row form-row-wide">
                                    <label class="gvn-field__label" for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label . __(' do participante', 'gvn-checkout')); ?><?php echo $required ? ' *' : ''; ?></label>
                                    <input id="<?php echo esc_attr($id); ?>" class="input-text gvn-field__input" type="<?php echo $field === 'email' ? 'email' : 'text'; ?>" name="gvn_participants[<?php echo esc_attr($key); ?>][people][<?php echo esc_attr($index); ?>][<?php echo esc_attr($field); ?>]" value="<?php echo esc_attr($value); ?>" maxlength="<?php echo $field === 'cpf' ? '14' : '254'; ?>" data-participant-field="<?php echo esc_attr($field); ?>" data-participant-required="<?php echo $required ? '1' : '0'; ?>" <?php echo $required ? 'required aria-required="true"' : ''; ?> <?php echo $field === 'cpf' ? 'inputmode="numeric"' : ''; ?>>
                                </p>
                            <?php endforeach; ?>
                            </div>
                        </fieldset>
                    <?php endfor; ?>
                </fieldset>
            <?php endforeach; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private function is_buyer(array $data, string $key): bool {
        if (get_option(self::PREFIX . 'buyer', 'yes') !== 'yes') {
            return false;
        }
        $choice = $data['gvn_participants'][$key]['buyer'] ?? null;
        // Default only on initial rendering. Missing submitted choices keep legacy unchecked semantics.
        return $choice === null ? !isset($data['gvn_participants_nonce']) : $choice === '1';
    }

    /** Resolve exclusively from the current cart; never trust submitted counts or buyer identity. */
    public function collect(array $data, $errors = null): array {
        $result = [];
        foreach ($this->groups() as $key => $group) {
            $people = [];
            for ($index = 0; $index < $group['quantity']; $index++) {
                $buyer = $index === 0 && $this->is_buyer($data, $key);
                $person = $data['gvn_participants'][$key]['people'][$index] ?? [];
                if ($buyer) {
                    $person = [
                        'name' => $this->scalar($data['billing_first_name'] ?? '') . ' ' . $this->scalar($data['billing_last_name'] ?? ''),
                        'email' => $data['billing_email'] ?? '',
                        'cpf' => $data['billing_cpf'] ?? '',
                    ];
                }
                $values = [];
                $fields = [];
                foreach (self::labels() as $field => $label) {
                    if ($this->mode($field) === 'hidden') {
                        continue;
                    }
                    $value = $this->scalar(is_array($person) ? ($person[$field] ?? '') : '');
                    $values[$field] = $field === 'cpf' ? preg_replace('/\D/', '', $value) : sanitize_text_field($value);
                    $fields[] = ['key' => $field, 'enabled' => true, 'required' => $this->mode($field) === 'required',
                        'type' => $field === 'email' ? 'email' : 'text', 'mask' => $field === 'cpf' ? 'cpf' : '',
                        'label' => sprintf(__('%1$s, participante %2$d de %3$s', 'gvn-checkout'), $label, $index + 1, $group['name'])];
                }
                foreach (FieldValidator::validate($fields, $values, new \WP_Error()) as $field => $message) {
                    if ($errors) {
                        $errors->add('gvn_participant_' . $key . '_' . $index . '_' . $field, $message);
                    }
                }
                $people[] = $values;
            }
            $result[$key] = $people;
        }
        return $result;
    }

    private function scalar($value): string {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    public function validate(array $posted, $errors): void {
        if (!$this->groups()) {
            return;
        }
        $data = $this->request();
        if (!wp_verify_nonce($this->scalar($data['gvn_participants_nonce'] ?? ''), 'gvn_participants')) {
            $errors->add('gvn_participants_nonce', __('Recarregue o checkout para informar os participantes.', 'gvn-checkout'));
            return;
        }
        // Normalized WooCommerce billing fields take precedence over raw input.
        $this->collect(array_merge($data, $posted), $errors);
    }

    public function save($item, $cart_item_key, $values, $order): void {
        if (!isset($this->groups()[$cart_item_key])) {
            return;
        }
        $data = $this->request();
        if (!wp_verify_nonce($this->scalar($data['gvn_participants_nonce'] ?? ''), 'gvn_participants')) {
            throw new \Exception(esc_html__('Recarregue o checkout para informar os participantes.', 'gvn-checkout'));
        }
        $data['billing_first_name'] = $order->get_billing_first_name();
        $data['billing_last_name'] = $order->get_billing_last_name();
        $data['billing_email'] = $order->get_billing_email();
        // billing_cpf may be supplied by a custom field plugin and not yet saved to the order.
        $errors = new \WP_Error();
        $people = $this->collect($data, $errors);
        if ($errors->has_errors()) {
            throw new \Exception(esc_html__('Confira os dados dos participantes e tente novamente.', 'gvn-checkout'));
        }
        $item->add_meta_data('_gvn_participants', $people[$cart_item_key], true);
        $item->add_meta_data('_gvn_participants_buyer', $this->is_buyer($data, $cart_item_key) ? 'yes' : 'no', true);
    }

    public function admin_details($item_id, $item, $product): void {
        // Private metadata is deliberately not exposed on public thank-you pages or emails.
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        $people = $item->get_meta('_gvn_participants', true);
        if (!is_array($people)) {
            return;
        }
        echo '<div class="gvn-participants-admin"><strong>' . esc_html__('Participantes / Alunos', 'gvn-checkout') . '</strong><ol>';
        foreach ($people as $person) {
            if (!is_array($person)) {
                continue;
            }
            echo '<li>';
            foreach (self::labels() as $field => $label) {
                if (isset($person[$field]) && is_scalar($person[$field])) {
                    echo esc_html($label . ': ' . $person[$field]) . '<br>';
                }
            }
            echo '</li>';
        }
        echo '</ol></div>';
    }
}
