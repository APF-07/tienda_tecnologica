(() => {
    'use strict';
    const grid = document.querySelector('#contenedor-productos');
    if (!grid) return;
    const search = document.querySelector('#input-busqueda');
    const order = document.querySelector('#orden-catalogo');
    const available = document.querySelector('#solo-disponibles');
    const summary = document.querySelector('#resumen-catalogo');
    const selection = document.querySelector('#seleccion-catalogo');
    const cats = [...document.querySelectorAll('.filtro-cat')];
    const brands = [...document.querySelectorAll('.filtro-marca')];
    const small = matchMedia('(max-width:768px)');
    const reduced = matchMedia('(prefers-reduced-motion:reduce)');
    let category = '0', brand = '0', page = 1, request, timer;
    function disclosures() { document.querySelectorAll('.filter-disclosure').forEach(d => d.open = !small.matches); }
    disclosures(); small.addEventListener('change', disclosures);
    function mark(links, key, value) { links.forEach(a => { const active = a.dataset[key] === value; a.classList.toggle('active',active); if (active) a.setAttribute('aria-current','true'); else a.removeAttribute('aria-current'); }); }
    function chips() {
        selection.replaceChildren();
        const chip = (text, clear) => { const b = document.createElement('button'); b.type = 'button'; b.textContent = text + ' ×'; b.setAttribute('aria-label','Quitar filtro: ' + text); b.addEventListener('click',() => { clear(); page = 1; fetchProducts(); }); selection.append(b); };
        if (category !== '0') chip(cats.find(a => a.dataset.cat === category)?.textContent.trim() || 'Categoría',() => { category = '0'; mark(cats,'cat',category); });
        if (brand !== '0') chip(brands.find(a => a.dataset.marca === brand)?.textContent.trim() || 'Marca',() => { brand = '0'; mark(brands,'marca',brand); });
        if (search.value.trim()) chip('«' + search.value.trim() + '»',() => search.value = '');
        if (available.checked) chip('Disponibles',() => available.checked = false);
    }
    function reset() { clearTimeout(timer); category = brand = '0'; page = 1; search.value = ''; order.value = 'recientes'; available.checked = false; mark(cats,'cat',category); mark(brands,'marca',brand); fetchProducts(); }
    async function fetchProducts() {
        clearTimeout(timer); request?.abort(); const current = new AbortController(); request = current;
        chips(); grid.setAttribute('aria-busy','true'); summary.textContent = 'Actualizando productos…';
        if (!grid.children.length) for (let i = 0; i < 6; i++) { const skeleton = document.createElement('div'); skeleton.className = 'catalog-skeleton'; skeleton.setAttribute('aria-hidden','true'); grid.append(skeleton); }
        const params = new URLSearchParams({q:search.value.trim(),categoria:category,marca:brand,pagina:String(page),orden:order.value,disponibles:available.checked?'1':'0'});
        try {
            const response = await fetch('buscar_productos.php?' + params,{signal:current.signal});
            if (!response.ok) throw new Error('Catálogo no disponible');
            const html = await response.text(); if (request !== current) return;
            grid.innerHTML = html;
            const total = Number(response.headers.get('X-Catalog-Total'));
            page = Number(response.headers.get('X-Catalog-Page')) || 1;
            summary.textContent = total === 0 ? 'Sin coincidencias. Prueba otra combinación.' : total + (total === 1 ? ' producto encontrado' : ' productos encontrados') + ' · Página ' + page;
        } catch (error) {
            if (error.name === 'AbortError' || request !== current) return;
            grid.replaceChildren(); const box = document.createElement('div'); box.className = 'no-products';
            const title = document.createElement('h3'); title.textContent = 'No pudimos cargar el catálogo';
            const p = document.createElement('p'); p.textContent = 'Comprueba tu conexión e inténtalo nuevamente.';
            const retry = document.createElement('button'); retry.className = 'btn-detail'; retry.type = 'button'; retry.textContent = 'Reintentar'; retry.addEventListener('click',fetchProducts);
            box.append(title,p,retry); grid.append(box); summary.textContent = 'No se pudo actualizar el catálogo.';
        } finally { if (request === current) grid.setAttribute('aria-busy','false'); }
    }
    cats.forEach(a => a.addEventListener('click',e => { e.preventDefault(); category = a.dataset.cat; page = 1; mark(cats,'cat',category); if (small.matches) a.closest('details').open = false; fetchProducts(); }));
    brands.forEach(a => a.addEventListener('click',e => { e.preventDefault(); brand = a.dataset.marca; page = 1; mark(brands,'marca',brand); if (small.matches) a.closest('details').open = false; fetchProducts(); }));
    search.addEventListener('input',() => { clearTimeout(timer); request?.abort(); page = 1; timer = setTimeout(fetchProducts,250); });
    order.addEventListener('change',() => { page = 1; fetchProducts(); }); available.addEventListener('change',() => { page = 1; fetchProducts(); });
    document.querySelector('#limpiar-filtros').addEventListener('click',reset);
    grid.addEventListener('click',e => { if (e.target.closest('[data-restablecer]')) { reset(); return; } const b = e.target.closest('[data-pagina]'); if (!b) return; page = Number(b.dataset.pagina); fetchProducts(); document.querySelector('#catalogo').scrollIntoView({behavior:reduced.matches?'auto':'smooth',block:'start'}); });
    mark(cats,'cat',category); mark(brands,'marca',brand); fetchProducts();
})();
