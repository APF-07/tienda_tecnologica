'use strict';
const loginForm = document.querySelector('#login-form');
const passwordInput = document.querySelector('#clave-login');
const passwordToggle = document.querySelector('.password-toggle');
const capsWarning = document.querySelector('#caps-warning');
passwordToggle.hidden = false;
passwordToggle.addEventListener('click', () => {
    const visible = passwordInput.type === 'password';
    passwordInput.type = visible ? 'text' : 'password';
    passwordToggle.setAttribute('aria-pressed', String(visible));
    passwordToggle.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña');
});
passwordInput.addEventListener('keyup', event => {
    capsWarning.hidden = !event.getModifierState('CapsLock');
});
passwordInput.addEventListener('blur', () => { capsWarning.hidden = true; });
loginForm.addEventListener('submit', () => {
    loginForm.setAttribute('aria-busy', 'true');
    const button = loginForm.querySelector('[type="submit"]');
    button.disabled = true;
    button.querySelector('span').textContent = 'Ingresando…';
});
window.addEventListener('pageshow', () => {
    loginForm.setAttribute('aria-busy', 'false');
    const button = loginForm.querySelector('[type="submit"]');
    button.disabled = false;
    button.querySelector('span').textContent = 'Iniciar sesión';
});
