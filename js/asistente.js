'use strict';
const chatForm = document.querySelector('#chat-form');
const questionInput = document.querySelector('#chat-pregunta');
const messageList = document.querySelector('#chat-mensajes');
const chatStatus = document.querySelector('#chat-estado');
const sendButton = document.querySelector('#chat-enviar');
const suggestions = [...document.querySelectorAll('[data-pregunta]')];
let busy = false;
function addMessage(author, text, answer = null, question = '') {
    const message = document.createElement('article');
    message.className = 'chat-message ' + (author === 'Tú' ? 'user-message' : 'assistant-message');
    const title = document.createElement('span'); title.className = 'message-author'; title.textContent = author;
    const body = document.createElement('p'); body.textContent = text;
    message.append(title, body);
    if (answer) {
        const badge = document.createElement('span'); badge.className = 'message-mode';
        badge.textContent = answer.modo === 'gemini' ? 'Gemini · LLM' : (answer.modo === 'sin_respuesta' ? 'Respuesta pendiente' : 'Consulta local'); message.append(badge);
        if (answer.aviso) { const notice = document.createElement('p'); notice.className = 'message-notice'; notice.textContent = answer.aviso; message.append(notice); }
        if (answer.reintentar && question) {
            const retry = document.createElement('button'); retry.type = 'button'; retry.className = 'message-retry'; retry.textContent = 'Reintentar pregunta';
            retry.addEventListener('click', () => {
                if (busy) return;
                questionInput.value = question; document.querySelector('#chat-contador').textContent = question.length + '/500'; chatForm.requestSubmit();
            });
            message.append(retry);
        }
        if (answer.detalle) {
            const details = document.createElement('details'); details.className = 'message-details';
            const summary = document.createElement('summary'); summary.textContent = 'Detalle de la conexión';
            const detail = document.createElement('p'); detail.textContent = answer.detalle;
            details.append(summary, detail); message.append(details);
        }
        if (answer.enlaces?.length) {
            const links = document.createElement('div'); links.className = 'message-links';
            for (const item of answer.enlaces) {
                const url = new URL(item.url, location.href);
                if (url.origin !== location.origin) continue;
                const link = document.createElement('a'); link.href = url.href; link.textContent = item.texto + ' ↗'; links.append(link);
            }
            message.append(links);
        }
        const source = document.createElement('small'); source.className = 'message-source';
        source.textContent = answer.fuente + ' · ' + answer.fecha; message.append(source);
    }
    messageList.append(message);
    // Mostrar el inicio de respuestas extensas, en lugar de saltar a su final.
    messageList.scrollTop = author === 'Tú' ? messageList.scrollHeight : Math.max(0, messageList.scrollTop + message.getBoundingClientRect().top - messageList.getBoundingClientRect().top - 18);
}
function setBusy(value) {
    busy = value; sendButton.disabled = value;
    questionInput.readOnly = value;
    suggestions.forEach(button => { button.disabled = value; });
    document.querySelectorAll('.message-retry').forEach(button => { button.disabled = value; });
    document.querySelector('#limpiar-chat').disabled = value;
    chatForm.setAttribute('aria-busy', String(value));
    sendButton.firstChild.textContent = value ? 'Consultando… ' : 'Enviar pregunta ';
}
questionInput.addEventListener('input', () => { document.querySelector('#chat-contador').textContent = questionInput.value.length + '/500'; });
questionInput.addEventListener('keydown', event => {
    if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) { event.preventDefault(); chatForm.requestSubmit(); }
});
chatForm.addEventListener('submit', async event => {
    event.preventDefault();
    if (busy) return;
    const question = questionInput.value.trim();
    if (!question) { questionInput.focus(); return; }
    const data = new FormData(chatForm); data.set('pregunta', question);
    addMessage('Tú', question); setBusy(true); chatStatus.textContent = 'Consultando información…';
    const controller = new AbortController(); const timeout = setTimeout(() => controller.abort(), 35000);
    try {
        const response = await fetch(chatForm.action, {method:'POST', body:data, headers:{Accept:'application/json'}, signal:controller.signal});
        const answer = await response.json().catch(() => { throw new Error('No se pudo leer la respuesta. Recarga la página y vuelve a intentarlo.'); });
        if (!response.ok) throw new Error(answer.error || 'No se pudo realizar la consulta.');
        addMessage('Asistente', answer.texto, answer, question);
        questionInput.value = ''; document.querySelector('#chat-contador').textContent = '0/500';
        chatStatus.textContent = '';
    } catch (error) {
        addMessage('Asistente', error.name === 'AbortError' ? 'La consulta tardó demasiado. Puedes intentar nuevamente.' : error.message);
        chatStatus.textContent = 'La consulta no se completó. Conservamos tu pregunta para reintentar.';
    } finally { clearTimeout(timeout); setBusy(false); questionInput.focus({preventScroll:true}); }
});
suggestions.forEach(button => button.addEventListener('click', () => {
    questionInput.value = button.dataset.pregunta;
    document.querySelector('#chat-contador').textContent = questionInput.value.length + '/500';
    chatForm.requestSubmit();
}));
document.querySelector('#limpiar-chat').addEventListener('click', async () => {
    if (busy) return;
    setBusy(true);
    const controller = new AbortController(); const timeout = setTimeout(() => controller.abort(), 20000);
    try {
        const data = new FormData(chatForm); data.set('accion', 'limpiar');
        const response = await fetch(chatForm.action, {method:'POST', body:data, signal:controller.signal});
        const result = await response.json();
        if (!response.ok || !result.ok) throw new Error('No se pudo limpiar la conversación. Recarga e inténtalo otra vez.');
        messageList.replaceChildren(); addMessage('Asistente', 'Conversación limpia. Puedes hacer otra consulta sobre tu tienda.');
        chatStatus.textContent = '';
    } catch (error) { chatStatus.textContent = error.name === 'AbortError' ? 'No se pudo limpiar la conversación a tiempo. Intenta nuevamente.' : error.message; }
    finally { clearTimeout(timeout); setBusy(false); questionInput.focus(); }
});
