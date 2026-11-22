(() => {
  'use strict';
  const body = document.body;
  if (!body.classList.contains('app-page')) return;
  const toggle = document.getElementById('app-menu');
  const sidebar = document.getElementById('app-sidebar');
  const backdrop = document.getElementById('app-backdrop');
  const content = document.querySelector('.content');
  const mobile = window.matchMedia('(max-width: 999px)');
  const focusable = () => [...sidebar.querySelectorAll('a[href],button:not([disabled])')];
  const sync = () => {
    const open = body.classList.contains('app-menu-open') && mobile.matches;
    backdrop.hidden = !open;
    sidebar.inert = mobile.matches && !open;
    content.inert = open;
    toggle.setAttribute('aria-expanded', String(mobile.matches ? open : !body.classList.contains('app-collapsed')));
    toggle.setAttribute('aria-label', mobile.matches ? 'Open navigation' : (body.classList.contains('app-collapsed') ? 'Expand navigation' : 'Collapse navigation'));
  };
  const close = (restore = true) => { body.classList.remove('app-menu-open'); sync(); if (restore) toggle.focus(); };
  try { if (localStorage.getItem('tivoli.sidebar.collapsed') === '1') body.classList.add('app-collapsed'); } catch (_) {}
  toggle.addEventListener('click', () => {
    if (mobile.matches) {
      body.classList.add('app-menu-open'); sync(); (focusable()[0] || sidebar).focus();
    } else {
      body.classList.toggle('app-collapsed');
      try { localStorage.setItem('tivoli.sidebar.collapsed', body.classList.contains('app-collapsed') ? '1' : '0'); } catch (_) {}
      sync(); setTimeout(() => window.dispatchEvent(new Event('resize')), 220);
    }
  });
  backdrop.addEventListener('click', () => close());
  document.getElementById('app-drawer-close').addEventListener('click', () => close());
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      if (body.classList.contains('app-menu-open')) close();
      document.querySelectorAll('.app-account[open]').forEach(item => { item.open = false; item.querySelector('summary').focus(); });
    }
    if (event.key === 'Tab' && mobile.matches && body.classList.contains('app-menu-open')) {
      const items = focusable();
      if (!items.length) { event.preventDefault(); sidebar.focus(); return; }
      const first = items[0], last = items[items.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
  });
  document.addEventListener('click', event => document.querySelectorAll('.app-account[open]').forEach(item => { if (!item.contains(event.target)) item.open = false; }));
  mobile.addEventListener('change', () => { close(false); });
  sync();
})();
