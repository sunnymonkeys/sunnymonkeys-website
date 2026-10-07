/**
 * Sunny Monkeys forms: sends the contact form (#contact-form) and the footer newsletter form
 * (form.subscribtion-input) to /portal/inquiry.php and shows the reply under the form.
 * The server saves every submission to the portal (Admin > Inquiries) before emailing it.
 */
(function ($) {
    'use strict';

    var pageLoadedAt = Date.now();
    var fallback = 'Sorry, something went wrong. Please email us at contact@sunnymonkeys.com.';

    function wire(selector, kind) {
        var $form = $(selector);
        if (!$form.length) return;

        var $status = $('<p class="sm-form-status" role="status" aria-live="polite"></p>')
            .css({ marginTop: '14px', fontSize: '15px', lineHeight: 1.5 })
            .insertAfter($form);

        $form.on('submit', function (e) {
            e.preventDefault();
            var $button = $form.find('[type="submit"]').prop('disabled', true);
            $status.css('color', '').text('Sending...');

            $.ajax({
                type: 'POST',
                url: '/portal/inquiry.php',
                data: $form.serialize() + '&form=' + kind + '&started=' + pageLoadedAt
            })
                .done(function (text) {
                    $status.css('color', '').text(text || 'Thanks!');
                    $form[0].reset();
                })
                .fail(function (xhr) {
                    $status.css('color', '#d9534f').text(xhr.responseText || fallback);
                })
                .always(function () {
                    $button.prop('disabled', false);
                });
        });
    }

    wire('#contact-form', 'contact');

    // Preselect the package when arriving from a "Get Started" button, e.g. /contact?package=growth
    var pkg = (new URLSearchParams(window.location.search).get('package') || '').replace(/[^a-z-]/g, '');
    if (pkg && $('#cf-service option[value="' + pkg + '"]').length) {
        $('#cf-service').val(pkg);
    }
    wire('form.subscribtion-input', 'newsletter');
})(jQuery);
