/* Champion Auto Finance — interactions & motion */
(function () {
  'use strict';
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---- Snapshot/verification mode (?snap=1): reveal all, collapse hero height ---- */
  const SNAP = location.search.indexOf('snap=1') !== -1;
  if (SNAP) {
    const s = document.createElement('style');
    s.textContent = '.hero{min-height:auto!important;padding-top:9rem!important}'
      + '.reveal{opacity:1!important;transform:none!important}'
      + '.intro{display:none!important}';
    document.head.appendChild(s);
  }

  /* ---- Intro loader ---- */
  window.addEventListener('load', () => {
    const intro = document.getElementById('intro');
    if (intro) setTimeout(() => intro.classList.add('is-done'), 650);
  });

  /* ---- Year ---- */
  const yearEl = document.getElementById('year');
  if (yearEl) yearEl.textContent = new Date().getFullYear();

  /* ---- Sticky nav + scroll progress ---- */
  const nav = document.getElementById('nav');
  const progress = document.getElementById('scrollProgress');
  function onScroll() {
    const y = window.scrollY;
    if (nav) nav.classList.toggle('is-stuck', y > 20);
    if (progress) {
      const h = document.documentElement.scrollHeight - window.innerHeight;
      progress.style.width = (h > 0 ? (y / h) * 100 : 0) + '%';
    }
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* ---- Mobile menu ---- */
  const burger = document.getElementById('navBurger');
  if (burger) {
    burger.addEventListener('click', () => nav.classList.toggle('is-open'));
    document.querySelectorAll('#navLinks a').forEach(a =>
      a.addEventListener('click', () => nav.classList.remove('is-open')));
  }

  /* ---- Scroll reveal (staggered) ---- */
  const reveals = document.querySelectorAll('[data-reveal]');
  if (reduceMotion) {
    reveals.forEach(el => el.classList.add('is-visible'));
  } else {
    const io = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const delay = parseInt(entry.target.dataset.delay || '0', 10);
          setTimeout(() => entry.target.classList.add('is-visible'), delay);
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.15, rootMargin: '0px 0px -8% 0px' });
    reveals.forEach(el => io.observe(el));

    // If the page is deep-linked to an anchor, reveal everything immediately
    // (the visitor lands mid-page and shouldn't see empty sections).
    if (location.hash) reveals.forEach(el => el.classList.add('is-visible'));

    // Safety net: never leave content permanently hidden if the observer
    // doesn't fire (deep links, restored scroll positions, edge browsers).
    setTimeout(() => {
      reveals.forEach(el => {
        const r = el.getBoundingClientRect();
        if (r.top < window.innerHeight && r.bottom > 0) el.classList.add('is-visible');
      });
    }, 1200);
  }

  /* ---- Count-up stats ---- */
  const counters = document.querySelectorAll('[data-count]');
  const countIO = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      const el = entry.target;
      const target = parseInt(el.dataset.count, 10);
      const suffix = el.dataset.suffix || '';
      const dur = 1400;
      const start = performance.now();
      function tick(now) {
        const p = Math.min((now - start) / dur, 1);
        const eased = 1 - Math.pow(1 - p, 3);
        el.textContent = Math.round(target * eased) + suffix;
        if (p < 1) requestAnimationFrame(tick);
      }
      if (reduceMotion) { el.textContent = target + suffix; }
      else requestAnimationFrame(tick);
      countIO.unobserve(el);
    });
  }, { threshold: 0.6 });
  counters.forEach(c => countIO.observe(c));

  /* ---- Parallax shield on mouse + cursor glow ---- */
  const glow = document.getElementById('cursorGlow');
  const shieldWrap = document.querySelector('.hero__shield-wrap');
  if (!reduceMotion) {
    window.addEventListener('mousemove', (e) => {
      if (glow) { glow.style.left = e.clientX + 'px'; glow.style.top = e.clientY + 'px'; }
      if (shieldWrap && window.scrollY < window.innerHeight) {
        const dx = (e.clientX / window.innerWidth - 0.5) * 16;
        const dy = (e.clientY / window.innerHeight - 0.5) * 16;
        shieldWrap.style.transform = `translate(${dx}px, ${dy}px)`;
      }
    }, { passive: true });
  }

  /* ---- FAQ accordion ---- */
  document.querySelectorAll('.acc__q').forEach(q => {
    q.addEventListener('click', () => {
      const acc = q.parentElement;
      const ans = acc.querySelector('.acc__a');
      const open = acc.classList.contains('is-open');
      document.querySelectorAll('.acc').forEach(a => {
        a.classList.remove('is-open');
        a.querySelector('.acc__a').style.maxHeight = null;
      });
      if (!open) {
        acc.classList.add('is-open');
        ans.style.maxHeight = ans.scrollHeight + 'px';
      }
    });
  });

  /* ---- Lead form -> PHP mailer (same-origin) ---- */
  const form = document.getElementById('leadForm');
  const note = document.getElementById('formNote');
  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const data = Object.fromEntries(new FormData(form));
      if (!data.name || !data.email) {
        note.textContent = 'Please add your name and email.';
        note.style.color = '#c0392b';
        return;
      }
      const btn = form.querySelector('button[type="submit"]');
      const label = btn ? btn.innerHTML : '';
      if (btn) { btn.disabled = true; btn.style.opacity = '0.7'; }
      note.style.color = '';
      note.textContent = 'Sending\u2026';
      try {
        const fd = new FormData(form);
        fd.append('_page', location.pathname);
        const res = await fetch('/caf-v7-assets/lead.php', { method: 'POST', body: fd });
        const out = await res.json().catch(() => ({ ok: false }));
        if (res.ok && out.ok) {
          note.style.color = '';
          note.textContent = `Thanks, ${data.name.split(' ')[0]} \u2014 we\u2019ll be in touch shortly.`;
          form.reset();
        } else {
          note.style.color = '#c0392b';
          note.textContent = out.error || 'Something went wrong. Please call (732) 618-2036.';
        }
      } catch (err) {
        note.style.color = '#c0392b';
        note.textContent = 'Network error. Please call (732) 618-2036 or email info@championautofinance.com.';
      } finally {
        if (btn) { btn.disabled = false; btn.style.opacity = ''; btn.innerHTML = label; }
      }
    });
  }
})();
