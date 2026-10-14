(() => {
    'use strict';
    const main = document.querySelector('.admin-content');
    const header = document.querySelector('.admin-header');
    const heading = header?.querySelector('h1');
    if (!main || !heading) return;
    const active = document.querySelector('.sidebar-menu a[aria-current="page"]');
    const section = active?.querySelector('.menu-label')?.textContent.trim() || 'Administración';
    const dashboard = document.querySelector('.sidebar-menu a');
    const title = heading.textContent.trim();
    const group = document.createElement('div');
    const eyebrow = document.createElement('span'); eyebrow.className = 'admin-title-eyebrow'; eyebrow.textContent = 'TECHSTORE / ADMINISTRACIÓN';
    heading.before(group); group.append(eyebrow,heading);
    const nav = document.createElement('nav'); nav.className = 'admin-breadcrumbs'; nav.setAttribute('aria-label','Ruta de navegación');
    function crumb(text, href) { if (nav.children.length) { const sep = document.createElement('span'); sep.textContent = '/'; sep.setAttribute('aria-hidden','true'); nav.append(sep); } const el = document.createElement(href ? 'a' : 'span'); el.textContent = text; if (href) el.href = href; else el.setAttribute('aria-current','page'); nav.append(el); }
    if (dashboard && dashboard !== active) crumb('Panel de control',dashboard.href);
    if (active && section !== title && dashboard !== active) crumb(section,active.href);
    crumb(title); main.prepend(nav);
    const summaries = {
        'Panel de control':['Tu tienda, bajo control.','Revisa la actividad de tu tienda y accede a las tareas que necesitan tu atención.'],
        'Productos':['Un catálogo listo para vender.','Organiza tus productos, revisa las existencias y mantén la información de la tienda al día.'],
        'Pedidos':['Cada pedido, en su lugar.','Encuentra pedidos, revisa su detalle y gestiona cada etapa desde una sola vista.'],
        'Clientes':['Conoce el historial de tus clientes.','Consulta sus datos y pedidos desde las fichas de cliente.'],
        'Categorías':['Un catálogo fácil de explorar.','Organiza las categorías para que tus clientes encuentren lo que buscan.'],
        'Marcas':['Dale orden a tus marcas.','Mantén las marcas de tu catálogo organizadas y revisa su disponibilidad.']
    };
    const formContainer = main.querySelector('.form-container');
    if (formContainer) {
        const info = document.createElement('div'); info.className = 'admin-form-heading';
        const h = document.createElement('h2'); h.textContent = 'Información del registro';
        const p = document.createElement('p'); p.textContent = 'Completa los campos obligatorios y revisa la información antes de guardar.';
        info.append(h,p); formContainer.prepend(info);
        formContainer.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit',() => { form.setAttribute('aria-busy','true'); const button = form.querySelector('button[type="submit"]'); if (button) { button.dataset.originalText = button.textContent; button.textContent = 'Guardando…'; button.disabled = true; } });
        });
    } else if (summaries[section] && (!active || new URL(active.href).pathname === location.pathname)) {
        const intro = document.createElement('section'); intro.className = 'admin-page-intro';
        const text = document.createElement('div'); const h = document.createElement('h2'); h.textContent = summaries[section][0];
        const p = document.createElement('p'); p.textContent = summaries[section][1]; text.append(h,p); intro.append(text);
        const assistant = document.querySelector('.sidebar-menu a[href*="/asistente/"]');
        if (assistant) { const a = document.createElement('a'); a.href = assistant.href; a.textContent = 'Consultar al asistente ↗'; intro.append(a); }
        nav.after(intro);
    }
    main.querySelectorAll('.alert-error').forEach(el => el.setAttribute('role','alert'));
    main.querySelectorAll('.alert-success').forEach(el => el.setAttribute('role','status'));
    const file = formContainer?.querySelector('input[type="file"][accept*="image"]');
    if (file) {
        const preview = document.createElement('div'); preview.className = 'admin-upload-preview'; preview.hidden = true;
        const image = document.createElement('img'); image.alt = 'Vista previa de la imagen seleccionada';
        const label = document.createElement('span'); preview.append(image,label); file.after(preview); let objectUrl;
        file.addEventListener('change',() => { if (objectUrl) URL.revokeObjectURL(objectUrl); image.removeAttribute('src'); preview.hidden = true; const selected = file.files?.[0]; if (!selected || !['image/jpeg','image/png','image/webp'].includes(selected.type)) return; objectUrl = URL.createObjectURL(selected); image.src = objectUrl; label.textContent = selected.name + ' · ' + Math.round(selected.size/1024) + ' KB. Se guardará al enviar el formulario.'; preview.hidden = false; });
        window.addEventListener('pagehide',() => { if (objectUrl) URL.revokeObjectURL(objectUrl); });
    }
    window.addEventListener('pageshow',() => { main.querySelectorAll('form[aria-busy="true"]').forEach(form => { form.setAttribute('aria-busy','false'); const button = form.querySelector('button[data-original-text]'); if (button) { button.textContent = button.dataset.originalText; button.disabled = false; } }); });
})();
