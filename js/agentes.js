(() => {
    const form = document.querySelector('#ejecutar-agentes');
    if (!form) return;
    const button = form.querySelector('button');
    form.addEventListener('submit',() => { button.disabled = true; button.textContent = 'Revisando inventario y pedidos…'; form.setAttribute('aria-busy','true'); });
    window.addEventListener('pageshow',() => { button.disabled = false; button.textContent = 'Revisar inventario y pedidos'; form.setAttribute('aria-busy','false'); });
})();
