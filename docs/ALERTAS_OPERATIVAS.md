# Alertas de compras y pedidos demorados

## Stock después de una compra

Al registrar una compra, se conserva el stock resultante de cada producto.
Si queda entre 0 y 5, se guarda un aviso en `alertas_operativas` dentro de la
misma transacción del pedido. Si la compra falla, ambos se deshacen. Tras el
commit se intenta un envío; los demás quedan para siguientes revisiones.
Un pedido Pendiente reserva/descuenta stock en este sistema: no se afirma
que esté pagado o confirmado. El texto de Telegram lo aclara.

## Pedidos demorados

El plazo predeterminado es de **5 minutos** para la demostración de entrega rápida.
Se puede ajustar con `PEDIDOS_DEMORA_MINUTOS` (entero positivo hasta 525600).
Se conserva compatibilidad con `PEDIDOS_DEMORA_HORAS` si no se define minutos.
Solo se consideran pedidos en estado Pendiente cuya fecha de creación supera
el umbral. No se calcula tiempo desde el último cambio de estado. Se registra
un aviso por pedido, no un recordatorio diario. Si ya fue atendido al enviar,
se descarta. No se incluyen nombres, teléfonos ni direcciones en Telegram.

La primera revisión guarda en MySQL la fecha de activación y solo considera
pedidos creados a partir de ella, excluyendo el histórico anterior. Abre el
dashboard tras desplegar y antes de crear el pedido de demostración.
Se detectan hasta 50 nuevos por revisión y se envían hasta 5 avisos por ejecución.
Cada visita al dashboard
revisa automáticamente; también lo hacen las revisiones con envío desde Agentes
y el comando `php scripts/ejecutar_agentes.php --enviar-telegram`.

En Render Free no existe un reloj independiente configurado: sin visitas al
dashboard o ejecuciones del comando, no se revisan demoras. La tarea Windows
sigue perteneciendo al equipo local y su base configurada, no al contenedor remoto.

## Entrega y almacenamiento

La tabla se crea automáticamente si no existe; el usuario MySQL necesita permiso
CREATE TABLE. No se modifican las tablas de negocio existentes. Se usa una clave
única por evento y una actualización condicional para evitar envíos concurrentes.
Los rechazos explícitos se conservan pendientes y pueden reintentarse en otra
revisión después de cinco minutos. Los timeouts inciertos y envíos interrumpidos
en estado En curso requieren revisión: no se reenvían a ciegas. No se garantiza
entrega exactamente una vez. Cambiar de destinatario no reenvía eventos antiguos.

Este registro MySQL cubre estas dos alertas nuevas; el flujo anterior de edición
manual/inventario conserva su registro en archivos. Una revisión de inventario
posterior puede notificar también el mismo stock bajo: son fuentes distintas,
todavía no unificadas en un único registro.

Pruebas: `C:/xampp/php/php.exe scripts/pruebas_alertas_operativas.php`.
SQLite temporal y transporte simulado; no envía mensajes reales.
