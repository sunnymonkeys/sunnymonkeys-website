/* sunnymonkeys.com scripts: menu, forms and the diagrams that start when they scroll into view.
   No libraries. The forms post to /portal/inquiry.php, which saves every submission before emailing it. */
(function () {
  'use strict';

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* Menu (phones and tablets) */
  var toggle = document.querySelector('.nav-toggle');
  var nav = document.getElementById('site-nav');
  if (toggle && nav) {
    function setOpen(open) {
      document.body.classList.toggle('nav-open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    }
    toggle.addEventListener('click', function () {
      setOpen(!document.body.classList.contains('nav-open'));
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && document.body.classList.contains('nav-open')) { setOpen(false); toggle.focus(); }
    });
    nav.addEventListener('click', function (e) {
      if (e.target.closest('a')) setOpen(false);
    });
    window.matchMedia('(min-width: 900px)').addEventListener('change', function (m) { if (m.matches) setOpen(false); });
  }

  /* Diagrams: animate once when they scroll into view */
  var animated = Array.prototype.slice.call(document.querySelectorAll('.timeline'));
  if (animated.length) {
    if (reduceMotion) {
      animated.forEach(function (el) { el.classList.add('is-static'); });
    } else {
      var reveal = function (el) { el.classList.add('is-visible'); animated = animated.filter(function (x) { return x !== el; }); };
      var check = function () {
        animated.forEach(function (el) {
          var r = el.getBoundingClientRect();
          if (r.width > 0 && r.top < window.innerHeight * 0.85 && r.bottom > 0) reveal(el);
        });
      };
      if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
          entries.forEach(function (entry) {
            if (entry.isIntersecting) { reveal(entry.target); io.unobserve(entry.target); }
          });
        }, { threshold: 0.35 });
        animated.forEach(function (el) { io.observe(el); });
      }
      // Scroll check as well, for browsers that delay observer callbacks
      var ticking = false;
      var onScroll = function () {
        if (ticking) return;
        ticking = true;
        requestAnimationFrame(function () { ticking = false; check(); });
      };
      window.addEventListener('scroll', onScroll, { passive: true });
      window.addEventListener('resize', onScroll);
      window.addEventListener('load', check);
      check();
    }
  }
  if (reduceMotion) {
    document.querySelectorAll('.board').forEach(function (el) { el.classList.add('is-static'); });
  }

  /* Forms */
  var pageLoadedAt = Date.now();
  var fallback = 'Sorry, something went wrong. Please email us at contact@sunnymonkeys.com.';

  function wire(form, kind) {
    if (!form) return;
    var status = form.querySelector('.form-status');
    if (!status) {
      status = document.createElement('p');
      status.className = 'form-status';
      status.setAttribute('role', 'status');
      status.setAttribute('aria-live', 'polite');
      form.appendChild(status);
    }
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!form.reportValidity()) return;
      var button = form.querySelector('[type="submit"]');
      button.disabled = true;
      status.removeAttribute('data-state');
      status.textContent = 'Sending...';

      var data = new URLSearchParams(new FormData(form));
      data.append('form', kind);
      data.append('started', String(pageLoadedAt));

      fetch('/portal/inquiry.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'fetch' },
        body: data.toString()
      }).then(function (res) {
        return res.text().then(function (text) { return { ok: res.ok, text: text }; });
      }).then(function (r) {
        status.setAttribute('data-state', r.ok ? 'success' : 'error');
        status.textContent = r.text || (r.ok ? 'Thanks!' : fallback);
        if (r.ok) {
          form.reset();
          var service = form.querySelector('#cf-service');
          if (service) service.dispatchEvent(new Event('change'));
        }
      }).catch(function () {
        status.setAttribute('data-state', 'error');
        status.textContent = fallback;
      }).then(function () {
        button.disabled = false;
      });
    });
  }

  wire(document.getElementById('contact-form'), 'contact');
  wire(document.getElementById('newsletter-form'), 'newsletter');

  /* Contact page: In-House Designer inquiries also ask when the designer should start */
  var service = document.getElementById('cf-service');
  var start = document.getElementById('cf-start');
  var startGroup = document.getElementById('cf-start-group');
  if (service && start && startGroup) {
    function toggleStart() {
      var inHouse = (service.value || '').indexOf('in-house') === 0;
      startGroup.hidden = !inHouse;
      start.required = inHouse;
      if (!inHouse) start.value = '';
    }
    service.addEventListener('change', toggleStart);

    // Preselect the service or package when arriving from a button, e.g. /contact?package=growth
    var pkg = (new URLSearchParams(window.location.search).get('package') || '').replace(/[^a-z-]/g, '');
    if (pkg && service.querySelector('option[value="' + pkg + '"]')) service.value = pkg;
    toggleStart();
  }
})();
