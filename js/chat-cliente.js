(() => {
    'use strict';
    const panel = document.querySelector('#chat-cliente');
    if (!panel) return;
    const launch = document.querySelector('#abrir-chat-cliente');
    const close = document.querySelector('#cerrar-chat-cliente');
    const form = document.querySelector('#form-chat-cliente');
    const input = document.querySelector('#pregunta-cliente');
    const log = document.querySelector('#mensajes-cliente');
    const status = document.querySelector('#estado-chat-cliente');
    let busy = false;
    const hide = () => { panel.hidden = true; launch.hidden = false; launch.setAttribute('aria-expanded', 'false'); launch.focus({preventScroll:true}); };
    launch.addEventListener('click', () => { panel.hidden = false; launch.hidden = true; launch.setAttribute('aria-expanded', 'true'); input.focus({preventScroll:true}); });
    close.addEventListener('click', hide);
    panel.addEventListener('keydown', event => { if (event.key === 'Escape') hide(); });
    const safeUrl = value => { try { const url = new URL(value, location.href); return url.origin === location.origin && ['http:', 'https:'].includes(url.protocol) ? url.href : null; } catch { return null; } };
    function add(text, user = false, answer = null) {
        const item = document.createElement('article'); item.className = user ? 'customer-question' : 'customer-answer';
        const body = document.createElement('p'); body.textContent = text; item.append(body);
        for (const product of answer?.productos || []) {
            const href = safeUrl(product.url); if (!href) continue;
            const link = document.createElement('a'); link.className = 'customer-product'; link.href = href;
            const image = document.createElement('img'); image.alt = ''; image.loading = 'lazy'; const src = safeUrl(product.imagen); if (src) image.src = src;
            const info = document.createElement('div'); const name = document.createElement('strong'); name.textContent = product.nombre;
            const details = document.createElement('span'); details.textContent = new Intl.NumberFormat('es-PE', {style:'currency',currency:'PEN'}).format(Number(product.precio)) + (Number(product.stock) > 0 ? ' · Disponible' : ' · Agotado');
            info.append(name, details); link.append(image, info); item.append(link);
        }
        if (answer?.enlace) { const href = safeUrl(answer.enlace.url); if (href) { const a = document.createElement('a'); a.href = href; a.className = 'customer-track'; a.textContent = answer.enlace.texto + ' ↗'; item.append(a); } }
        if (answer) { const note = document.createElement('small'); note.textContent = answer.aviso || (answer.modo === 'gemini' ? 'Asistente con IA · Catálogo de TechStore' : 'Información de la tienda'); item.append(note); }
        log.append(item); log.scrollTop = user ? log.scrollHeight : Math.max(0,log.scrollTop + item.getBoundingClientRect().top - log.getBoundingClientRect().top - 12);
    }
    form.addEventListener('submit', async event => {
        event.preventDefault(); if (busy || !input.value.trim()) return;
        const question = input.value.trim(); const data = new FormData(form); data.set('pregunta', question);
        busy = true; input.readOnly = true; form.setAttribute('aria-busy','true');
        panel.querySelectorAll('button:not(#cerrar-chat-cliente)').forEach(b => b.disabled = true);
        add(question, true); status.textContent = 'Consultando la tienda…';
        const controller = new AbortController(); const timer = setTimeout(() => controller.abort(),35000);
        try {
            const response = await fetch(form.action,{method:'POST',body:data,signal:controller.signal,headers:{Accept:'application/json'}});
            const answer = await response.json().catch(() => { throw new Error('Recarga la tienda para renovar tu sesión e inténtalo otra vez.'); });
            if (!response.ok) throw new Error(answer.error || 'No se pudo realizar la consulta.');
            add(answer.texto,false,answer); input.value = ''; status.textContent = '';
        } catch (error) { status.textContent = error.name === 'AbortError' ? 'La consulta tardó demasiado. Conservamos tu pregunta para reintentar.' : error.message; }
        finally { clearTimeout(timer); busy = false; input.readOnly = false; form.setAttribute('aria-busy','false'); panel.querySelectorAll('button').forEach(b => b.disabled = false); if (!panel.hidden) input.focus({preventScroll:true}); }
    });
    input.addEventListener('keydown',event => { if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) { event.preventDefault(); form.requestSubmit(); } });
    panel.querySelectorAll('[data-consulta]').forEach(b => b.addEventListener('click',() => { input.value = b.dataset.consulta; form.requestSubmit(); }));
})();
