(() => {
    const form = document.querySelector('#ejecutar-agentes');
    if (!form) return;
    const button = form.querySelector('button');
    form.addEventListener('submit',() => { button.disabled = true; button.textContent = 'Coordinando agentes…'; form.setAttribute('aria-busy','true'); });
    window.addEventListener('pageshow',() => { button.disabled = false; button.textContent = 'Ejecutar revisión ↗'; form.setAttribute('aria-busy','false'); });
})();
