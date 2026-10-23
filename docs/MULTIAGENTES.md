> Versión vigente (16/10/2026): [Dashboard automático y cinco agentes](DASHBOARD_AUTOMATICO.md). Este documento conserva detalles de la versión anterior; las nuevas rutas usan registro común MySQL.

# Agentes y alertas

Acceso: Administración → Agentes y alertas. El administrador puede pulsar «Ejecutar revisión»; la tarea de Windows también realiza la revisión cada 15 minutos mientras se cumplen sus condiciones de ejecución.

Esta primera implementación es una arquitectura multiagente determinista: cada agente tiene objetivo, consulta herramientas del dominio y produce mensajes estructurados para el siguiente. No son tres conversaciones con Gemini ni se afirma que sean agentes LLM autónomos.

1. El coordinador organiza el flujo y registra los resultados, destinos y tiempos de cada agente.
2. Inventario consulta todos los productos activos con stock de 0 a 5, incluidos los agotados.
3. Análisis recibe los hallazgos y consulta unidades de pedidos confirmados, en preparación, enviados y entregados de los últimos 30 días. Excluye pendientes y cancelados. Prioriza agotados, después cobertura menor o igual a tres días y finalmente otros productos con stock bajo. La cobertura se calcula con media histórica, no con un modelo ML de agotamiento.
4. Notificaciones recibe la prioridad y prepara alertas locales. Deduplica por producto, stock y prioridad; una reposición que deja de cumplir el criterio resuelve la alerta.

## Stock recuperado

Cuando un producto con una alerta activa pasa de 0–5 a más de 5 unidades y sigue activo, se crea un aviso de recuperación con el stock anterior, el actual y el código del producto. Desactivaciones y eliminaciones cierran la alerta, pero no se anuncian como recuperaciones. Un aumento de 0 a 3 sigue siendo stock bajo.

El evento se conserva entre revisiones y ejecuciones sin Telegram para poder entregarlo después. El control de envíos evita volver a notificar una recuperación aceptada. Si el producto vuelve a stock bajo antes de la entrega, se descarta el aviso antiguo; un ciclo posterior de reposición puede generar otro aviso. El mensaje resumen prioriza las recuperaciones y siempre indica cuántas hay, incluso si se limita la vista a ocho productos.

El panel incluye una sección «Stock recuperado» con hasta 12 recuperaciones vigentes y el contador de recuperaciones nuevas de la última revisión. Las revisiones programadas de 15 minutos utilizan la misma lógica; no necesitan reinstalar la tarea. Solo detectan transiciones observadas entre revisiones, no cambios intermedios que ocurran y se reviertan antes de la siguiente ejecución.

La propuesta de reposición cubre 14 días al ritmo histórico con un mínimo objetivo de 6 unidades. No crea compras ni modifica stock. Los resultados tienen fecha de ejecución y pueden quedar desactualizados hasta la siguiente revisión.

Estado privado del módulo: `storage/multiagentes/estado.json`, ignorado por Git y bloqueado por Apache. Las revisiones utilizan bloqueo exclusivo y escritura temporal antes de reemplazar el estado. No crean ni alteran tablas de MySQL.

Telegram: configura `TELEGRAM_BOT_TOKEN` en `.env`, envía «Vincular TechStore» al bot y ejecuta `scripts/configurar_telegram.php --vincular`. Solo se vincula automáticamente un único chat privado con ese mensaje o «Hola TechStore»; si hay varios, se pide elegir el destinatario manualmente. El token y el chat nunca se muestran en el panel.

Con el destinatario configurado, el panel permite enviar una prueba o ejecutar la revisión con la casilla «Enviar alertas nuevas a mi Telegram». Los envíos agrupan hasta ocho productos en un mensaje y señalan el total del resumen. La aceptación se registra con el identificador del mensaje. Si hay timeout, el envío se marca incierto y no se repite automáticamente. Esto evita duplicar mensajes cuando el proveedor pudo haber aceptado uno sin devolver confirmación. Un estado «Enviado» indica aceptación de Telegram, no lectura por el destinatario.

El archivo privado `storage/multiagentes/envios.json` conserva la deduplicación entre revisiones. No debe borrarse para reintentar entregas inciertas sin revisar el chat.

La instalación local incluye una tarea de Windows llamada `TechStore - Alertas de inventario`, cada 15 minutos y al iniciar sesión. Ejecuta `scripts/revision_automatica.ps1` en una ventana oculta, con el usuario actual y privilegios limitados. No guarda contraseñas. Requiere equipo encendido, sesión iniciada, MySQL disponible e internet para Telegram. No arranca XAMPP ni modifica servicios del sistema.

El instalador reproducible es `scripts/instalar_alertas_automaticas.ps1`, con `-IntervaloMinutos 15` como valor predeterminado. Actualiza únicamente su propia tarea para evitar duplicados. Windows no inicia una segunda instancia si la primera sigue ejecutándose y limita cada ejecución a tres minutos. El módulo también mantiene su propio bloqueo de revisión.

El panel muestra el intervalo instalado y `storage/multiagentes/ultima_revision_automatica.json` con el resultado y la fecha de la última ejecución. Estos archivos están protegidos por Apache e ignorados por Git. La configuración mostrada refleja la instalación realizada por el script; si se cambia la tarea directamente en Windows, se debe revisar allí su estado real.

Para pausar o cambiar el intervalo, abre el Programador de tareas de Windows y busca el nombre anterior. Si se elimina o cambia de ubicación la carpeta del proyecto, hay que actualizar la tarea.

El punto de entrada PHP continúa disponible: `C:\xampp\php\php.exe C:\xampp\htdocs\tienda_tecnologica\scripts\ejecutar_agentes.php --enviar-telegram`. Sin esa opción solo registra la revisión localmente. Un resultado de Telegram no confirmado, rechazado o sin configurar produce salida distinta de cero para que Windows registre el fallo.

Verificación: `C:\xampp\php\php.exe scripts/pruebas_multiagentes.php`. Los casos usan SQLite en memoria, sin API externa ni cambios en la base real.
