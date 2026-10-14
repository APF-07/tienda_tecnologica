/* Mejoras progresivas: los formularios y enlaces funcionan sin este archivo. */
(() => {
    'use strict';
    const main = document.querySelector('.admin-content, .store-container, .checkout-container, .cart-container, .tracker-card, .product-detail-container, .access');
    if (main) {
        if (!main.id) main.id = 'contenido-principal';
        const skip = document.createElement('a');
        skip.className = 'ui-skip'; skip.href = '#' + main.id; skip.textContent = 'Saltar al contenido';
        document.body.prepend(skip);
        main.tabIndex = -1;
    }
    const sidebar = document.querySelector('.admin-sidebar');
    if (sidebar) {
        const nav = sidebar.querySelector('nav');
        if (nav) {
            nav.id = 'navegacion-admin';
            const toggle = document.createElement('button');
            toggle.type = 'button'; toggle.className = 'ui-menu-toggle';
            toggle.setAttribute('aria-controls', nav.id); toggle.setAttribute('aria-expanded', 'false');
            toggle.textContent = '☰ Abrir menú de administración';
            sidebar.querySelector('.sidebar-brand').after(toggle);
            sidebar.classList.add('ui-nav-ready');
            const close = () => { sidebar.classList.remove('ui-nav-open'); toggle.setAttribute('aria-expanded', 'false'); toggle.textContent = '☰ Abrir menú de administración'; };
            toggle.addEventListener('click', () => {
                const open = sidebar.classList.toggle('ui-nav-open');
                toggle.setAttribute('aria-expanded', String(open));
                toggle.textContent = open ? '× Cerrar menú de administración' : '☰ Abrir menú de administración';
            });
            sidebar.addEventListener('keydown', event => { if (event.key === 'Escape') { close(); toggle.focus(); } });
            nav.addEventListener('click', event => { if (event.target.closest('a')) close(); });
        }
    }
    document.querySelectorAll('.admin-content table').forEach(table => {
        if (table.closest('.ui-table-scroll')) return;
        const wrapper = document.createElement('div');
        wrapper.className = 'ui-table-scroll'; wrapper.tabIndex = 0;
        wrapper.setAttribute('role', 'region');
        wrapper.setAttribute('aria-label', 'Tabla de datos. Desplázate horizontalmente para ver todas las columnas.');
        table.before(wrapper); wrapper.append(table);
    });
})();
