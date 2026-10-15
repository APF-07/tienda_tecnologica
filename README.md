# TechStore · Tienda tecnológica

Proyecto PHP 8.2 y MySQL/MariaDB para catálogo, carrito, pedidos y administración.

## Arquitectura y evidencias

- [Diagramas de arquitectura y flujo de agentes](docs/ARQUITECTURA.md).
- [Resultados de pruebas y guion de demostración](docs/EVIDENCIAS_PRUEBAS.md).
- [Salida reproducible de pruebas locales](docs/evidencias/pruebas-locales.txt).

## Iniciar en XAMPP

1. Inicia Apache y MySQL desde XAMPP.
2. Abre `http://localhost:8080/tienda_tecnologica/tienda.php` para el catálogo y `http://localhost:8080/tienda_tecnologica/index.php` para el acceso administrativo.
3. Para una instalación nueva, crea la base `tienda_tecnologica` e importa `database/tienda_tecnologica.sql`. Ese archivo contiene estructura y datos iniciales, no el volcado con información personal.
4. Si ya tienes una base existente, conserva sus datos y ejecuta desde esta carpeta:

```powershell
C:/xampp/php/php.exe scripts/actualizar_esquema.php
```

La migración agrega los campos que falten en pedidos y conserva los datos de entrega históricos. Es repetible. Ya se aplicó en la base local durante esta revisión.

## Configuración

La conexión acepta `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` y `DB_PASSWORD` como variables de entorno. Los valores locales predeterminados son localhost, 3306, tienda_tecnologica, root y contraseña vacía. En producción utiliza un usuario propio de MySQL y contraseña.

- `GOOGLE_MAPS_API_KEY`: opcional; se lee de `.env` o del entorno del servidor y activa el autocompletado de direcciones en el checkout. Configura las restricciones de dominio y API en Google Cloud. Sin clave, la dirección se ingresa manualmente.
- `BCP_CUENTA`, `BCP_CCI`, `BCP_TITULAR`: datos de transferencia. Con `APP_DEMO=false`, la transferencia queda deshabilitada si no hay cuenta configurada.
- `APP_DEMO`: configuración interna para la ejecución académica local (predeterminado: true). No añade avisos a la interfaz.

La moneda de presentación es soles (S/). Los importes originales se conservaron, sin conversión de divisas. Yape/Plin y transferencias son métodos manuales: registrar un pedido no acredita un abono bancario.

Para una instalación nueva, el usuario de ejemplo es `admin@tienda.com`, con contraseña inicial `password`, correspondiente al hash del SQL. Cámbiala antes de publicar. La base existente conserva sus propios usuarios.

## Flujo de compra

El carrito se guarda en sesión. El checkout valida datos y consulta precio, disponibilidad y stock bajo bloqueo. Si cambió el precio, pide revisar y confirmar el nuevo total. El pedido, sus detalles y la disminución de stock se guardan en una transacción.

Un correo puede comprar varias veces; cada pedido conserva su propio nombre, correo, teléfono y dirección de entrega. Cancelar devuelve stock una vez; reactivar exige existencias suficientes. Para rastrear se solicita número de pedido y correo de compra. La página de confirmación requiere una sesión autorizada para ese pedido.

## Machine Learning y analítica

Los indicadores operativos se consultan directamente en MySQL/MariaDB. Los modelos Python producen informes evaluados que aparecen junto a esos indicadores:

- **Inventario:** K-Means sobre precio y stock, con log1p, StandardScaler y selección de 2 a 5 grupos por Silhouette. Incluye reposición orientativa según ventas observadas.
- **Clientes:** segmentación RFM (recencia, frecuencia y monto) con K-Means y perfiles de cada grupo.
- **Pedidos y ventas:** Random Forest Regressor y Ridge frente a referencias semanales y mediana histórica. Se separan entrenamiento, validación y prueba temporal; se comparan MAE, RMSE y R². La prueba no se utiliza para elegir el modelo.
- **Cancelaciones:** Random Forest Classifier entrenado solo con pedidos entregados o cancelados. La validación separa clientes entre entrenamiento y prueba. Reporta F1, exactitud balanceada, ROC AUC y Brier.

Los pronósticos usan el último día completo como corte para evitar interpretar un día en curso como un día sin ventas. La fecha de entrenamiento y el corte aparecen en el panel. Las métricas corresponden al histórico disponible; una referencia estadística puede superar al ML y en ese caso se recomienda la referencia.

Instala las dependencias en el Python que utiliza Apache:

```powershell
python -m pip install -r ml/requirements.txt
python ml/procesar_todas_predicciones.py
```

Desde el administrador también puedes usar **Entrenar y actualizar**. La variable `PYTHON_BIN` permite seleccionar el ejecutable si Apache no encuentra el mismo Python que la terminal. Los resultados se guardan mediante reemplazo atómico en `ml/cache_todos_*.json`; los modelos se conservan en `ml/artefactos/`. Las rutas son relativas al proyecto y la conexión utiliza `DB_*`.

### Histórico académico

Se agregó un lote de 270 días, de 2026-01-15 a 2026-10-11: 180 clientes y 5,640 pedidos. Los registros anteriores se conservaron. El generador representa crecimiento, fines de semana, fin de mes, preferencias de clientes y cancelaciones probabilísticas basadas en características del pedido.

```powershell
python ml/generar_historial.py
```

La tabla `historial_lotes` registra la carga y evita duplicarla. Es una importación de operaciones históricas cerradas y no descuenta las existencias actuales. El generador utiliza una semilla fija y guarda su configuración; se documenta como conjunto académico y no como evidencia de ventas reales. Consulta [la metodología](docs/ML.md) para la sustentación.

## Catálogo y administración

Las categorías y marcas de los productos se normalizaron con respaldo. El generador de productos y los formularios administrativos respetan las reglas de `includes/catalogo.php`; por ejemplo, una laptop no se guarda como componente y los celulares del catálogo actual corresponden a marcas compatibles. Los respaldos se guardan en `database/respaldo_catalogo_*.json`.

Productos, pedidos y clientes tienen búsqueda, filtros, paginación y exportación CSV. La ficha del cliente permite consultar sus pedidos. Las imágenes faltantes utilizan ilustraciones por categoría.

## Comprobaciones

```powershell
C:/xampp/php/php.exe scripts/diagnostico.php
C:/xampp/php/php.exe scripts/pruebas.php
python -m unittest discover -s tests -p test_modelos.py
```

Las pruebas crean tablas temporales que ocultan las reales únicamente en la conexión de prueba. Comprueban compras repetidas, historial de entrega, stock, cancelaciones, precios cambiados, productos inactivos, CSV y analítica. No insertan compras en las tablas reales.

## Despliegue

El Dockerfile actual utiliza PHP 8.2 con Apache y PDO MySQL. Para entrenar desde un despliegue Docker también se debe instalar Python con `ml/requirements.txt` y configurar `PYTHON_BIN`; el entrenamiento desde la web fue comprobado en XAMPP local. Configura las variables de entorno y ejecuta la migración antes de desplegar el código actualizado sobre una base existente. Las carpetas de imágenes deben contar con almacenamiento persistente si tu proveedor utiliza un sistema de archivos efímero.

Las credenciales de servicios externos se configuran mediante variables de entorno y no se incluyen en esta documentación.

## Asistente del administrador

Abre `http://localhost:8080/tienda_tecnologica/admin/asistente/index.php` o selecciona **Asistente** en el menú lateral. El chatbot incorpora una conexión opcional con **Gemini 3.1 Flash-Lite** y consultas locales de respaldo. Configura `GEMINI_API_KEY` en `.env` para habilitar conversación libre. Para coste cero, utiliza un proyecto Free Tier sin facturación habilitada. Consulta la [guía de activación y privacidad](docs/GEMINI.md).

Las consultas locales permiten ver pedidos pendientes, estados, últimos pedidos, clientes, stock bajo y resultados ML. Gemini recibe un resumen limitado de estadísticas e informes ML y hasta tres intercambios de conversación. No ejecuta SQL ni modifica registros. Requiere sesión de administrador y CSRF. Cada respuesta identifica si fue generada por Gemini o por una consulta local y muestra su fuente y fecha.

Pruebas de consultas locales (MySQL con tablas temporales):

```powershell
C:/xampp/php/php.exe scripts/pruebas_asistente.php
```

Pruebas de integración Gemini sin conexión externa ni claves reales (SQLite temporal y respuestas simuladas):

```powershell
C:/xampp/php/php.exe scripts/pruebas_gemini.php
```
