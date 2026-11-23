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

(() => {
  'use strict';
  const allowedImages = new Set(['image/jpeg', 'image/png']);
  for (const input of document.querySelectorAll('main input[type="file"]')) {
    const preview = document.createElement('div');
    preview.className = 'app-file-preview';
    preview.hidden = true;
    const names = document.createElement('p');
    names.setAttribute('role', 'status');
    const image = document.createElement('img');
    image.alt = 'Selected attachment';
    image.hidden = true;
    preview.append(names,image);
    (input.closest('label') || input).after(preview);
    let url;
    const clear = () => {
      if (url) URL.revokeObjectURL(url);
      url = null;
      image.hidden = true;
      image.removeAttribute('src');
    };
    const render = () => {
      clear();
      const files = Array.from(input.files);
      preview.hidden = files.length === 0;
      if (preview.hidden) return;
      names.textContent = (files.length === 1 ? 'Selected file: ' : 'Selected files: ')
        + files.map(file => file.name).join(', ') + '. Use Choose File to replace.';
      if (files.length === 1 && allowedImages.has(files[0].type) && files[0].size <= 5 * 1024 * 1024) {
        url = URL.createObjectURL(files[0]);
        image.src = url;
        image.hidden = false;
      }
    };
    image.addEventListener('error', clear);
    input.addEventListener('change',render);
    input.form?.addEventListener('reset',()=>queueMicrotask(render));
    addEventListener('pageshow',render);
  }
})();
