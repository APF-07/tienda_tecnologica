# Aplicar el control ML y avisos de proveedores

## Control ML

En `admin/ml/control.php`, seleccionar un modelo no ejecuta nada por sí solo.

- **Guardar y entrenar ahora** guarda los valores elegidos, llama al entrenamiento compartido y vuelve a mostrar los informes publicados al terminar.
- **Solo guardar** conserva las opciones para una ejecución posterior y redirige con GET, evitando reenviar el formulario al recargar.
- **Entrenar configuración guardada** ejecuta las opciones que ya están persistidas en MySQL.
- La casilla de entrenamiento diario controla la programación; la opción de modelo «Automático» controla la elección por MAE. Son decisiones diferentes.
- Los mismos datos y algoritmo pueden dar las mismas cifras. Se comprueba la nueva ejecución por fecha e historial, no forzando una variación del pronóstico.

## Proveedores y Telegram

Registrar un proveedor nuevo guarda, en una sola transacción, el proveedor y un aviso `proveedor_alta` en `alertas_operativas`. Después del commit se intenta enviar específicamente ese aviso al chat de Telegram ya configurado.

El mensaje incluye nombre y plazo, sin transmitir el contacto del proveedor. Editar no vuelve a generar el aviso de alta. El formulario tiene CSRF y un identificador de operación que evita repetir el alta por reenvío. La cola conserva el estado y los errores de entrega para su revisión posterior.

El alta no compra mercancía ni aumenta existencias. Para eso se registra una orden de reposición y luego su recepción física.

Se registró el proveedor de ejemplo **TecnoAndes Distribuciones**, activo y con plazo de dos días, sin contacto real. La interfaz confirmó que Telegram aceptó su aviso.

## Verificación

`scripts/pruebas_proveedores_alertas.php`: seis verificaciones SQLite, con Telegram simulado: atomicidad, mensaje sin contacto, edición sin duplicación, envío dirigido, aislamiento de otros avisos y rollback al fallar la cola.

También se comprobaron las pruebas existentes de gestión, alertas y control ML. La entrega real del alta fue comprobada mediante el resultado del servidor en el formulario.
