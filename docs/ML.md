# Metodología de Machine Learning

## Objetivo y fuente de datos

El sistema utiliza la base MySQL/MariaDB de la tienda para segmentar inventario y clientes, pronosticar demanda e importes, y estimar riesgo de cancelación. La base combina los registros del proyecto con un histórico académico adicional, cuya generación probabilística es reproducible. Por tanto, las métricas describen este conjunto académico y no validan desempeño en un comercio real.

Los modelos no cambian el estado de los pedidos ni realizan pagos. Cada entrenamiento lee una instantánea consistente de las cuatro tablas utilizadas: productos, clientes, pedidos y detalle de pedidos. Los informes guardan conteos, fecha de corte, identificador de la instantánea y versiones de las bibliotecas.

## Preparación

- Los importes se convierten en variables numéricas; los cálculos operativos de compra se hacen en céntimos.
- El inventario usa productos activos y las ventas excluyen pendientes y cancelados.
- Las series incluyen días sin pedidos y terminan en el último día completo.
- Las agrupaciones utilizan log1p y estandarización para reducir diferencias de escala.
- El estado final de un pedido nunca se introduce como variable predictora de cancelación.

## Segmentación

K-Means compara entre dos y cinco grupos. El mayor Silhouette determina el número elegido. Los grupos se presentan con sus perfiles promedio: precio y stock para productos; días desde la última compra, número de compras y monto para clientes RFM.

Silhouette no es precisión: es una medida de cohesión y separación. Los clientes sin compras confirmadas se cuentan por separado. No se inventan edades ni etiquetas de abandono.

## Pronóstico temporal

Las variables son el valor del día anterior, el valor de hace siete días, la media previa de siete días, el día de semana y la tendencia. Todos los rezagos utilizan únicamente datos anteriores a la fecha predicha.

Se comparan Ridge, Random Forest Regressor, Huber robusto, Random Forest log, repetición semanal, mediana histórica y mediana por día de semana. Huber estandariza entradas y objetivo y reduce la influencia de errores extremos. Random Forest log entrena sobre log1p del objetivo y regresa a la escala original con expm1. Ambos conservan todos los registros. Esta última conserva la estacionalidad semanal y resiste valores extremos sin eliminar registros. El bloque final (al menos siete días o 20% del conjunto) se reserva para prueba. Los 7 a 21 días previos constituyen la validación que selecciona el modelo.

Validación y prueba simulan ventanas de hasta siete días: cada ventana empieza con un reajuste usando únicamente datos anteriores; dentro de la ventana los rezagos se alimentan con predicciones, no con observaciones futuras. Las ventanas siguientes pueden utilizar los valores reales de ventanas ya concluidas. La selección se fija antes de evaluar la prueba final. Después se reajusta con todo el historial para generar los próximos siete días.

El porcentaje destacado es **días dentro del margen de ±20%**: número de días con error absoluto menor o igual al 20% del valor observado, dividido entre días de prueba, multiplicado por 100. Si el real es cero, solo una estimación de cero cuenta como acierto. Es una tolerancia elegida para la demostración, no una probabilidad de éxito futuro ni una precisión universal. Se muestran también el número de días y ventanas. WAPE es la suma de errores absolutos dividida entre la suma de valores reales, multiplicada por 100; menor es mejor, puede superar 100% y no se calcula si todos los valores reales son cero.

MAE expresa el error absoluto promedio, RMSE penaliza más los errores grandes y R² compara con una referencia de variabilidad. R² puede ser negativo. El pico de pedidos del histórico inicial se conserva y puede aumentar el error; no se oculta para mejorar las métricas.

## Cancelaciones

Se utilizan solo pedidos con resultado conocido: Entregado y Cancelado. Las variables son importe, unidades, método de pago, día de semana y hora de creación. Random Forest utiliza 200 árboles y ponderación de clases.

StratifiedGroupKFold separa clientes para que un mismo cliente no aparezca en ambos conjuntos. Se reportan F1 para cancelación, exactitud balanceada, ROC AUC y Brier, además de una referencia de clase mayoritaria. ROC AUC mide discriminación, no equivale a un porcentaje de precisión. La puntuación de un pendiente es una estimación y no una certeza.

También se presentan porcentajes de alertas acertadas (precision), cancelaciones detectadas (recall) y exactitud balanceada. Las alertas se evalúan con el umbral fijo de 50%; no se elige usando resultados de prueba. La segmentación K-Means conserva Silhouette en su escala original: no se convierte en un porcentaje de acierto.

## Histórico adicional

El lote academico_v1 comprende 270 días y una semilla fija. Sus patrones son crecimiento gradual, mayor actividad de fin de semana y fin de mes, preferencias por grupos de clientes y probabilidades de cancelación asociadas al importe, unidades, forma de pago y hora. Las etiquetas se generan probabilísticamente: el ML no recibe la regla ni una columna con la respuesta.

Esta generación facilita una demostración controlada y una explicación de metodología. Para evaluar un negocio real se necesitarían datos observados, definición de etiquetas, seguimiento temporal y validación externa.

## Pruebas

Las pruebas Python comprueban ausencia de fuga temporal en los rezagos, rechazo de historiales cortos, predicciones no negativas, clustering degenerado, clientes sin compras, exclusión de pendientes del entrenamiento supervisado y separación de clientes. Las pruebas PHP usan tablas temporales para verificar compras, stock, filtros, validación y clasificación del catálogo.
