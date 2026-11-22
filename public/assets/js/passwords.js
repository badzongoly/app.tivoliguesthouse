'use strict';
document.querySelectorAll('[data-password-toggle]').forEach(button => {
  button.addEventListener('click', () => {
    const input = document.getElementById(button.dataset.passwordToggle);
    const visible = input.type === 'password';
    input.type = visible ? 'text' : 'password';
    button.textContent = visible ? 'Hide' : 'Show';
    button.setAttribute('aria-pressed', String(visible));
    button.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
  });
});// Keep recovery tokens out of HTTP URLs, access logs and referrers.
const resetToken = document.getElementById('reset-token');
if (resetToken && window.location.hash) {
  resetToken.value = new URLSearchParams(window.location.hash.slice(1)).get('token') || '';
  history.replaceState(null, '', window.location.pathname);
}
