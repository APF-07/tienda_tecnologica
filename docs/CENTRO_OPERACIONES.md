# Predicción frente a realidad y acciones del día

Entrar en Administración → Centro de operaciones (`admin/operaciones.php`).
Actualiza cada 30 segundos mientras la pestaña está visible. Se pausa al abrir
un formulario de seguimiento para no perder lo escrito. No ejecuta Telegram ni
entrenamiento en cada refresco.

## Evidencia prospectiva

Ventas y pedidos: `ml_pronosticos` guarda los estimados originales y la referencia;
`ml_pronosticos_auditoria` registra la fecha de captura y corte. Solo se archivan
fechas futuras de informes vigentes. Los registros antiguos sin auditoría se
conservan pero no se cuentan como evidencia prospectiva. No se reconstruyen
predicciones pasadas ni se muestra un cero como error cuando aún falta el real.

Al terminar un entrenamiento desde el administrador, ejecutar los agentes o abrir
este centro se archivan pronósticos futuros y se evalúan fechas ya cerradas.
Las ejecuciones automáticas existentes de los agentes hacen ese trabajo también;
requieren servidor y programador activos. No se ha añadido otro programador.

Se fija el observado en la primera revisión posterior al cierre del día (hora de
la aplicación, America/Lima). Se usan los estados disponibles en esa revisión;
no hay un historial de estados para reconstruir el estado exacto a medianoche.
Cambiar después un pedido no reescribe el observado archivado. Se procesan hasta
100 pronósticos vencidos por revisión, por lo que una cola grande puede requerir
varias revisiones. El pronóstico y su referencia nunca se sobrescriben.

La referencia repite el día de la misma semana más reciente que ya estaba completo
al archivar (7 o 14 días antes del objetivo). Ventas incluyen envío y estados
Confirmado/En preparación/Enviado/Entregado; no acreditan cobros. Pedidos incluyen
pendientes y excluyen cancelados. Se selecciona la primera captura por fecha
objetivo, hasta 60 fechas, para no multiplicar el mismo día por distintos modelos.
Se muestran MAE del modelo y de la referencia en unidades originales, sin convertir
el error en una supuesta probabilidad de acierto.

El gráfico usa series guardadas en MySQL. Los archivos ML solo son la entrada al
archivo; no se utilizan para reconstruir ni reemplazar sus valores antiguos.

## Acciones

`acciones_dia`: una clave por fecha/tipo/entidad. Inventario activo agotado o con
stock hasta cinco; pedidos pendientes con antigüedad superior al plazo configurado
(por defecto cinco minutos); informes de ventas/pedidos ausentes o desactualizados.
Prioridad crítica para agotados, alta para bajo stock/demoras y revisión para ML.

El seguimiento humano se guarda en `acciones_historial` con administrador, fecha,
estado y motivo de 5 a 500 caracteres, mediante POST autenticado y CSRF. Repetir
el mismo cambio de estado no duplica el seguimiento. Atendida/Descartada no se
reabren al refrescar. La condición desaparecida marca la tarea como no vigente y
resuelve pendientes automáticamente. Si reaparece una condición resuelta durante
el día, se reactiva. Un problema que persiste vuelve a generar tarea al día siguiente.
Las transiciones automáticas quedan en estado/vigencia/fecha del registro; el
historial de motivos corresponde a decisiones humanas. No se compran productos,
cambian pedidos ni envían mensajes al marcar una tarea.

Las tablas se preparan de forma aditiva e idempotente. No se borra ni se modifica
el catálogo ni el histórico de ventas. Pruebas: `scripts/pruebas_centro_operaciones.php`
usa SQLite y un reloj fijo, sin APIs, Telegram ni datos del proyecto.
