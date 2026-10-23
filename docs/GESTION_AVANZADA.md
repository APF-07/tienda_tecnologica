# Gestión de la tienda

Administración → Gestión de la tienda (`admin/gestion.php`). Los módulos usan MySQL;
los formularios requieren autenticación, CSRF y un identificador de operación de
un solo uso en la sesión. No se añadieron APIs de pago ni servicios de proveedores.

## Inventario

`movimientos_stock` registra apertura, ventas, ajustes, altas, cancelaciones,
reactivaciones, reposiciones y devoluciones. Incluye saldo anterior, variación,
saldo nuevo, motivo, referencia, fecha y usuario (0 para sistema/cliente).
El saldo de apertura es una observación desde la activación, no un historial
reconstruido. Una diferencia externa al registro se anota como conciliación.
Se bloquea primero el producto y se relee el saldo vigente; el último movimiento
se lee con bloqueo para evitar una instantánea antigua bajo REPEATABLE READ.
Movimiento, cambio de stock y alerta se confirman en la misma transacción.
Las notificaciones se intentan después de confirmar, sin deshacer la operación.

La sugerencia del agente de Análisis usa ventas confirmadas de 30 días, el plazo
actual del último proveedor activo utilizado y siete días de cobertura. Sin
proveedor usa 14 días. Objetivo mínimo seis unidades, descontando stock y unidades
de órdenes abiertas. Es una regla histórica, no una nueva predicción ML. Se deben
comprobar fechas de llegada: una orden abierta no garantiza entrega puntual.

## Proveedores y reposición

`proveedores`: nombre, contacto opcional, plazo y estado activo.
`compras_reposicion`: una línea por orden, cantidad, costo unitario en céntimos,
plazo capturado, fecha y administrador. No envía la orden a terceros ni registra
un pago. Crear una orden no aumenta stock.
`recepciones_compra`: entradas físicas parciales o completas, referencia y usuario.
Una referencia de entrega no puede repetirse en la misma orden; nunca se recibe
más de lo pendiente. Cancelar una orden conserva lo ya recibido y cierra el saldo
pendiente; se guarda el motivo en `gestion_eventos`.

## Devoluciones y reembolsos

Solo pedidos entregados y productos realmente incluidos en ellos. Las solicitudes
reservan unidades; se impide devolver más de lo vendido. Rechazar libera esa reserva.
Al recibir físicamente se decide si está apto para reintegrarse al catálogo. No
se reintegra dos veces. Recepción/rechazo registran usuario y observación.
Un pedido con devolución no rechazada no puede cambiar a otro estado, evitando
que una cancelación vuelva a devolver todas las unidades.

`reembolsos` registra una operación de dinero ya realizada fuera del sistema,
con referencia única y céntimos exactos. Se admite un registro por devolución,
solo después de recibirla y hasta el precio original por unidades devueltas.
No incluye envío. Si una línea duplicada del producto tiene varios precios, se
usa el menor como máximo conservador. No ejecuta transferencias ni verifica bancos.
Los importes devueltos se muestran separados; el ML de ventas conserva su objetivo
de importes confirmados brutos, sin reinterpretarlo silenciosamente como ingreso neto.

## Roles

`usuarios_roles` contiene el rol efectivo; se conserva el ENUM antiguo de usuarios
para compatibilidad. Las cuentas nuevas se crean con hash de contraseña y asignación
de rol en una sola transacción. Las cuentas previas conservan su rol existente.
`roles_historial` audita asignaciones; `roles_control` serializa cambios para impedir
que dos cambios simultáneos eliminen al último administrador.

- Administrador: todas las secciones y operaciones.
- Vendedor: pedidos, clientes, consulta y solicitud de devoluciones.
- Almacén: inventario, recepción de reposiciones y recepción/rechazo de devoluciones.

Los permisos se validan en servidor por ruta y por operación, y se relee usuario
activo/rol en cada solicitud. No basta con ocultar botones. No se han creado cuentas
de ejemplo ni cambiado permisos de usuarios existentes.
También se pueden desactivar y reactivar cuentas. No se permite desactivar al
último administrador. Los productos con historial se conservan mediante estado
Inactivo; se impide eliminarlos físicamente para no dejar recepciones huérfanas.

## Resumen diario

En la primera apertura por un administrador se habilita para comenzar al día
siguiente desde las 09:00, hora Perú. La pestaña Resumen diario permite cambiar
hora o desactivar. Se informa sobre el día anterior cerrado y los pendientes/stock
actuales al generar. No se encolan resúmenes atrasados de varios días.

La clave diaria evita duplicados aunque haya varias ejecuciones. Reutiliza
`alertas_operativas`, bot vinculado, cola, prioridad del resumen y reintentos.
Al desactivar se descartan resúmenes pendientes antes de enviarlos. Una respuesta
de entrega incierta sigue requiriendo revisión para no duplicar mensajes.

La tarea Windows `TechStore - Alertas de inventario` revisa cada 15 minutos. Requiere
sesión iniciada, equipo despierto y XAMPP/MySQL activos. No se garantiza envío con
el equipo apagado. No se necesita mantener abierto el navegador.
Se reinstaló el 19/10/2026 porque no estaba registrada; se comprobó la primera
ejecución con resultado 0 y estado Ready.

## Instalación y pruebas

Las tablas se crean de forma aditiva. También puede ejecutarse
`C:\xampp\php\php.exe scripts/preparar_gestion_avanzada.php`; no envía mensajes ni
cambia stock. La página muestra como máximo las 40 órdenes/devoluciones/recepciones
más recientes; el libro de inventario sí incluye paginación y filtro por producto.
Los registros completos se conservan en MySQL.

`scripts/pruebas_gestion_avanzada.php`: 33 comprobaciones con SQLite y sin servicios
externos. `scripts/pruebas.php`: 40 comprobaciones con tablas temporales de MySQL,
incluyendo compra/cancelación y recepción/devolución/reembolso reales en esas tablas.
Las tablas temporales ocultan todas las tablas comerciales afectadas para no escribir
datos de prueba en el catálogo ni el histórico real del proyecto.
