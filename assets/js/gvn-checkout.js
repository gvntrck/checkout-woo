/**
 * GVN Checkout - JavaScript
 * @version 1.0.0
 */

(function ($) {
    'use strict';

    var GVNCheckout = {

        init: function () {
            this.bindCoupon();
            this.bindGateways();
            this.bindOrderBump();
            this.bindMasks();
            this.bindConditionalFields();
            this.showFirstGatewayFields();
        },

        /* ============================
           Cupom de Desconto
           ============================ */

        bindCoupon: function () {
            var self = this;

            $('#gvn-coupon-toggle').on('click', function () {
                var $form = $('#gvn-coupon-form');
                var $arrow = $('#gvn-coupon-arrow');
                $form.slideToggle(200);
                $arrow.toggleClass('gvn-coupon__arrow--open');
            });

            $('#gvn-apply-coupon').on('click', function () {
                self.applyCoupon();
            });

            $('#gvn-coupon-code').on('keypress', function (e) {
                if (e.which === 13) {
                    e.preventDefault();
                    self.applyCoupon();
                }
            });

            $(document).on('click', '.gvn-coupon__remove', function () {
                self.removeCoupon($(this).data('coupon'));
            });
        },

        applyCoupon: function () {
            var code = $('#gvn-coupon-code').val().trim();
            var $message = $('#gvn-coupon-message');
            var $btn = $('#gvn-apply-coupon');

            if (!code) {
                $message
                    .removeClass('gvn-coupon__message--success')
                    .addClass('gvn-coupon__message--error')
                    .text('Informe um código de cupom.');
                return;
            }

            $btn.prop('disabled', true).text('Aplicando...');

            $.ajax({
                url: gvn_checkout_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'gvn_apply_coupon',
                    nonce: gvn_checkout_params.nonce,
                    coupon_code: code
                },
                success: function (response) {
                    if (response.success) {
                        $message
                            .removeClass('gvn-coupon__message--error')
                            .addClass('gvn-coupon__message--success')
                            .text(response.data.message);
                        $('#gvn-coupon-code').val('');

                        $(document.body).trigger('update_checkout');
                    } else {
                        $message
                            .removeClass('gvn-coupon__message--success')
                            .addClass('gvn-coupon__message--error')
                            .text(response.data.message);
                    }
                },
                error: function () {
                    $message
                        .removeClass('gvn-coupon__message--success')
                        .addClass('gvn-coupon__message--error')
                        .text('Erro ao aplicar o cupom. Tente novamente.');
                },
                complete: function () {
                    $btn.prop('disabled', false).text('Aplicar');
                }
            });
        },

        removeCoupon: function (couponCode) {
            $.ajax({
                url: gvn_checkout_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'gvn_remove_coupon',
                    nonce: gvn_checkout_params.nonce,
                    coupon_code: couponCode
                },
                success: function () {
                    $(document.body).trigger('update_checkout');
                }
            });
        },

        /* ============================
           Gateways de Pagamento
           ============================ */

        bindGateways: function () {
            var self = this;

            $(document).on('click', '.gvn-gateway', function () {
                var $this = $(this);
                var gatewayId = $this.data('gateway');

                $('.gvn-gateway').removeClass('gvn-gateway--active');
                $this.addClass('gvn-gateway--active');

                $this.find('.gvn-gateway__radio').prop('checked', true).trigger('change');

                self.showGatewayFields(gatewayId);
            });
        },

        showFirstGatewayFields: function () {
            var $active = $('.gvn-gateway--active');
            if ($active.length) {
                this.showGatewayFields($active.data('gateway'));
            }
        },

        showGatewayFields: function (gatewayId) {
            $('.gvn-gateway-fields').hide();
            var $fields = $('#gvn-gateway-fields-' + gatewayId);
            if ($fields.length && $fields.children().length > 0) {
                $fields.show();
            }
        },

        /* ============================
           Order Bump
           ============================ */

        bindOrderBump: function () {
            var self = this;

            $('#gvn-bump-checkbox').on('change', function () {
                var checked = $(this).is(':checked');
                self.toggleOrderBump(checked ? 'add' : 'remove');
            });
        },

        toggleOrderBump: function (action) {
            var $checkbox = $('#gvn-bump-checkbox');
            $checkbox.prop('disabled', true);

            $.ajax({
                url: gvn_checkout_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'gvn_toggle_order_bump',
                    nonce: gvn_checkout_params.nonce,
                    bump_action: action
                },
                success: function (response) {
                    if (response.success) {
                        $(document.body).trigger('update_checkout');

                        if (response.data.items) {
                            $('#gvn-order-items').html(response.data.items);
                        }
                        if (response.data.subtotal) {
                            $('#gvn-subtotal').html(response.data.subtotal);
                        }
                        if (response.data.total) {
                            $('#gvn-total').html(response.data.total);
                        }
                    } else {
                        $checkbox.prop('checked', action !== 'add');
                    }
                },
                error: function () {
                    $checkbox.prop('checked', action !== 'add');
                },
                complete: function () {
                    $checkbox.prop('disabled', false);
                }
            });
        },

        /* ============================
           Campos Condicionais (E/OU)
           ============================ */

        bindConditionalFields: function () {
            var self = this;
            var $container = $('.gvn-fields-dynamic');
            if (!$container.length) return;

            var $conditionalFields = $container.find('.gvn-field--conditional');
            if (!$conditionalFields.length) return;

            // Avalia todas as condições ao mudar qualquer campo
            $container.on('input change', '.gvn-field__input', function () {
                self.evaluateAllConditions($container, $conditionalFields);
            });

            // Avalia no carregamento inicial
            this.evaluateAllConditions($container, $conditionalFields);
        },

        evaluateAllConditions: function ($container, $conditionalFields) {
            var self = this;

            $conditionalFields.each(function () {
                var $field = $(this);
                var conditions = $field.data('conditions');

                if (!conditions || !conditions.rules || conditions.rules.length === 0) return;

                var logic = conditions.logic || 'and';
                var rules = conditions.rules;
                var visible = self.evaluateRules(rules, logic, $container);

                if (visible) {
                    self.showConditionalField($field);
                } else {
                    self.hideConditionalField($field);
                }
            });
        },

        evaluateRules: function (rules, logic, $container) {
            var results = [];

            for (var i = 0; i < rules.length; i++) {
                var rule = rules[i];
                var fieldValue = this.getFieldValue(rule.field, $container);
                var result = this.evaluateRule(rule, fieldValue);
                results.push(result);
            }

            if (logic === 'or') {
                for (var j = 0; j < results.length; j++) {
                    if (results[j]) return true;
                }
                return false;
            }

            // AND (default)
            for (var k = 0; k < results.length; k++) {
                if (!results[k]) return false;
            }
            return true;
        },

        evaluateRule: function (rule, fieldValue) {
            var op = rule.operator;
            var compareValue = rule.value || '';

            switch (op) {
                case 'equals':
                    return fieldValue === compareValue;
                case 'not_equals':
                    return fieldValue !== compareValue;
                case 'filled':
                    return fieldValue !== '' && fieldValue !== null && fieldValue !== undefined;
                case 'empty':
                    return fieldValue === '' || fieldValue === null || fieldValue === undefined;
                case 'contains':
                    return String(fieldValue).toLowerCase().indexOf(compareValue.toLowerCase()) !== -1;
                case 'greater':
                    return parseFloat(fieldValue) > parseFloat(compareValue);
                case 'less':
                    return parseFloat(fieldValue) < parseFloat(compareValue);
                default:
                    return false;
            }
        },

        getFieldValue: function (fieldKey, $container) {
            var $wrapper = $container.find('.gvn-field[data-field-key="' + fieldKey + '"]');
            if (!$wrapper.length) return '';

            var $input = $wrapper.find('input, select, textarea').first();
            if (!$input.length) return '';

            if ($input.is(':checkbox')) {
                return $input.is(':checked') ? $input.val() : '';
            }

            return $input.val() || '';
        },

        showConditionalField: function ($field) {
            if ($field.hasClass('gvn-field--conditional-hidden')) {
                $field.removeClass('gvn-field--conditional-hidden');
                $field.slideDown(200);

                // Restaurar required se configurado
                if ($field.data('required') === 1 || $field.data('required') === '1') {
                    $field.find('input, select, textarea').first().prop('required', true);
                }
            }
        },

        hideConditionalField: function ($field) {
            if (!$field.hasClass('gvn-field--conditional-hidden')) {
                $field.addClass('gvn-field--conditional-hidden');
                $field.slideUp(200);

                // Remover required e limpar valor
                var $input = $field.find('input, select, textarea').first();
                $input.prop('required', false);
            }
        },

        /* ============================
           Máscaras de Input (dinâmicas)
           ============================ */

        bindMasks: function () {
            var masks = {
                cpf: function (val) {
                    val = val.replace(/\D/g, '').substring(0, 11);
                    if (val.length > 9) return val.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
                    if (val.length > 6) return val.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
                    if (val.length > 3) return val.replace(/(\d{3})(\d{1,3})/, '$1.$2');
                    return val;
                },
                cnpj: function (val) {
                    val = val.replace(/\D/g, '').substring(0, 14);
                    if (val.length > 12) return val.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{1,2})/, '$1.$2.$3/$4-$5');
                    if (val.length > 8) return val.replace(/(\d{2})(\d{3})(\d{3})(\d{1,4})/, '$1.$2.$3/$4');
                    if (val.length > 5) return val.replace(/(\d{2})(\d{3})(\d{1,3})/, '$1.$2.$3');
                    if (val.length > 2) return val.replace(/(\d{2})(\d{1,3})/, '$1.$2');
                    return val;
                },
                phone: function (val) {
                    val = val.replace(/\D/g, '').substring(0, 11);
                    if (val.length > 6) return val.replace(/(\d{2})(\d{5})(\d{1,4})/, '($1) $2-$3');
                    if (val.length > 2) return val.replace(/(\d{2})(\d{1,5})/, '($1) $2');
                    return val;
                },
                cep: function (val) {
                    val = val.replace(/\D/g, '').substring(0, 8);
                    if (val.length > 5) return val.replace(/(\d{5})(\d{1,3})/, '$1-$2');
                    return val;
                },
                date: function (val) {
                    val = val.replace(/\D/g, '').substring(0, 8);
                    if (val.length > 4) return val.replace(/(\d{2})(\d{2})(\d{1,4})/, '$1/$2/$3');
                    if (val.length > 2) return val.replace(/(\d{2})(\d{1,2})/, '$1/$2');
                    return val;
                },
                rg: function (val) {
                    val = val.replace(/\D/g, '').substring(0, 9);
                    if (val.length > 8) return val.replace(/(\d{2})(\d{3})(\d{3})(\d{1})/, '$1.$2.$3-$4');
                    if (val.length > 5) return val.replace(/(\d{2})(\d{3})(\d{1,3})/, '$1.$2.$3');
                    if (val.length > 2) return val.replace(/(\d{2})(\d{1,3})/, '$1.$2');
                    return val;
                }
            };

            $(document).on('input', '.gvn-field[data-mask] input, .gvn-field[data-mask] textarea', function () {
                var maskType = $(this).closest('.gvn-field').data('mask');
                if (maskType && masks[maskType]) {
                    $(this).val(masks[maskType]($(this).val()));
                }
            });
        }
    };

    $(document).ready(function () {
        GVNCheckout.init();
    });

    $(document.body).on('updated_checkout', function () {
        GVNCheckout.showFirstGatewayFields();
    });

})(jQuery);
