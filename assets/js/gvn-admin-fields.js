/**
 * GVN Checkout - Admin Fields Manager
 * Drag-and-drop, CRUD, largura e ordenação de campos.
 * @version 1.13.9
 */

(function ($) {
    'use strict';

    /* ===================================================
     *  Gerenciador de Campos (Fields Tab)
     * =================================================== */
    var GVNAdminFields = {

        init: function () {
            this.$container = $('#gvn-fields-manager');
            if (!this.$container.length) return;

            this.$list = $('#gvn-fields-list');
            this.$addBtn = $('#gvn-add-field');
            this.$status = $('#gvn-fields-status');

            this.bindEvents();
            this.initSortable();
            this.interceptWooFormSubmit();
        },

        bindEvents: function () {
            var self = this;

            this.$addBtn.on('click', function () {
                self.addField();
            });

            $('#gvn-import-woo-fields').on('click', function() {
                self.importWooFields();
            });

            $('#gvn-import-br-fields').on('click', function() {
                self.importBrazilianFields();
            });

            this.$list.on('click', '.gvn-field-remove', function () {
                var $row = $(this).closest('.gvn-field-row');
                if ($row.data('default') === true || $row.data('default') === 'true') {
                    if (!confirm('Este é um campo padrão. Deseja realmente removê-lo?')) return;
                }
                $row.slideUp(200, function () { $(this).remove(); self.updatePositions(); });
            });

            this.$list.on('click', '.gvn-field-toggle', function () {
                var $row = $(this).closest('.gvn-field-row');
                var $body = $row.find('.gvn-field-row__body');
                var $icon = $(this).find('.gvn-toggle-icon');
                $body.slideToggle(200);
                $icon.toggleClass('gvn-toggle-icon--open');
            });

            this.$list.on('change', '.gvn-field-enabled', function () {
                var $row = $(this).closest('.gvn-field-row');
                $row.toggleClass('gvn-field-row--disabled', !this.checked);

                // Automação: Tipo de Pessoa → CPF/CNPJ
                var fieldKey = $row.attr('data-key');
                if (fieldKey === 'billing_persontype') {
                    self.handlePersonTypeToggle(this.checked);
                }
            });

            this.$list.on('change', '.gvn-field-type-select', function () {
                var $row = $(this).closest('.gvn-field-row');
                var $optionsCol = $row.find('.gvn-field-col--options');
                if ($(this).val() === 'select') {
                    $optionsCol.slideDown(200);
                } else {
                    $optionsCol.slideUp(200);
                }
            });

            // Atualiza o select de valor padrão quando o admin edita as opções
            this.$list.on('input change', '.gvn-field-options-input', function () {
                var $row = $(this).closest('.gvn-field-row');
                var $defaultSelect = $row.find('.gvn-field-default-option-select');
                var currentVal = $defaultSelect.val();
                $defaultSelect.html(self.getDefaultOptionSelect($(this).val(), currentVal));
            });

            // Condições: adicionar regra
            this.$list.on('click', '.gvn-add-condition', function () {
                var $row = $(this).closest('.gvn-field-row');
                var $rules = $row.find('.gvn-conditions-rules');
                var $logic = $row.find('.gvn-conditions-logic');
                var currentKey = $row.find('.gvn-field-key-input').val();
                var fieldOptions = self.getFieldOptionsForConditions(currentKey);
                var operatorOptions = self.getOperatorOptions('');
                var html = '<div class="gvn-condition-rule">' +
                    '<select class="gvn-rule-field"><option value="">-- Campo --</option>' + fieldOptions + '</select>' +
                    '<select class="gvn-rule-operator">' + operatorOptions + '</select>' +
                    '<input type="text" class="gvn-rule-value" placeholder="Valor" />' +
                    '<button type="button" class="gvn-rule-remove button-link" title="Remover condição">✕</button>' +
                    '</div>';
                var $rule = $(html).hide();
                $rules.append($rule);
                $rule.slideDown(150);
                $logic.slideDown(150);
            });

            // Condições: remover regra
            this.$list.on('click', '.gvn-rule-remove', function () {
                var $rule = $(this).closest('.gvn-condition-rule');
                var $section = $rule.closest('.gvn-conditions-section');
                $rule.slideUp(150, function () {
                    $(this).remove();
                    if ($section.find('.gvn-condition-rule').length === 0) {
                        $section.find('.gvn-conditions-logic').slideUp(150);
                    }
                });
            });

            // Condições: show/hide valor conforme operador
            this.$list.on('change', '.gvn-rule-operator', function () {
                var $value = $(this).closest('.gvn-condition-rule').find('.gvn-rule-value');
                var op = $(this).val();
                if (op === 'filled' || op === 'empty') {
                    $value.hide().val('');
                } else {
                    $value.show();
                }
            });
        },

        initSortable: function () {
            var self = this;
            this.$list.sortable({
                handle: '.gvn-field-drag',
                axis: 'y',
                opacity: 0.7,
                placeholder: 'gvn-field-placeholder',
                update: function () {
                    self.updatePositions();
                }
            });
        },

        updatePositions: function () {
            this.$list.find('.gvn-field-row').each(function (index) {
                $(this).find('.gvn-field-position').val(index + 1);
                $(this).find('.gvn-field-pos-label').text('#' + (index + 1));
            });
        },

        addField: function () {
            var timestamp = Date.now();
            var key = 'gvn_custom_' + timestamp;

            var html = this.buildFieldRow({
                key: key,
                label: '',
                type: 'text',
                required: false,
                width: '100',
                position: this.$list.find('.gvn-field-row').length + 1,
                placeholder: '',
                enabled: true,
                mask: '',
                is_default: false,
                options: '',
                conditions: { logic: 'and', rules: [] }
            });

            var $row = $(html).hide();
            this.$list.append($row);
            $row.slideDown(200);
            $row.find('.gvn-field-row__body').show();
            $row.find('.gvn-field-label-input').focus();
            this.updatePositions();
        },

        buildFieldRow: function (field) {
            var types = this.getTypeOptions(field.type);
            var widths = this.getWidthOptions(field.width);
            var masks = this.getMaskOptions(field.mask);
            var pos = field.position || 1;
            var isDefault = field.is_default ? 'true' : 'false';
            var isWooDefault = field.is_woo_default ? 'true' : 'false';
            var enabledChecked = field.enabled ? 'checked' : '';
            var requiredChecked = field.required ? 'checked' : '';
            var disabledClass = field.enabled ? '' : ' gvn-field-row--disabled';
            var keyReadonly = field.is_default ? 'readonly' : '';
            var labelDisplay = field.label || '(novo campo)';

            return '' +
                '<div class="gvn-field-row' + disabledClass + '" data-key="' + this.escAttr(field.key) + '" data-default="' + isDefault + '" data-woo-default="' + isWooDefault + '">' +
                '  <div class="gvn-field-row__header">' +
                '    <span class="gvn-field-drag" title="Arrastar para reordenar">☰</span>' +
                '    <span class="gvn-field-pos-label">#' + pos + '</span>' +
                '    <span class="gvn-field-label-display">' + this.escHtml(labelDisplay) + '</span>' +
                (isWooDefault === 'true' ? '    <span class="gvn-field-badge gvn-field-badge--woo">Padrão Woo</span>' : '') +
                '    <span class="gvn-field-width-badge">' + this.escHtml(field.width) + '%</span>' +
                '    <span class="gvn-field-row__actions">' +
                '      <label class="gvn-field-enabled-label"><input type="checkbox" class="gvn-field-enabled" ' + enabledChecked + ' /> Ativo</label>' +
                '      <button type="button" class="gvn-field-toggle button-link"><span class="gvn-toggle-icon">▼</span></button>' +
                '      <button type="button" class="gvn-field-remove button-link" title="Remover">✕</button>' +
                '    </span>' +
                '  </div>' +
                '  <div class="gvn-field-row__body" style="display:none;">' +
                '    <input type="hidden" class="gvn-field-position" value="' + pos + '" />' +
                '    <input type="hidden" class="gvn-field-is-default" value="' + isDefault + '" />' +
                '    <input type="hidden" class="gvn-field-is-woo-default" value="' + isWooDefault + '" />' +
                '    <div class="gvn-field-grid">' +
                '      <div class="gvn-field-col">' +
                '        <label>Chave (key)</label>' +
                '        <input type="text" class="gvn-field-key-input" value="' + this.escAttr(field.key) + '" ' + keyReadonly + ' />' +
                '      </div>' +
                '      <div class="gvn-field-col">' +
                '        <label>Label</label>' +
                '        <input type="text" class="gvn-field-label-input" value="' + this.escAttr(field.label) + '" />' +
                '      </div>' +
                '      <div class="gvn-field-col">' +
                '        <label>Tipo</label>' +
                '        <select class="gvn-field-type-select">' + types + '</select>' +
                '      </div>' +
                '      <div class="gvn-field-col">' +
                '        <label>Largura</label>' +
                '        <select class="gvn-field-width-select">' + widths + '</select>' +
                '      </div>' +
                '      <div class="gvn-field-col">' +
                '        <label>Máscara</label>' +
                '        <select class="gvn-field-mask-select">' + masks + '</select>' +
                '      </div>' +
                '      <div class="gvn-field-col">' +
                '        <label>Placeholder</label>' +
                '        <input type="text" class="gvn-field-placeholder-input" value="' + this.escAttr(field.placeholder) + '" />' +
                '      </div>' +
                '      <div class="gvn-field-col gvn-field-col--options" style="' + (field.type !== 'select' ? 'display:none;' : '') + 'grid-column: 1 / -1;">' +
                '        <label>Opções (uma por linha, formato: <code>valor|Rótulo</code> ou apenas <code>Rótulo</code>)</label>' +
                '        <textarea class="gvn-field-options-input" rows="4" placeholder="opcao1|Opção 1&#10;opcao2|Opção 2">' + this.escHtml(field.options || '') + '</textarea>' +
                '        <label style="margin-top:8px;display:block;">Valor padrão pré-selecionado</label>' +
                '        <select class="gvn-field-default-option-select">' + this.getDefaultOptionSelect(field.options || '', field.default_option || '') + '</select>' +
                '      </div>' +
                '      <div class="gvn-field-col">' +
                '        <label><input type="checkbox" class="gvn-field-required" ' + requiredChecked + ' /> Obrigatório</label>' +
                '      </div>' +
                '    </div>' +
                this.buildConditionsSection(field) +
                '  </div>' +
                '</div>';
        },

        getTypeOptions: function (selected) {
            var types = {
                'text': 'Texto', 'email': 'E-mail', 'tel': 'Telefone',
                'number': 'Número', 'textarea': 'Área de texto',
                'select': 'Seleção', 'date': 'Data', 'password': 'Senha'
            };
            var html = '';
            for (var k in types) {
                html += '<option value="' + k + '"' + (k === selected ? ' selected' : '') + '>' + types[k] + '</option>';
            }
            return html;
        },

        getWidthOptions: function (selected) {
            var widths = { '25': '25%', '33': '33%', '50': '50%', '75': '75%', '100': '100%' };
            var html = '';
            for (var k in widths) {
                html += '<option value="' + k + '"' + (k === selected ? ' selected' : '') + '>' + widths[k] + '</option>';
            }
            return html;
        },

        getMaskOptions: function (selected) {
            var masks = {
                '': 'Nenhuma', 'cpf': 'CPF', 'cnpj': 'CNPJ',
                'phone': 'Celular', 'cep': 'CEP', 'date': 'Data', 'rg': 'RG'
            };
            var html = '';
            for (var k in masks) {
                html += '<option value="' + k + '"' + (k === selected ? ' selected' : '') + '>' + masks[k] + '</option>';
            }
            return html;
        },

        saveFields: function (callback) {
            var self = this;
            var fields = [];

            this.$list.find('.gvn-field-row').each(function (index) {
                var $row = $(this);
                fields.push({
                    key: $row.find('.gvn-field-key-input').val(),
                    label: $row.find('.gvn-field-label-input').val(),
                    type: $row.find('.gvn-field-type-select').val(),
                    required: $row.find('.gvn-field-required').is(':checked'),
                    width: $row.find('.gvn-field-width-select').val(),
                    position: index + 1,
                    placeholder: $row.find('.gvn-field-placeholder-input').val(),
                    enabled: $row.find('.gvn-field-enabled').is(':checked'),
                    mask: $row.find('.gvn-field-mask-select').val(),
                    is_default: $row.find('.gvn-field-is-default').val() === 'true',
                    is_woo_default: $row.find('.gvn-field-is-woo-default').val() === 'true',
                    options: $row.find('.gvn-field-options-input').val() || '',
                    default_option: $row.find('.gvn-field-default-option-select').val() || '',
                    conditions: self.collectConditions($row)
                });
            });

            $.ajax({
                url: gvn_admin_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'gvn_save_fields',
                    nonce: gvn_admin_params.nonce,
                    fields: JSON.stringify(fields)
                },
                success: function (response) {
                    if (response.success) {
                        self.$status.html('<span style="color:#16a34a;">✓ ' + response.data.message + '</span>').show().delay(3000).fadeOut();
                        if (typeof callback === 'function') {
                            callback(true);
                        }
                    } else {
                        self.$status.html('<span style="color:#dc2626;">✕ ' + response.data.message + '</span>').show();
                        if (typeof callback === 'function') {
                            callback(false);
                        }
                    }
                },
                error: function () {
                    self.$status.html('<span style="color:#dc2626;">Erro ao salvar.</span>').show();
                    if (typeof callback === 'function') {
                        callback(false);
                    }
                }
            });
        },

        /**
         * Intercepta o submit do formulário do WooCommerce para salvar campos via AJAX.
         */
        interceptWooFormSubmit: function () {
            var self = this;
            var $form = this.$container.closest('form');
            if (!$form.length) return;

            $form.on('submit', function (e) {
                // Se já estamos no processo de submit após o AJAX, deixa prosseguir
                if (self._submitting) {
                    self._submitting = false; // reset para próximo submit
                    return true;
                }

                e.preventDefault();

                var $submitBtn = $form.find('.woocommerce-save-button');
                $submitBtn.prop('disabled', true).val('Salvando...');

                self.saveFields(function (success) {
                    // Só prossegue com o submit do form Woo se o save dos campos foi bem-sucedido.
                    if (!success) {
                        $submitBtn.prop('disabled', false).val('Salvar alterações');
                        return;
                    }
                    self._submitting = true;
                    // Reabilita o botão antes do re-submit (o WC pode re-validar).
                    $submitBtn.prop('disabled', false);
                    $form.submit();
                });

                return false;
            });
        },

        importWooFields: function() {
            var self = this;
            var wooFields = [
                { key: 'billing_address_1', label: 'Endereço 1 (Logradouro)', placeholder: 'Nome da rua', width: '75', type: 'text', required: true },
                { key: 'billing_address_2', label: 'Endereço 2 (Complemento)', placeholder: 'Apartamento, suite, unidade, etc. (opcional)', width: '25', type: 'text', required: false },
                { key: 'billing_city', label: 'Cidade', placeholder: 'Cidade', width: '50', type: 'text', required: true },
                { key: 'billing_state', label: 'Estado', placeholder: 'Estado / Província', width: '50', type: 'text', required: true },
                { key: 'billing_postcode', label: 'CEP', placeholder: '00000-000', width: '50', type: 'text', required: true, mask: 'cep' },
                { key: 'billing_country', label: 'País', placeholder: 'País / Região', width: '100', type: 'text', required: true },
                { key: 'billing_company', label: 'Empresa', placeholder: 'Nome da empresa (opcional)', width: '100', type: 'text', required: false }
            ];

            var added = this._importFieldList(wooFields, true);

            if (added > 0) {
                alert(added + ' campo(s) nativo(s) importado(s) e adicionado(s) ao final da lista. Clique no botão de configurações para ajustá-los e não esqueça de Salvar.');
            } else {
                alert('Todos os campos nativos do WooCommerce já estão na lista.');
            }
        },

        importBrazilianFields: function() {
            var self = this;
            var brFields = [
                { key: 'billing_persontype', label: 'Tipo de Pessoa', placeholder: '', width: '100', type: 'select', required: false, mask: '', options: 'pf|Pessoa Física\npj|Pessoa Jurídica' },
                { key: 'billing_cpf', label: 'CPF', placeholder: '000.000.000-00', width: '100', type: 'text', required: true, mask: 'cpf' },
                { key: 'billing_rg', label: 'RG', placeholder: '', width: '50', type: 'text', required: false, mask: 'rg' },
                { key: 'billing_cnpj', label: 'CNPJ', placeholder: '00.000.000/0000-00', width: '100', type: 'text', required: false, mask: 'cnpj' },
                { key: 'billing_ie', label: 'Inscrição Estadual', placeholder: '', width: '50', type: 'text', required: false },
                { key: 'billing_birthdate', label: 'Data de Nascimento', placeholder: 'DD/MM/AAAA', width: '50', type: 'text', required: false, mask: 'date' },
                { key: 'billing_gender', label: 'Gênero', placeholder: '', width: '50', type: 'select', required: false, options: 'prefiro_nao_dizer|Prefiro não dizer\nfeminino|Feminino\nmasculino|Masculino\noutro|Outro' },
                { key: 'billing_number', label: 'Número', placeholder: '', width: '25', type: 'text', required: true },
                { key: 'billing_neighborhood', label: 'Bairro', placeholder: '', width: '50', type: 'text', required: false },
                { key: 'billing_cellphone', label: 'Celular (Adicional)', placeholder: '(00) 00000-0000', width: '50', type: 'tel', required: false, mask: 'phone' },
                { key: 'shipping_number', label: 'Número (Entrega)', placeholder: '', width: '25', type: 'text', required: true },
                { key: 'shipping_neighborhood', label: 'Bairro (Entrega)', placeholder: '', width: '50', type: 'text', required: false }
            ];

            var added = this._importFieldList(brFields, false);

            if (added > 0) {
                alert(added + ' campo(s) brasileiro(s) importado(s) e adicionado(s) ao final da lista. Ajuste as configurações e não esqueça de Salvar.');
            } else {
                alert('Todos os campos brasileiros já estão na lista.');
            }
        },

        /**
         * Importa uma lista de campos, retornando quantos foram adicionados.
         */
        _importFieldList: function(fieldList, isWooDefault) {
            var self = this;

            // Coletar keys existentes
            var existingKeys = {};
            self.$list.find('.gvn-field-row').each(function() {
                var k = $(this).attr('data-key');
                if (k) existingKeys[k] = true;
            });

            var added = 0;
            $.each(fieldList, function(i, wField) {
                if (existingKeys[wField.key]) return; // pula se já existe

                var fieldData = {
                    key: wField.key,
                    label: wField.label,
                    type: wField.type,
                    required: wField.required,
                    width: wField.width,
                    position: self.$list.children().length + 1,
                    placeholder: wField.placeholder || '',
                    enabled: true,
                    mask: wField.mask || '',
                    is_default: !isWooDefault,
                    is_woo_default: isWooDefault,
                    options: wField.options || '',
                    default_option: '',
                    conditions: { logic: 'and', rules: [] }
                };

                var fieldHtml = self.buildFieldRow(fieldData);
                self.$list.append(fieldHtml);
                added++;
            });

            if (added > 0) {
                this.updatePositions();
            }

            return added;
        },

        escHtml: function (str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        },

        escAttr: function (str) {
            return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        },

        buildConditionsSection: function (field) {
            var conditions = field.conditions || { logic: 'and', rules: [] };
            var rules = conditions.rules || [];
            var logic = conditions.logic || 'and';
            var currentKey = field.key;
            var logicDisplay = rules.length === 0 ? 'display:none;' : '';

            var html = '<div class="gvn-conditions-section">' +
                '<div class="gvn-conditions-header">' +
                '  <strong>Condições de exibição</strong>' +
                '  <span class="gvn-conditions-hint">Deixe vazio para sempre exibir</span>' +
                '</div>' +
                '<div class="gvn-conditions-logic" style="' + logicDisplay + '">' +
                '  <label>Quando</label>' +
                '  <select class="gvn-conditions-logic-select">' +
                '    <option value="and"' + (logic === 'and' ? ' selected' : '') + '>TODAS as condições forem verdadeiras (E)</option>' +
                '    <option value="or"' + (logic === 'or' ? ' selected' : '') + '>QUALQUER condição for verdadeira (OU)</option>' +
                '  </select>' +
                '</div>' +
                '<div class="gvn-conditions-rules">';

            for (var i = 0; i < rules.length; i++) {
                var r = rules[i];
                var opOptions = this.getOperatorOptions(r.operator);
                var valueHidden = (r.operator === 'filled' || r.operator === 'empty') ? ' style="display:none;"' : '';
                html += '<div class="gvn-condition-rule">' +
                    '<select class="gvn-rule-field"><option value="">-- Campo --</option>' + this.getFieldOptionsForConditions(currentKey, r.field) + '</select>' +
                    '<select class="gvn-rule-operator">' + opOptions + '</select>' +
                    '<input type="text" class="gvn-rule-value" value="' + this.escAttr(r.value || '') + '" placeholder="Valor"' + valueHidden + ' />' +
                    '<button type="button" class="gvn-rule-remove button-link" title="Remover condição">✕</button>' +
                    '</div>';
            }

            html += '</div>' +
                '<button type="button" class="gvn-add-condition button-link">+ Adicionar condição</button>' +
                '</div>';

            return html;
        },

        getFieldOptionsForConditions: function (excludeKey, selectedKey) {
            var html = '';
            var self = this;

            // Todos os campos da lista (unificados)
            this.$list.find('.gvn-field-row').each(function () {
                var key = $(this).find('.gvn-field-key-input').val();
                var label = $(this).find('.gvn-field-label-input').val() || key;
                if (key && key !== excludeKey) {
                    html += '<option value="' + self.escAttr(key) + '"' + (key === selectedKey ? ' selected' : '') + '>' + self.escHtml(label) + '</option>';
                }
            });

            return html;
        },

        /**
         * Gera as options HTML do select de valor padrão.
         */
        getDefaultOptionSelect: function (optionsText, selected) {
            var html = '<option value=""' + ('' === selected ? ' selected' : '') + '>-- Nenhuma opção pré-selecionada --</option>';
            var lines = (optionsText || '').split('\n');
            for (var i = 0; i < lines.length; i++) {
                var line = lines[i].trim();
                if (!line) continue;
                var value, label;
                if (line.indexOf('|') !== -1) {
                    var parts = line.split('|');
                    value = parts[0].trim();
                    label = parts.slice(1).join('|').trim();
                } else {
                    value = line.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9\-_]/g, '');
                    label = line;
                }
                if (value && label) {
                    html += '<option value="' + this.escAttr(value) + '"' + (value === selected ? ' selected' : '') + '>' + this.escHtml(label) + '</option>';
                }
            }
            return html;
        },

        getOperatorOptions: function (selected) {
            var ops = {
                'equals': 'Igual a',
                'not_equals': 'Diferente de',
                'filled': 'Preenchido',
                'empty': 'Vazio',
                'contains': 'Contém',
                'greater': 'Maior que',
                'less': 'Menor que'
            };
            var html = '';
            for (var k in ops) {
                html += '<option value="' + k + '"' + (k === selected ? ' selected' : '') + '>' + ops[k] + '</option>';
            }
            return html;
        },

        collectConditions: function ($row) {
            var conditions = {
                logic: $row.find('.gvn-conditions-logic-select').val() || 'and',
                rules: []
            };
            $row.find('.gvn-condition-rule').each(function () {
                var field = $(this).find('.gvn-rule-field').val();
                var operator = $(this).find('.gvn-rule-operator').val();
                if (field && operator) {
                    conditions.rules.push({
                        field: field,
                        operator: operator,
                        value: $(this).find('.gvn-rule-value').val() || ''
                    });
                }
            });
            return conditions;
        },

        /**
         * Automação: ativa/desativa CPF e CNPJ quando Tipo de Pessoa é toggled.
         * Configura condições de exibição automaticamente.
         */
        handlePersonTypeToggle: function (isEnabled) {
            var self = this;
            var dependentFields = {
                'billing_cpf': 'pf',
                'billing_cnpj': 'pj'
            };

            $.each(dependentFields, function (fieldKey, personTypeValue) {
                var $row = self.$list.find('.gvn-field-row[data-key="' + fieldKey + '"]');
                if (!$row.length) return;

                var $checkbox = $row.find('.gvn-field-enabled');

                if (isEnabled) {
                    // Ativar o campo
                    $checkbox.prop('checked', true).trigger('change');
                    $row.removeClass('gvn-field-row--disabled');

                    // Injetar condição de exibição
                    self.injectPersonTypeCondition($row, personTypeValue);
                } else {
                    // Desativar o campo
                    $checkbox.prop('checked', false).trigger('change');
                    $row.addClass('gvn-field-row--disabled');

                    // Remover condições de billing_persontype
                    self.removePersonTypeConditions($row);
                }
            });
        },

        /**
         * Injeta a condição: billing_persontype equals <value> no campo.
         */
        injectPersonTypeCondition: function ($row, value) {
            var $rules = $row.find('.gvn-conditions-rules');
            var $logic = $row.find('.gvn-conditions-logic');

            // Verificar se já existe essa condição
            var alreadyExists = false;
            $rules.find('.gvn-condition-rule').each(function () {
                var rField = $(this).find('.gvn-rule-field').val();
                var rOp = $(this).find('.gvn-rule-operator').val();
                var rVal = $(this).find('.gvn-rule-value').val();
                if (rField === 'billing_persontype' && rOp === 'equals' && rVal === value) {
                    alreadyExists = true;
                    return false;
                }
            });

            if (alreadyExists) return;

            // Gerar options do select de campos excluindo o campo atual
            var currentKey = $row.find('.gvn-field-key-input').val();
            var fieldOptions = this.getFieldOptionsForConditions(currentKey, 'billing_persontype');
            var operatorOptions = this.getOperatorOptions('equals');

            var html = '<div class="gvn-condition-rule">' +
                '<select class="gvn-rule-field"><option value="">-- Campo --</option>' + fieldOptions + '</select>' +
                '<select class="gvn-rule-operator">' + operatorOptions + '</select>' +
                '<input type="text" class="gvn-rule-value" value="' + this.escAttr(value) + '" placeholder="Valor" />' +
                '<button type="button" class="gvn-rule-remove button-link" title="Remover condição">✕</button>' +
                '</div>';

            var $rule = $(html).hide();
            $rules.append($rule);
            $rule.slideDown(150);
            $logic.slideDown(150);
        },

        /**
         * Remove todas as condições referentes a billing_persontype de um campo.
         */
        removePersonTypeConditions: function ($row) {
            var $rules = $row.find('.gvn-conditions-rules');
            $rules.find('.gvn-condition-rule').each(function () {
                var rField = $(this).find('.gvn-rule-field').val();
                if (rField === 'billing_persontype') {
                    $(this).slideUp(150, function () {
                        $(this).remove();
                        if ($rules.find('.gvn-condition-rule').length === 0) {
                            $row.find('.gvn-conditions-logic').slideUp(150);
                        }
                    });
                }
            });
        }
    };

    /* ===================================================
     *  Inicialização
     * =================================================== */
    $(document).ready(function () {
        GVNAdminFields.init();
    });

})(jQuery);
