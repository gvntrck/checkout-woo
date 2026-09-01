/**
 * GVN Checkout - JavaScript
 * @version 1.13.25
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
            this.bindPersonTypeDependencies();
            this.bindViaCEP();
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

            // Avalia todas as condições ao mudar qualquer campo.
            // Reavalia em cascata (até 3 níveis) para suportar condições
            // que dependem de outros campos condicionais.
            $container.on('input change', '.gvn-field__input', function () {
                self.evaluateAllConditions($container, $conditionalFields);
                // Segunda passagem para propagar mudanças de visibilidade.
                self.evaluateAllConditions($container, $conditionalFields);
            });

            // Avalia no carregamento inicial.
            this.evaluateAllConditions($container, $conditionalFields);
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

                // Remover required e limpar valor para não ser enviado no submit.
                var $input = $field.find('input, select, textarea').first();
                $input.prop('required', false);
                if ($input.is(':checkbox') || $input.is(':radio')) {
                    $input.prop('checked', false);
                } else {
                    $input.val('');
                }
                $input.trigger('change');
            }
        },

        /* ============================
           Dependências de Tipo de Pessoa
           (Campos Padrão com depends_on)
           ============================ */

        bindPersonTypeDependencies: function () {
            var self = this;
            var $container = $('.gvn-fields-dynamic');
            if (!$container.length) return;

            var $dependentFields = $container.find('.gvn-field[data-depends-on-field]');
            if (!$dependentFields.length) return;

            // Coleta todos os campos trigger únicos
            var triggerFields = {};
            $dependentFields.each(function () {
                var triggerKey = $(this).data('depends-on-field');
                if (!triggerFields[triggerKey]) {
                    triggerFields[triggerKey] = [];
                }
                triggerFields[triggerKey].push($(this));
            });

            // Para cada campo trigger, escuta mudanças
            $.each(triggerFields, function (triggerKey) {
                var $triggerWrapper = $container.find('.gvn-field[data-field-key="' + triggerKey + '"]');
                if (!$triggerWrapper.length) return;

                var $triggerInput = $triggerWrapper.find('select, input').first();
                if (!$triggerInput.length) return;

                $triggerInput.on('change input', function () {
                    self.evaluatePersonTypeDeps($container, triggerKey, $(this).val());
                });

                // Avaliar no carregamento inicial
                self.evaluatePersonTypeDeps($container, triggerKey, $triggerInput.val());
            });
        },

        evaluatePersonTypeDeps: function ($container, triggerKey, triggerValue) {
            var $dependents = $container.find('.gvn-field[data-depends-on-field="' + triggerKey + '"]');

            $dependents.each(function () {
                var $field = $(this);
                var requiredValue = $field.data('depends-on-value');
                var origRequired = $field.data('orig-required') === 1 || $field.data('orig-required') === '1';
                var $input = $field.find('input, select, textarea').first();

                if (String(triggerValue) === String(requiredValue)) {
                    // Mostrar
                    $field.slideDown(200);
                    if (origRequired) {
                        $input.prop('required', true);
                    }
                } else {
                    // Ocultar
                    $field.slideUp(200);
                    $input.prop('required', false).val('');
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
        },

        /* ============================
           ViaCEP - Preenchimento automático
           (v1.13.0 — proxy server-side,
            normalização, lock e consistência)
           ============================ */

        _lastCep: '',
        _cepRetries: 0,
        _maxRetries: 1,
        _addressLockedFields: [],

        bindViaCEP: function () {
            var self = this;

            $(document).on('input', '.gvn-field[data-mask="cep"] input, #billing_postcode', function () {
                var raw = $(this).val().replace(/\D/g, '');

                if (raw.length === 8 && raw !== self._lastCep) {
                    self._lastCep = raw;
                    self._cepRetries = 0;
                    self.fetchCEP(raw, $(this));
                }

                // Limpar locks se CEP foi apagado ou alterado para menos de 8 dígitos.
                if (raw.length < 8) {
                    self.unlockAddressFields();
                    // Permite reconsultar o mesmo CEP após apagar.
                    self._lastCep = '';
                }
            });

            // Permitir desbloquear campos clicando no ícone de edição
            $(document).on('click', '.gvn-field__unlock-btn', function (e) {
                e.preventDefault();
                var fieldKey = $(this).closest('.gvn-field').data('field-key');
                self.unlockField(fieldKey);
            });
        },

        /**
         * Consulta CEP via proxy server-side (com cache no backend).
         */
        fetchCEP: function (cep, $input) {
            var self = this;
            var $field = $input.closest('.gvn-field');

            $field.addClass('gvn-field--loading');
            self.removeViaCEPMessage($field);

            $.ajax({
                url: gvn_checkout_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'gvn_cep_lookup',
                    nonce: gvn_checkout_params.nonce,
                    cep: cep
                },
                timeout: 12000,
                success: function (response) {
                    if (response.success) {
                        self.fillAddressFromCEP(response.data, $field);
                    } else {
                        var msg = response.data && response.data.message ? response.data.message : 'CEP não encontrado.';
                        self.showViaCEPMessage($field, msg, 'error');
                    }
                },
                error: function () {
                    // Retry uma vez em caso de falha de rede
                    if (self._cepRetries < self._maxRetries) {
                        self._cepRetries++;
                        setTimeout(function () {
                            self.fetchCEP(cep, $input);
                        }, 1500);
                        return;
                    }
                    self.showViaCEPMessage($field, 'Erro ao consultar o CEP. Tente novamente.', 'error');
                },
                complete: function () {
                    $field.removeClass('gvn-field--loading');
                }
            });
        },

        /**
         * Preenche os campos de endereço com dados normalizados do servidor.
         */
        fillAddressFromCEP: function (data, $cepField) {
            var self = this;

            var mapping = {
                'billing_address_1':    data.logradouro || '',
                'billing_neighborhood': data.bairro || '',
                'billing_city':         data.cidade || '',
                'billing_state':        data.uf || ''
            };

            var filled = 0;
            var fieldsToLock = [];

            $.each(mapping, function (fieldKey, value) {
                if (value && self.setCheckoutFieldValue(fieldKey, value)) {
                    filled++;
                    fieldsToLock.push(fieldKey);
                }
            });

            // Lock campos preenchidos automaticamente (UF, cidade, bairro)
            var lockableFields = ['billing_city', 'billing_state', 'billing_neighborhood'];
            for (var i = 0; i < lockableFields.length; i++) {
                if (fieldsToLock.indexOf(lockableFields[i]) !== -1) {
                    self.lockField(lockableFields[i]);
                }
            }

            if (filled > 0) {
                // Mensagem de sucesso
                var successMsg = 'Endereço preenchido automaticamente.';

                // Alerta de inconsistência CEP ↔ UF
                if (data.consistente === false && data.uf_esperada) {
                    successMsg += ' ⚠ A UF retornada (' + data.uf + ') difere da esperada (' + data.uf_esperada + ') para este CEP.';
                    self.showViaCEPMessage($cepField, successMsg, 'warning');
                } else {
                    self.showViaCEPMessage($cepField, successMsg, 'success');
                }

                // Foco no campo "número" se existir
                self.focusFieldAfterCEP();
            }
        },

        /**
         * Foca no campo billing_number após preenchimento automático.
         */
        focusFieldAfterCEP: function () {
            var focusTargets = ['billing_number', 'billing_address_2'];
            for (var i = 0; i < focusTargets.length; i++) {
                var $wrapper = $('.gvn-fields-dynamic .gvn-field[data-field-key="' + focusTargets[i] + '"]');
                if ($wrapper.length && $wrapper.is(':visible')) {
                    var $input = $wrapper.find('input, textarea').first();
                    if ($input.length) {
                        setTimeout(function () { $input.focus(); }, 300);
                        return;
                    }
                }
            }
        },

        /**
         * Trava um campo de endereço preenchido via CEP (readonly + botão de unlock).
         */
        lockField: function (fieldKey) {
            var $wrapper = $('.gvn-fields-dynamic .gvn-field[data-field-key="' + fieldKey + '"]');
            if (!$wrapper.length) return;

            var $input = $wrapper.find('input, select, textarea').first();
            if (!$input.length) return;

            $input.prop('readonly', true);
            $wrapper.addClass('gvn-field--cep-locked');

            // Adicionar botão de destravamento se ainda não existir
            if (!$wrapper.find('.gvn-field__unlock-btn').length) {
                var $btn = $('<button type="button" class="gvn-field__unlock-btn" title="Editar manualmente">✎</button>');
                $wrapper.find('.gvn-field__label').append($btn);
            }

            if (this._addressLockedFields.indexOf(fieldKey) === -1) {
                this._addressLockedFields.push(fieldKey);
            }
        },

        /**
         * Destrava um campo específico.
         */
        unlockField: function (fieldKey) {
            var $wrapper = $('.gvn-fields-dynamic .gvn-field[data-field-key="' + fieldKey + '"]');
            if (!$wrapper.length) return;

            var $input = $wrapper.find('input, select, textarea').first();
            $input.prop('readonly', false);
            $wrapper.removeClass('gvn-field--cep-locked');
            $wrapper.find('.gvn-field__unlock-btn').remove();

            var idx = this._addressLockedFields.indexOf(fieldKey);
            if (idx !== -1) {
                this._addressLockedFields.splice(idx, 1);
            }
        },

        /**
         * Destrava todos os campos de endereço.
         */
        unlockAddressFields: function () {
            var self = this;
            var fields = self._addressLockedFields.slice();
            for (var i = 0; i < fields.length; i++) {
                self.unlockField(fields[i]);
            }
        },

        /**
         * Define o valor de um campo do checkout (visível ou hidden).
         * Retorna true se o campo foi encontrado e preenchido.
         */
        setCheckoutFieldValue: function (fieldKey, value) {
            // Tentar campo dinâmico (renderizado pelo template)
            var $wrapper = $('.gvn-fields-dynamic .gvn-field[data-field-key="' + fieldKey + '"]');
            if ($wrapper.length) {
                var $input = $wrapper.find('input, select, textarea').first();
                if ($input.length) {
                    // Destravar temporariamente se estiver locked
                    var wasReadonly = $input.prop('readonly');
                    if (wasReadonly) $input.prop('readonly', false);
                    $input.val(value).trigger('change');
                    if (wasReadonly) $input.prop('readonly', true);
                    return true;
                }
            }

            // Tentar campo hidden ou nativo do WooCommerce
            var $hidden = $('input[name="' + fieldKey + '"]');
            if ($hidden.length) {
                $hidden.val(value).trigger('change');
                return true;
            }

            // Tentar por ID
            var $byId = $('#' + fieldKey);
            if ($byId.length) {
                $byId.val(value).trigger('change');
                return true;
            }

            return false;
        },

        showViaCEPMessage: function ($field, message, type) {
            this.removeViaCEPMessage($field);
            var cssClass = 'gvn-viacep-msg--success';
            if (type === 'error') cssClass = 'gvn-viacep-msg--error';
            if (type === 'warning') cssClass = 'gvn-viacep-msg--warning';

            // Cria o container e injeta a mensagem como texto (não HTML) para evitar XSS.
            var $msg = $('<div class="gvn-viacep-msg ' + cssClass + '"></div>').hide();
            $msg.text(message);
            $field.append($msg);
            $msg.slideDown(150);

            if (type === 'success') {
                setTimeout(function () {
                    $msg.slideUp(150, function () { $(this).remove(); });
                }, 4000);
            }
            if (type === 'warning') {
                setTimeout(function () {
                    $msg.slideUp(150, function () { $(this).remove(); });
                }, 8000);
            }
        },

        removeViaCEPMessage: function ($field) {
            $field.find('.gvn-viacep-msg').remove();
        }
    };

    $(document).ready(function () {
        GVNCheckout.init();
    });

    $(document.body).on('updated_checkout', function () {
        GVNCheckout.showFirstGatewayFields();
    });

})(jQuery);
