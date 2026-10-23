# Cinco predicciones operativas

Implementación local del 16/10/2026. Las cinco tareas son pronóstico de ventas,
pronóstico de pedidos, riesgo de cancelación, recompra de clientes y demanda por
producto con agotamiento estimado. La segmentación RFM y de inventario continúa
disponible, pero no se cuenta como predicción individual de compra.

## Dónde consultar

- Administración → Analítica y proyección abre el **Dashboard de predicciones**
  (`admin/ml/predicciones.php`), con cinco pestañas y gráficos interactivos.
- Ventas y pedidos: histórico observado de 30 días y pronóstico de siete días.
- Recompra: indicador porcentual y compras observadas del cliente seleccionado.
- Inventario: curvas de stock central, rápido y lento, demanda diaria y fecha de
  agotamiento estimada. Se guardan los valores diarios producidos por el modelo;
  no se reconstruyen curvas a partir de un total. Los escenarios no son intervalos
  de confianza calibrados. Si cambia el stock, se solicita entrenar de nuevo.
- Cancelación: porcentaje del pedido seleccionado y comparación de pendientes.
- Cada gráfico ofrece una tabla desplegable. Los controles permiten seleccionar
  un registro evaluado o consultar otro ID. Un registro sin evidencia suficiente
  no obtiene una predicción inventada. «Actualizar datos» relee el informe guardado;
  «Ver evaluación» lleva al informe y entrenamiento. Se indica corte y vigencia.
- Analítica → Clientes → Probabilidad de recompra en 30 días → introducir ID.
- Analítica → Productos → Demanda por producto y agotamiento estimado → introducir ID.
- Analítica → Pedidos → Riesgo de cancelación → introducir ID de un pedido puntuado.
- Asistente: «¿Qué probabilidad tiene el cliente #515 de volver a comprar?».
- Asistente: «¿Cuándo se agotará el producto #18?».
- Asistente: «¿Qué riesgo de cancelación tiene el pedido #123?».

Las consultas individuales leen resultados ML guardados y MySQL, sin pedir a
Gemini que invente probabilidades. Son de solo lectura. Las predicciones de
clientes y pedidos se marcan privadas y no se transmiten al proveedor LLM.
Gemini recibe únicamente métricas y disponibilidad agregadas para explicar modelos.

El dashboard usa el endpoint autenticado `admin/ml/predicciones_datos.php`, con
consultas de solo lectura y sin envío a proveedores externos. La suite
`scripts/pruebas_dashboard_predicciones.php` cubre nueve casos: informes ausentes,
separación entre observado y previsto, fidelidad de porcentajes y curvas, series
antiguas sin detalle diario, cambios de stock y pedidos que dejaron de estar pendientes.

## Recompra

Regresión logística sobre recencia, frecuencia e importe de los últimos 90 días,
intervalo medio y antigüedad. Los clientes necesitan al menos dos compras
confirmadas previas. Las fechas de observación están separadas 30 días y cada
etiqueta tiene 30 días posteriores completos. La última observación se reserva
para prueba; las etiquetas de entrenamiento terminan antes de esa observación.
La penúltima observación se reserva para calibración sigmoide y el entrenamiento
termina antes de ella. Se conserva el modelo y calibrador evaluados, sin reajustar
con la prueba. Las puntuaciones actuales se limitan al rango observado al calibrar
para evitar extrapolación sin respaldo; no se impone un techo porcentual manual.

Se evalúan clientes conocidos en fechas futuras; no se afirma generalización a
clientes nuevos. Se muestran Brier y AUC, además del Brier de una probabilidad
constante obtenida del entrenamiento. No se afirma calibración externa ni certeza
individual. Los porcentajes extremos se presentan sin redondearlos a una certeza
del 100 %. El horizonte corresponde al corte del informe, no a la hora de consulta.

Regla de publicación conservadora: cinco compras confirmadas, 30 días de antigüedad
y 30 casos en el rango de calibración (rangos de 20 puntos porcentuales). Si falta
alguno se conserva el cálculo interno para diagnóstico, pero el dashboard y el
asistente muestran «Evidencia limitada», sin porcentaje ni inclusión en el top.
Es una política de publicación, no una prueba de significación estadística individual.
Todos los clientes registrados están disponibles en el selector y tienen un gráfico
compacto de actividad mensual real de seis meses, incluso sin modelo o sin compras.
Los meses sin compras figuran en cero y se excluyen pendientes y cancelados.
El indicador de probabilidad se añade solo cuando la evidencia permite publicarlo;
la falta de probabilidad no deja el dashboard vacío. El último mes es parcial hasta
el corte indicado; estos datos históricos no se presentan como predicciones.

Evaluación actual: 826 observaciones de entrenamiento, 180 de calibración y 180
de prueba. Brier calibrado **0,1002**, sin calibrar **0,0976**, referencia constante
**0,1086**, AUC **0,7503**. La calibración no mejoró el Brier frente a la regresión
original en esta prueba; ambas superan la referencia constante. No se afirma una
mejora de precisión. La protección principal añadida es no publicar estimaciones
individuales con evidencia limitada y hacer visible su respaldo.
En calibración recompró el **91,11 %** de los elegibles: este patrón del histórico
académico favorece porcentajes altos y limita extrapolar el modelo a una tienda real.
Estas métricas son del conjunto académico y pueden cambiar en otro entrenamiento.

## Demanda y agotamiento

Modelo global de unidades diarias compartido por productos. Usa valores anteriores
de 1 y 7 días, medias anteriores de 7 y 28 días y día de semana; no utiliza stock
actual como variable de entrenamiento. Productos con al menos 56 días de serie,
8 días de venta y 20 unidades antes de la validación; se exige un historial global
de al menos 70 días para disponer de variables y periodos separados.

Se selecciona entre Random Forest, repetición semanal y media de siete días usando
siete días de validación. Se reajusta hasta el inicio de prueba y se evalúa en
otros siete días. Las predicciones de varios pasos son recursivas, sin leer valores
reales del futuro. Después se reajusta y pronostica 30 días.

Se acumulan unidades previstas hasta alcanzar el stock observado. Si no lo alcanza
en 30 días, no se inventa una fecha. Los escenarios rápido/lento suman/restan el
MAE diario individual de prueba: son sensibilidad orientativa, no intervalos
calibrados ni fechas garantizadas. Si el stock cambió desde el entrenamiento, se
indica que debe actualizarse en lugar de presentar esa fecha como operativa.

Resultado local: **72 productos**, Random Forest seleccionado, MAE diario de prueba
**0,577 unidades**, referencia semanal **0,938** y media de siete días **0,874**.
Es un promedio sobre pares producto-día, no un porcentaje de acierto. Los productos
sin suficientes ventas no reciben fecha. El stock cero se identifica como agotado,
no como una predicción de agotamiento futuro.

Limitaciones: no hay historial de disponibilidad, reposición ni cambios de estado
de pedidos. Se usan los estados actuales disponibles de los registros históricos.
Ventas observadas pueden estar limitadas por falta de stock y no equivalen a
demanda no satisfecha. El supuesto de fecha es ausencia de reposición.

## Integración y pruebas

El agente de proyección entrega métricas y número de resultados individuales.
Para el análisis señala productos con posible agotamiento en siete días solo si
el informe es reciente y el stock actual coincide con el utilizado por el modelo.
No cambia stock ni realiza compras automáticamente.

```powershell
python -m unittest discover -s tests -p test_predicciones_individuales.py
C:/xampp/php/php.exe scripts/pruebas_predicciones_individuales.php
```

Se ejecutaron siete pruebas Python nuevas y diez PHP: ausencia de fuga en las
variables, prueba temporal aislada de la selección, insuficiencia de historial,
no invención de fechas, consultas privadas y aclaración cuando falta el ID.
También pasaron las 11 pruebas Python anteriores, 30 del asistente integral,
seis del comprador frecuente y siete del ranking de clientes. La navegación real
comprobó las consultas de recompra y agotamiento y la respuesta del asistente.
