# TechStore · Tienda tecnológica

Sistema académico de comercio electrónico y administración de una tienda tecnológica, desarrollado con **PHP, MySQL/MariaDB, Python y JavaScript**. Integra catálogo, compras, inventario, analítica, Machine Learning, asistentes con un LLM y agentes que automatizan tareas de supervisión.

Los importes se presentan en **soles (S/)**. La base utilizada en las evaluaciones contiene datos académicos; sus métricas no demuestran precisión en un negocio real.

## ¿Qué hace el sistema?

### Tienda para clientes

- Catálogo con búsqueda y filtros por categoría y marca.
- Detalle de productos, disponibilidad e imágenes.
- Carrito, checkout y registro de pedidos con validación de precios y existencias.
- Métodos de pago registrados: Yape/Plin, tarjeta y contraentrega, según la configuración del proyecto. Registrar un método o pedido no acredita un pago recibido ni una integración bancaria automática.
- Dirección de entrega y autocompletado opcional con Google Maps.
- Seguimiento del pedido mediante su número y correo de compra.
- Chatbot para orientar sobre la tienda y recomendaciones de productos relacionados.

### Administración

- Gestión de productos, categorías, marcas, clientes y pedidos.
- Dashboard de ventas, pedidos e inventario con consulta automática de datos.
- Búsquedas, filtros, paginación, exportaciones CSV e historial de pedidos.
- Movimientos de inventario, proveedores, reposiciones, recepciones y devoluciones.
- Usuarios con roles de administrador, vendedor y almacén.
- Asistente administrativo, resultados ML, centro de acciones y seguimiento de agentes.
- Alertas de Telegram cuando el bot, el destinatario y la automatización están configurados.

## Acceso al sistema

Con Apache en el puerto **8080**:

| Apartado | Dirección local |
|---|---|
| Tienda | http://localhost:8080/tienda_tecnologica/tienda.php |
| Inicio de sesión administrativo | http://localhost:8080/tienda_tecnologica/index.php |
| Panel administrativo | http://localhost:8080/tienda_tecnologica/admin/dashboard.php |
| Cinco controles ML de negocio | http://localhost:8080/tienda_tecnologica/admin/ml/controles.php |
| Configuración y entrenamiento ML | http://localhost:8080/tienda_tecnologica/admin/ml/control.php |
| Dashboard de predicciones | http://localhost:8080/tienda_tecnologica/admin/ml/predicciones.php |
| Asistente administrativo | http://localhost:8080/tienda_tecnologica/admin/asistente/index.php |
| Agentes y alertas | http://localhost:8080/tienda_tecnologica/admin/agentes/index.php |

### Credenciales de prueba

**Base local del proyecto utilizada por el autor:**

| Rol | Correo | Contraseña de prueba |
|---|---|---|
| Administrador | `admin@admin.com` | `123456admin` |

**Instalación nueva importando únicamente `database/tienda_tecnologica.sql`:**

| Rol | Correo | Contraseña inicial del SQL |
|---|---|---|
| Administrador | `admin@tienda.com` | `password` |

Son accesos de prueba para dos bases diferentes. Importar el SQL inicial no crea automáticamente el usuario de la base local del autor. Una base existente conserva sus propios usuarios. No hay una contraseña compartida para los clientes: el flujo de compra solicita sus datos de contacto. Cambia los accesos de prueba si publicas una instancia accesible por Internet.

## Machine Learning: ¿qué hace en la tienda?

El **ML** aprende o calcula patrones a partir del historial y produce estimaciones evaluadas. Ayuda a planificar y revisar decisiones; no garantiza ventas, recompra ni fechas de agotamiento.

### Cinco controles de negocio

| Control | Función | Algoritmo / evaluación |
|---|---|---|
| **1. Ventas por producto** | Estimar ingresos de los próximos siete días por producto y contrastarlos con valores observados. | Random Forest regularizado frente a referencia semanal; MAE y RMSE sobre fechas reservadas. |
| **2. Demanda e inventario** | Estimar unidades necesarias, riesgo de insuficiencia y reposición orientativa. | Random Forest y referencias temporales; MAE. La validación de alertas reales requiere stock histórico fiable. |
| **3. Anomalías** | Señalar pedidos con importes o cantidades inusuales para revisión. | Isolation Forest. Precision, recall y F1 solo cuando existen etiquetas; el fixture sintético se identifica por separado. |
| **4. Recomendaciones** | Sugerir productos por similitud textual e historial de compras conjuntas. | TF-IDF y coocurrencias; Precision@5 y Recall@5 en cestas reservadas frente a popularidad. |
| **5. Clasificación** | Proponer categorías a partir del nombre y la descripción de los productos. | TF-IDF y regresión logística; accuracy, precision, recall y F1 macro con grupos de texto separados. |

Las categorías propuestas requieren **revisión y confirmación humana**. El servidor comprueba que el producto no haya cambiado desde el entrenamiento y registra quién confirmó el cambio.

Estos cinco controles son **funciones de negocio**. No deben confundirse con los cinco algoritmos disponibles para los pronósticos globales: **Ridge, Huber robusto, Random Forest, Extra Trees y Gradient Boosting**. «Automático» es un modo de selección y puede elegir una referencia estadística si obtiene menor error de validación.

También se conservan segmentación de inventario con K-Means, segmentación RFM de clientes, probabilidad de recompra y riesgo de cancelación de pedidos.

### Cómo interpretar los resultados

- Se separan entrenamiento, validación y prueba cuando corresponde; la prueba reservada no se utiliza para elegir el modelo.
- Los informes muestran fecha de publicación, corte de datos, métricas y limitaciones. Los pronósticos diarios usan días completos hasta ayer.
- **Aprobado:** superó el criterio local documentado; no equivale a precisión garantizada.
- **Fallido:** se evaluó, pero no superó el criterio. No significa que el botón o el panel estén averiados.
- **No ejecutado:** faltan datos o etiquetas para completar esa validación; el componente operativo puede estar implementado.
- La última evaluación documentada aprobó ventas por producto y clasificación sobre el catálogo académico. Demanda y recomendaciones no superaron sus referencias; anomalías sigue pendiente de etiquetas del negocio. Consulta la evidencia fechada para ver cifras y alcance.

La prioridad inicial recomendada es **ventas por producto**, por su disponibilidad de datos y evaluación temporal directa. Demanda e inventario es la siguiente prioridad de desarrollo, completando trazabilidad de stock y validación de alertas.

[Metodología, comparación y límites](docs/CONTROLES_ML_NEGOCIO.md) · [Tabla de resultados medidos](docs/evidencias/controles-negocio.md) · [Evidencia estructurada](docs/evidencias/controles-negocio.json).

## LLM: ¿qué hace el asistente?

El **LLM** permite conversar en lenguaje natural. El proyecto tiene integración configurable con **Gemini**, además de consultas locales de respaldo.

- El administrador puede consultar productos, categorías, marcas, clientes, pedidos, ventas y resultados ML disponibles.
- El asistente presenta fuentes, fechas y explica resultados; los totales y cálculos proceden de consultas controladas o informes publicados.
- El chatbot del cliente orienta sobre productos y consultas de la tienda.
- El LLM no recibe libre acceso a toda la base ni ejecuta SQL arbitrario. El asistente no modifica registros por conversación.
- Su disponibilidad depende del proveedor, el modelo configurado, la clave y las cuotas. Las consultas locales no equivalen a una respuesta del LLM.

Configura `GEMINI_API_KEY`, `GEMINI_ENABLED` y, opcionalmente, `GEMINI_MODEL`. El costo depende de la cuenta y las condiciones del proveedor. [Guía del asistente](docs/GEMINI.md).

## Multiagentes: ¿qué automatizan?

El sistema usa **cinco agentes de tareas**, un **coordinador** y un **auditor adicional**. Son componentes PHP con objetivos y mensajes definidos; no son cinco APIs ni cinco LLM independientes.

| Componente | Responsabilidad |
|---|---|
| **Coordinador** | Ordenar la ejecución, compartir los resultados y conservar la traza del proceso. |
| **1. Inventario** | Detectar productos activos agotados o con stock bajo de 0 a 5 unidades. |
| **2. Seguimiento** | Detectar pedidos que siguen pendientes después del plazo configurado. |
| **3. Proyección** | Consultar informes ML, comprobar vigencia e identificar riesgos estimados disponibles. |
| **4. Análisis** | Combinar inventario, ventas y condiciones de reposición para priorizar acciones revisables. |
| **5. Notificaciones** | Preparar avisos, detectar recuperación de stock y evitar duplicados. |
| **Auditor** | Revisar consistencia, stock negativo y vigencia; bloquear envíos ante errores y advertir sobre calidad ML. |

Los resultados e históricos de ejecución se conservan para revisión. Telegram puede recibir avisos de stock bajo, agotado, stock recuperado y reportes operativos según los eventos y la configuración. El envío requiere token, chat vinculado y autorización del auditor cuando interviene en el reporte.

La tarea local revisa periódicamente; también existen acciones manuales. **No funciona con el equipo o MySQL apagados.** El entrenamiento diario y la actualización visual del dashboard son procesos distintos: refrescar el panel no reentrena el ML.

[Arquitectura](docs/ARQUITECTURA.md) · [Dashboard y agentes](docs/DASHBOARD_AUTOMATICO.md) · [Centro de operaciones](docs/CENTRO_OPERACIONES.md).

## Tecnologías y estructura

| Tecnología | Uso |
|---|---|
| PHP 8.2, PDO y sesiones | Tienda, administración, autenticación, validaciones y consultas. |
| MySQL / MariaDB | Datos de negocio, historial y seguimiento operativo. |
| Python, pandas, NumPy, scikit-learn y joblib | Entrenamiento, evaluación y publicación de informes ML. |
| HTML, CSS y JavaScript | Interfaz adaptable, filtros, gráficos y actualización de datos. |
| Gemini / Telegram / Google Maps | Integraciones configurables de conversación, avisos y direcciones. |

```text
admin/       Administración, asistentes, ML, agentes y gestión
config/      Conexión y configuración de integraciones
includes/    Reglas de negocio, seguridad, consultas y vistas compartidas
ml/          Modelos Python, evaluación y archivos generados
css/ y js/   Estilos e interacción de la interfaz
database/    SQL inicial y migraciones
scripts/     Mantenimiento, automatización y pruebas PHP
tests/       Pruebas Python
docs/        Arquitectura, metodología y evidencias
uploads/     Imágenes de productos y recursos de la tienda
```

## Instalación local con XAMPP

1. Coloca el repositorio en `C:\xampp\htdocs\tienda_tecnologica`.
2. Inicia Apache y MySQL. Las direcciones anteriores suponen Apache en el puerto 8080; adapta el puerto a tu instalación.
3. Para una base nueva, crea `tienda_tecnologica` e importa `database/tienda_tecnologica.sql` desde phpMyAdmin. No importes encima de una base que quieras conservar.
4. Copia `.env.example` como `.env` y configura únicamente las integraciones que utilizarás.
5. Si actualizas una base existente, ejecuta la migración aditiva desde la raíz:

```powershell
C:/xampp/php/php.exe scripts/actualizar_esquema.php
```

6. Instala las dependencias ML en el Python utilizado por el servidor y genera los informes:

```powershell
python -m pip install -r ml/requirements.txt
python ml/procesar_todas_predicciones.py
```

También puedes entrenar desde **Configurar modelos ML → Guardar y entrenar ahora**. `PYTHON_BIN` permite indicar la ruta de Python si Apache no encuentra el mismo ejecutable que tu terminal. Los informes generados y artefactos excluidos de Git se regeneran al entrenar.

### Configuración del entorno

| Variable | Función |
|---|---|
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | Conexión a MySQL/MariaDB. |
| `PYTHON_BIN` | Ejecutable Python para el entrenamiento. |
| `GEMINI_API_KEY`, `GEMINI_ENABLED`, `GEMINI_MODEL` | Integración del LLM. |
| `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID` | Bot y destinatario de Telegram. |
| `GOOGLE_MAPS_API_KEY` | Autocompletado de dirección, con APIs y restricciones de dominio configuradas. |
| `PEDIDOS_DEMORA_MINUTOS` | Plazo para revisar pedidos pendientes; predeterminado en el agente: 5 minutos. |

Los valores locales predeterminados de la conexión son `localhost`, puerto `3306`, base `tienda_tecnologica` y usuario `root` sin contraseña. Usa credenciales propias fuera de esa instalación local. `.env`, tokens y claves de servicios externos no se incluyen en el repositorio.

### Automatización local

Para registrar la tarea de Windows de revisión periódica, ejecuta el instalador desde la raíz en PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/instalar_alertas_automaticas.ps1
```

El intervalo predeterminado es de 15 minutos. Requiere la sesión y los servicios indicados por el instalador. Comprueba la última ejecución y los errores en **Agentes y alertas**; registrar la tarea no prueba por sí solo que Telegram haya recibido mensajes.

## Pruebas y evidencia

```powershell
python -m unittest discover -s tests
C:/xampp/php/php.exe scripts/pruebas_control_ml.php
C:/xampp/php/php.exe scripts/pruebas_controles_negocio.php
C:/xampp/php/php.exe scripts/evidencia_controles_negocio.php
```

La última verificación documentada del trabajo de cinco controles registró **34 pruebas Python, 9 comprobaciones de confirmación de categorías y 18 del control ML existente**. Son comprobaciones de código; las métricas predictivas y sus estados se reportan por separado. Las pruebas de categorías usan SQLite temporal; las pruebas sintéticas no representan precisión comercial.

- [Salida de pruebas Python](docs/evidencias/pruebas-cinco-controles.txt).
- [Pruebas de confirmación de categorías](docs/evidencias/pruebas-confirmacion-categorias.txt).
- [Informe de cinco controles](docs/CONTROLES_ML_NEGOCIO.md).
- [Predicciones individuales](docs/PREDICCIONES_INDIVIDUALES.md).
- [Gestión de proveedores, movimientos y roles](docs/GESTION_AVANZADA.md).
- [Requisitos y demostración académica](docs/REQUISITOS_INGENIERO.md).

Desde el administrador, **Cinco controles ML → Informe técnico y criterios** permite consultar y descargar el informe. La evidencia exportada es una lectura fechada: regénérala después de entrenar si necesitas un informe actualizado.

## Despliegue y límites

El repositorio incluye Dockerfile con PHP/Apache, extensiones MySQL, Python y dependencias ML. Un despliegue necesita base de datos externa y variables de entorno propias. La automatización de Windows no se traslada automáticamente al servidor: configura allí su programador de tareas. Un proveedor con almacenamiento efímero necesita persistencia para las imágenes y los informes que quieras conservar.

El sistema es un apoyo a la gestión: las sugerencias de reposición, anomalías y pronósticos requieren revisión humana. No se presentan como certezas ni sustituyen comprobaciones de inventario, pagos o resultados reales.
