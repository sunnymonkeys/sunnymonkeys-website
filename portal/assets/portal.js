/* Client portal scripts, version 1. Everything that used to be inline in the pages lives here,
   so the site's Content-Security-Policy can refuse inline scripts. */
(function () {
  'use strict';

  /* Toasts fade out after a few seconds */
  document.querySelectorAll('[data-toast]').forEach(function (toast) {
    setTimeout(function () { toast.classList.add('is-hidden'); }, 3500);
  });

  /* Slide-in panel (client dashboard: "Request a change") */
  var overlay = document.getElementById('overlay');
  var panel = document.getElementById('panel');
  var setPanel = function (open) {
    if (!overlay || !panel) return;
    overlay.classList.toggle('open', open);
    panel.classList.toggle('open', open);
    document.body.style.overflow = open ? 'hidden' : '';
  };
  document.querySelectorAll('[data-panel-open]').forEach(function (el) {
    el.addEventListener('click', function () { setPanel(true); });
  });
  document.querySelectorAll('[data-panel-close]').forEach(function (el) {
    el.addEventListener('click', function () { setPanel(false); });
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setPanel(false); });

  /* Admin: inline edit rows for add-ons */
  document.querySelectorAll('[data-edit-toggle]').forEach(function (button) {
    button.addEventListener('click', function () {
      var row = document.getElementById('edit-' + button.getAttribute('data-edit-toggle'));
      if (row) row.style.display = row.style.display === 'table-row' ? 'none' : 'table-row';
    });
  });

  /* Admin: standard packages show their promo block when selected */
  document.querySelectorAll('[data-promo-for]').forEach(function (box) {
    box.addEventListener('change', function () {
      var block = document.getElementById('promo-' + box.getAttribute('data-promo-for'));
      if (block) block.classList.toggle('visible', box.checked);
    });
  });
  document.querySelectorAll('[data-promo-fields]').forEach(function (box) {
    box.addEventListener('change', function () {
      var fields = document.getElementById('promo-fields-' + box.getAttribute('data-promo-fields'));
      if (fields) fields.style.display = box.checked ? 'grid' : 'none';
    });
  });

  /* Forms that ask before submitting (remove an add-on) */
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!window.confirm(form.getAttribute('data-confirm'))) e.preventDefault();
    });
  });

  /* Admin: copy newsletter emails */
  var copy = document.getElementById('copy-emails');
  if (copy) {
    copy.addEventListener('click', function () {
      var emails = Array.prototype.map.call(document.querySelectorAll('.sub-email'), function (td) {
        return td.textContent.trim();
      });
      navigator.clipboard.writeText(emails.join('\n')).then(function () {
        copy.textContent = 'Copied ' + emails.length;
      });
    });
  }
})();
