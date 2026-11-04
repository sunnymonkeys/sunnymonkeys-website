/* sunnymonkeys.com scripts, version 4: menu, header line, reveals, forms.
   No libraries. The forms post to /portal/inquiry.php, which saves every submission before emailing it. */
(function () {
  'use strict';

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var root = document.documentElement;

  /* Hero entrance: only when the page opens in a visible tab, so text is never left hidden */
  if (!reduceMotion && document.visibilityState === 'visible') root.classList.add('anim');
  setTimeout(function () { root.classList.remove('anim'); }, 2500);

  /* Menu (phones and tablets) */
  var toggle = document.querySelector('.nav-toggle');
  var nav = document.getElementById('site-nav');
  if (toggle && nav) {
    var setOpen = function (open) {
      document.body.classList.toggle('nav-open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    };
    toggle.addEventListener('click', function () { setOpen(!document.body.classList.contains('nav-open')); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && document.body.classList.contains('nav-open')) { setOpen(false); toggle.focus(); }
    });
    nav.addEventListener('click', function (e) { if (e.target.closest('a')) setOpen(false); });
    window.matchMedia('(min-width: 900px)').addEventListener('change', function (m) { if (m.matches) setOpen(false); });
  }

  /* Header: a gradient line draws under it once the page is scrolled */
  var header = document.querySelector('.site-header');
  var onHeader = function () { if (header) header.classList.toggle('is-scrolled', window.scrollY > 24); };

  /* Reveals: grids, lists and diagrams animate once when they scroll into view */
  var pending = Array.prototype.slice.call(document.querySelectorAll('[data-reveal], .timeline'));
  pending.forEach(function (el) {
    if (el.getAttribute('data-reveal') === 'stagger') {
      Array.prototype.forEach.call(el.children, function (child, i) { child.style.setProperty('--i', i); });
    }
  });
  var reveal = function (el) {
    el.classList.add('is-visible');
    pending = pending.filter(function (x) { return x !== el; });
  };
  var check = function () {
    var h = window.innerHeight;
    pending.forEach(function (el) {
      var r = el.getBoundingClientRect();
      if (r.width > 0 && r.top < h * 0.9 && r.bottom > 0) reveal(el);
    });
  };
  if (reduceMotion) {
    pending.forEach(function (el) { el.classList.add('is-visible', 'is-static'); });
    pending = [];
  } else {
    root.classList.add('reveal');
    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) { reveal(entry.target); io.unobserve(entry.target); }
        });
      }, { threshold: 0.12 });
      pending.forEach(function (el) { io.observe(el); });
    }
  }
  var last = 0;
  var timer = null;
  var onScroll = function () {
    var now = Date.now();
    if (now - last < 80) {
      if (!timer) timer = setTimeout(function () { timer = null; last = Date.now(); onHeader(); check(); }, 80);
      return;
    }
    last = now;
    onHeader();
    check();
  };
  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', onScroll);
  window.addEventListener('load', function () { onHeader(); check(); });
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') check(); });
  // Early on, look a few more times in case the observer is slow to start
  var tries = 0;
  var early = setInterval(function () { check(); if (++tries >= 12 || !pending.length) clearInterval(early); }, 500);
  onHeader();
  check();
  if (reduceMotion) document.querySelectorAll('.board').forEach(function (el) { el.classList.add('is-static'); });

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
    var toggleStart = function () {
      var inHouse = (service.value || '').indexOf('in-house') === 0;
      startGroup.hidden = !inHouse;
      start.required = inHouse;
      if (!inHouse) start.value = '';
    };
    service.addEventListener('change', toggleStart);
    var pkg = (new URLSearchParams(window.location.search).get('package') || '').replace(/[^a-z-]/g, '');
    if (pkg && service.querySelector('option[value="' + pkg + '"]')) service.value = pkg;
    toggleStart();
  }
})();
