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
