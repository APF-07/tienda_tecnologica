# Gemini en el asistente de TechStore

La integración utiliza `gemini-3.5-flash-lite` (configurable con `GEMINI_MODEL`) mediante REST desde PHP/cURL. Su nivel gratuito está sujeto a la disponibilidad y cuota de cada proyecto. DeepSeek publica tarifas por tokens; su aplicación de chat gratis no significa que su API sea gratis.

## Activación local

1. Entra a https://aistudio.google.com/apikey con tu cuenta de Google y crea una clave para un proyecto **Free Tier sin facturación habilitada**. Si la plataforma solicita activar facturación, no lo hagas para este proyecto gratuito.
2. Abre `C:\xampp\htdocs\tienda_tecnologica\.env` y coloca tu clave entre las comillas:

```ini
GEMINI_API_KEY="tu_clave_aqui"
GEMINI_ENABLED=true
```

3. Guarda y recarga `http://localhost:8080/tienda_tecnologica/admin/asistente/index.php`. El indicador debe decir «Gemini configurado». Prueba: «Explícame qué debería revisar primero en mi tienda y por qué».
4. Una respuesta realmente generada muestra **Gemini · LLM**. Si falta la clave, internet, permisos o cuota, verás **Consulta local** y el motivo. «Configurado» indica que existe una clave, no confirma todavía que Google la acepte.

No compartas la clave en el chat, en capturas ni en Git. `.env` está ignorado por Git y bloqueado por Apache. También puedes definir `GEMINI_API_KEY` y `GEMINI_ENABLED` como variables del proceso Apache; tienen prioridad sobre `.env`. En ese caso reinicia Apache después de cambiarlas. PHP debe disponer de cURL y certificados HTTPS válidos; no se desactiva la verificación TLS.

## Funcionamiento y límites

- Solo hay consultas SQL fijas; el LLM no ejecuta SQL ni dispone de herramientas de escritura.
- Se envía a Google la pregunta, hasta tres intercambios anteriores, conteos por estado, cantidad de clientes/productos activos, cinco ejemplos de stock bajo y resumen de métricas/pronósticos ML con su fecha de corte.
- El contexto SQL no incluye correos, teléfonos, direcciones, DNI, nombres de clientes ni contraseñas. Lo que el usuario escriba en la pregunta o historial sí se transmite: no introduzcas secretos ni datos personales.
- Google puede utilizar contenido del nivel gratuito para mejorar productos. Revisa sus condiciones si cambias de datos académicos a datos reales.
- El historial se mantiene temporalmente en la sesión de PHP, máximo tres intercambios. Limpiar conversación elimina esa memoria; recargar la página inicia una conversación nueva. No se guarda en MySQL.
- Una solicitud de cambio de registros recibe el rechazo local sin llamar a Gemini. No se activan pagos, entrenamiento ni cambios de estados desde el chat.
- Máximo cinco llamadas por minuto en la sesión, hasta 900 tokens de salida. Un error 503 permite un único reintento tras una pausa de un segundo, respetando el límite local; no se reintentan errores de cuota o pago ni se cambia a modelos pagados.
- El código no puede comprobar si la clave pertenece a un proyecto con facturación: para coste cero hay que mantener el proyecto en Free Tier. Los límites locales reducen uso, no garantizan gratuidad de una cuenta pagada.
- Las respuestas generadas pueden contener errores. El prompt exige basarse en el contexto y distinguir las métricas históricas de la probabilidad futura; no garantiza ausencia de alucinaciones.

## Verificación

`C:/xampp/php/php.exe scripts/pruebas_gemini.php` ejecuta pruebas en SQLite temporal con proveedor simulado. Comprueba respaldo local, límite del historial, exclusión de datos de contacto, rechazo de cambios, errores de cuota y respuesta incompleta. No llama a Google ni consume cuota. La aceptación de una clave real se verifica usando el chat después de configurarla.

Fuentes revisadas el 13/10/2026:

- https://ai.google.dev/gemini-api/docs/pricing?hl=en
- https://ai.google.dev/api/generate-content
- https://ai.google.dev/gemini-api/docs/api-key
- https://api-docs.deepseek.com/quick_start/pricing/

La consulta «comprador más frecuente» se calcula directamente en MySQL, contando pedidos confirmados, en preparación, enviados y entregados de todo el historial. Excluye pendientes y cancelados y muestra empates. No llama a Gemini ni agrega nombres de clientes al historial enviado al proveedor. Se puede comprobar con `C:/xampp/php/php.exe scripts/pruebas_comprador.php`. Un HTTP 503 de Gemini indica indisponibilidad temporal; la consulta local sigue funcionando.

Si Gemini sigue sin responder, el chat ofrece reintentar la pregunta y guarda el detalle HTTP en un bloque desplegable. Las respuestas fallidas no se agregan a la memoria conversacional. Las consultas locales y las respuestas generadas se identifican por separado. Pruebas del reintento: scripts/pruebas_reintento.php.

### Consultas operativas del asistente

Las preguntas de ventas observadas (hoy, ayer, esta semana, este mes, últimos 7 o 30 días), búsqueda de productos, stock, pedidos y clientes se calculan mediante consultas parametrizadas de MySQL. No requieren disponibilidad de Gemini ni consumen cuota. Las ventas incluyen envío y solo estados Confirmado, En preparación, Enviado y Entregado; se filtran por fecha de creación del pedido y no equivalen a pagos conciliados.

Ejemplos: «¿Cuánto vendí hoy?», «Ventas de este mes», «¿Qué stock tiene ASUS?», «Busca productos laptops», «¿Quién es el comprador más frecuente?». Las búsquedas consultan productos activos por nombre, código, marca o categoría y muestran hasta ocho coincidencias. Una búsqueda sin resultados no devuelve el catálogo completo.

Gemini sigue disponible para conversación, interpretación y explicación de los modelos. Las consultas locales identifican su fuente y los informes ML conservan su fecha de corte. Pruebas de periodos, filtros e inexistencias: `C:/xampp/php/php.exe scripts/pruebas_consultas_admin.php`.

«El producto más vendido» es una consulta independiente del resumen de ventas. Clasifica por suma de unidades de detalle_pedidos en estados confirmados/en proceso/entregados, muestra empates y usa el precio histórico del pedido para el importe sin envío. Sin periodo explícito consulta todo el historial hasta hoy; también admite hoy, ayer, esta semana, este mes y últimos 7/30 días. La prueba de regresión que reproduce la pregunta es `scripts/pruebas_mas_vendido.php`.
