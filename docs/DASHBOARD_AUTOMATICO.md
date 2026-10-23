# Dashboard automático y cinco agentes

Actualización local: 16/10/2026.

## Dónde verlo

- `http://localhost:8080/tienda_tecnologica/admin/dashboard.php`
- Agentes y alertas: traza completa de los cinco agentes y revisión manual.
- Asistente: pregunta «¿Qué debería atender primero hoy y por qué?».
- Analítica y proyección: modelos, evaluación temporal y entrenamiento separado.

El dashboard lee indicadores cada 30 segundos y solicita una ejecución de
agentes cada 60 segundos mientras está visible y la casilla está activa.
El servidor limita la ejecución automática a una por minuto entre pestañas.
Las lecturas GET no envían Telegram ni entrenan; la ejecución POST requiere
sesión de administrador y token CSRF. Al ocultar la pestaña se pausan sus
solicitudes. Fuera del panel, la tarea de Windows puede seguir ejecutando el
mismo comando según su horario; no se cambió su programación en esta actualización.

## Cinco agentes reales del coordinador

| Agente | Entradas | Resultado |
| --- | --- | --- |
| Inventario | Productos activos | Stock de 0 a 5, agotados |
| Seguimiento | Estado y fecha de pedidos | Pendientes fuera de 5 minutos, muestra limitada y total |
| Proyección | Informes ML evaluados | Pronósticos de pedidos/ventas con vigencia y fechas |
| Análisis | Inventario, seguimiento, proyección y ventas de 30 días | Prioridades con motivos y enlaces, reposición basada en media histórica |
| Notificaciones | Hallazgos y estado anterior | Diferencias de stock y recuperaciones; entrega coordinada mediante cola MySQL |

Son agentes especializados deterministas. El LLM explica sus resultados con
contexto limitado; no ejecuta cambios de stock ni reemplaza las métricas del ML.

## Registro unificado

Se crean de forma aditiva estas tablas si no existen:

- `agentes_estado`: última revisión y control de ejecución automática.
- `agentes_ejecuciones`: inicio, final, estado, resumen y errores genéricos.
- `agentes_inventario`: último stock observado y número de transición.
- `alertas_operativas`: cola común de ventas, edición, inventario y demoras.
- `alertas_configuracion`: fecha de inicio del monitoreo de demoras.
- `ml_pronosticos`: predicciones archivadas y resultados posteriores.

No se eliminan pedidos ni productos. `scripts/actualizar_agentes.php` prepara
el esquema desde CLI sin enviar mensajes. El estado JSON anterior se puede
leer para inicializar la revisión, pero MySQL es la fuente de estado principal.
Los archivos antiguos de envío no se importan ni se reinician. La primera
observación del inventario establece una base y no reenvía masivamente el histórico.

Ventas y edición registran las transiciones junto con la transacción de negocio;
una revisión posterior ve el mismo stock y no crea otro aviso. El envío ocurre
después del commit y un rechazo del proveedor no revierte una compra. El panel
puede enviar hasta un aviso pendiente por revisión web; CLI hasta cinco.
Los errores explícitos se reintentan en revisiones siguientes tras cinco minutos.
Los timeouts inciertos no se reenvían automáticamente. No se garantiza entrega
exactamente una vez ante una interrupción entre Telegram y la confirmación local.

La API nueva no incluye contactos de clientes. Los avisos antiguos de stock por
archivos siguen teniendo funciones de compatibilidad, pero los formularios y el
coordinador de esta versión usan la cola MySQL.

## ML y evaluación

Los modelos conservan comparación con referencias, validación temporal y
advertencias por historial insuficiente. Se reentrenaron los cuatro informes
locales y el panel lee sus fechas/métricas sin volver a entrenar.

Ahora se archivan pronósticos para días posteriores a la fecha de observación.
Una vez cerrado el día, se guardan el valor observado y los errores del modelo
y de la referencia semanal. No se crean evaluaciones retrospectivas ficticias
para fechas anteriores a la activación. Al inicio no habrá días evaluados.
El valor observado usa el estado de los pedidos en la primera evaluación; cambios
posteriores de estado pueden diferir de esa instantánea. Cada versión de pronóstico
se evalúa por separado; días únicos y cantidad de pronósticos no son equivalentes.

## Verificación

Ejecutadas 114 comprobaciones en las suites reproducibles (103 PHP y 11 Python),
más 35 comprobaciones de compras/gestión con tablas temporales de MySQL: **149**.
También se comprobó el dashboard en navegador y una respuesta real de Gemini con
las prioridades del coordinador. Esas pruebas no prometen exactitud universal del LLM.

La operación automática requiere XAMPP/MySQL activos. La actualización del panel
no equivale a un proceso permanente si se cierra la pestaña y no hay tarea Windows.
