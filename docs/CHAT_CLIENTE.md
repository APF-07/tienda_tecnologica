# Chat de compras

El botón «¿Te ayudo?» se muestra en las páginas públicas que utilizan `render_store_header`. No requiere inicio de sesión y utiliza un historial separado del asistente administrativo.

- Consulta productos, marcas y categorías activos. Combina categoría, marca, presupuesto máximo y código; muestra hasta seis fichas con precio y disponibilidad consultados en MySQL.
- Las consultas exactas se resuelven localmente. Para recomendaciones y conversación utiliza la configuración de Gemini del servidor, con una lista explícita de campos públicos. No consulta clientes, pedidos, usuarios ni informes administrativos.
- El seguimiento se deriva a `rastrear.php`. El chat no crea pedidos ni procesa pagos.
- La IA no recibe especificaciones técnicas que no se hayan consultado, ni puede prometer garantías, descuentos o fechas de entrega. Sus recomendaciones pueden contener errores; las fichas son la referencia para comprar.
- La clave permanece en el servidor. POST con CSRF, preguntas de hasta 500 caracteres, historial de dos intercambios y límites por sesión de 20 mensajes y 5 llamadas al proveedor por minuto, incluidos los reintentos.
- Si falla Gemini, conserva la respuesta del catálogo y lo informa. No presenta una respuesta local como si la hubiera generado el LLM.

Pruebas locales: `C:\xampp\php\php.exe scripts/pruebas_chat_cliente.php`. Utilizan SQLite en memoria y un transporte simulado, sin consumir API ni modificar la base del proyecto.

Antes de publicar la tienda en Internet se requiere un límite de consumo compartido por servidor o proveedor: las cuotas por sesión sirven para esta instalación local, pero una persona puede crear sesiones nuevas.
