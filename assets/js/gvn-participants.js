/* global jQuery */
(function ($) {
    'use strict';
    // In-memory drafts only: do not persist participants' CPF/e-mail in browser storage.
    var drafts = {};

    function remember() {
        $('#gvn-participants input').each(function () {
            if (this.name && this.type !== 'hidden') {
                drafts[this.name] = this.type === 'checkbox' ? this.checked : this.value;
            }
        });
    }

    function sync() {
        $('#gvn-participants .gvn-participants__course').each(function () {
            var $course = $(this);
            var buyer = $course.find('.gvn-participants__buyer').prop('checked') === true;
            var $first = $course.find('.gvn-participants__person').first();
            $first.find('.gvn-participants__fields').prop('hidden', buyer);
            $first.find('.gvn-participants__buyer-note').prop('hidden', !buyer);
            $first.find('input').each(function () {
                $(this).prop('disabled', buyer).prop('required', !buyer && this.dataset.participantRequired === '1');
            });
        });
    }

    $(function () {
        sync();
        remember();
        $(document.body).on('input change', '#gvn-participants input', function () {
            remember();
            sync();
        });
        $(document.body).on('updated_checkout', function () {
            $('#gvn-participants input').each(function () {
                if (Object.prototype.hasOwnProperty.call(drafts, this.name)) {
                    if (this.type === 'checkbox') this.checked = drafts[this.name];
                    else this.value = drafts[this.name];
                }
            });
            sync();
        });
        // Prevent hidden/disabled first-vacancy fields from interfering with native form validation.
        $(document.body).on('checkout_error', sync);
    });
})(jQuery);
