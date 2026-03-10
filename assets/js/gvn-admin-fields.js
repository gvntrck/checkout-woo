/**
 * GVN Checkout - Admin Fields Manager
 * Drag-and-drop, CRUD, largura e ordenação de campos.
 * @version 1.0.2
 */

(function ($) {
    'use strict';

    var GVNAdminFields = {

        init: function () {
            this.$container = $('#gvn-fields-manager');
            if (!this.$container.length) return;

            this.$list = $('#gvn-fields-list');
            this.$addBtn = $('#gvn-add-field');
            this.$saveBtn = $('#gvn-save-fields');
            this.$status = $('#gvn-fields-status');

            this.bindEvents();
            this.initSortable();
        },

        bindEvents: function () {
            var self = this;

            this.$addBtn.on('click', function () {
                self.addField();
            });

            this.$saveBtn.on('click', function () {
                self.saveFields();
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
            var types = this.getTypeOptions();
            var widths = this.getWidthOptions('100');
            var masks = this.getMaskOptions('');

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
                is_default: false
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
            var enabledChecked = field.enabled ? 'checked' : '';
            var requiredChecked = field.required ? 'checked' : '';
            var disabledClass = field.enabled ? '' : ' gvn-field-row--disabled';
            var keyReadonly = field.is_default ? 'readonly' : '';
            var labelDisplay = field.label || '(novo campo)';

            return '' +
                '<div class="gvn-field-row' + disabledClass + '" data-key="' + field.key + '" data-default="' + isDefault + '">' +
                '  <div class="gvn-field-row__header">' +
                '    <span class="gvn-field-drag" title="Arrastar para reordenar">☰</span>' +
                '    <span class="gvn-field-pos-label">#' + pos + '</span>' +
                '    <span class="gvn-field-label-display">' + this.escHtml(labelDisplay) + '</span>' +
                '    <span class="gvn-field-width-badge">' + field.width + '%</span>' +
                '    <span class="gvn-field-row__actions">' +
                '      <label class="gvn-field-enabled-label"><input type="checkbox" class="gvn-field-enabled" ' + enabledChecked + ' /> Ativo</label>' +
                '      <button type="button" class="gvn-field-toggle button-link"><span class="gvn-toggle-icon">▼</span></button>' +
                '      <button type="button" class="gvn-field-remove button-link" title="Remover">✕</button>' +
                '    </span>' +
                '  </div>' +
                '  <div class="gvn-field-row__body" style="display:none;">' +
                '    <input type="hidden" class="gvn-field-position" value="' + pos + '" />' +
                '    <input type="hidden" class="gvn-field-is-default" value="' + isDefault + '" />' +
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
                '      <div class="gvn-field-col">' +
                '        <label><input type="checkbox" class="gvn-field-required" ' + requiredChecked + ' /> Obrigatório</label>' +
                '      </div>' +
                '    </div>' +
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

        saveFields: function () {
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
                    is_default: $row.find('.gvn-field-is-default').val() === 'true'
                });
            });

            this.$saveBtn.prop('disabled', true).text('Salvando...');

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
                    } else {
                        self.$status.html('<span style="color:#dc2626;">✕ ' + response.data.message + '</span>').show();
                    }
                },
                error: function () {
                    self.$status.html('<span style="color:#dc2626;">Erro ao salvar.</span>').show();
                },
                complete: function () {
                    self.$saveBtn.prop('disabled', false).text('💾 Salvar Campos');
                }
            });
        },

        escHtml: function (str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        },

        escAttr: function (str) {
            return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }
    };

    $(document).ready(function () {
        GVNAdminFields.init();
    });

})(jQuery);
