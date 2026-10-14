# Asistente administrativo: alcance de las consultas

El asistente consulta las seis áreas desde MySQL/MariaDB y los informes ML guardados. No requiere enviar la base completa a un proveedor. Las consultas son de solo lectura y usan SQL fijo y parámetros, nunca SQL producido por un modelo.

Ejemplos:

- «Muéstrame el catálogo de productos», «Productos inactivos», «Producto #501».
- «Productos por categoría», «Qué marcas hay registradas».
- «Pedido #514», «Pedidos entregados», «Métodos de pago».
- «Cliente #515», «Busca cliente Ana Pérez», «Correo de cliente #515», «Clientes sin compras».
- «Ventas por categoría este mes», «Ventas por marca hoy».
- «Analítica y proyección», «Segmentación de clientes», «Analítica de productos».

Los totales se calculan sobre los registros correspondientes; las listas del chat tienen límites explícitos: 30 categorías/marcas/grupos de ventas, 10 productos/clientes/pedidos y 20 líneas de un pedido. Los enlaces abren las pantallas para consultar el resto. El catálogo puede incluir inactivos para administración, mientras que las búsquedas anteriores de productos siguen indicando que solo consultan activos.

La información individual de clientes y pedidos se responde localmente. Estas respuestas y sus preguntas no se añaden al historial enviado a Gemini. Los contactos solo se muestran si se piden. El contexto externo contiene agregados, categorías y marcas (hasta 30), estado del inventario, métodos de pago, ventas de los últimos 30 días y resúmenes de los cuatro informes ML. No contiene contactos, contraseñas, claves ni registros individuales de clientes.

Los importes de pedidos no acreditan cobros recibidos. Las ventas agrupadas usan los subtotales históricos de líneas, excluyen envío, pendientes y cancelados, y se agrupan por la clasificación actual de los productos. Los informes ML indican su fecha de corte y no se reentrenan al preguntar.

No es un motor de SQL libre: ante una pregunta fuera de estas consultas, Gemini puede explicar los agregados disponibles, pero debe reconocer los datos que no tiene. Las listas no se deben interpretar como exhaustivas y los modelos no garantizan aciertos futuros.

Pruebas: `scripts/pruebas_asistente_integral.php`, con SQLite temporal y transporte de IA simulado.
