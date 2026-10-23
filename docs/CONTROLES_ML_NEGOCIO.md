# Cinco controles de Machine Learning de TechStore

## Arquitectura y alcance

PHP/PDO sirve la tienda y la administración; MySQL/MariaDB conserva productos, categorías, marcas, pedidos y líneas. Python (pandas, numpy, scikit-learn, joblib y mysql-connector) lee una instantánea consistente. Los informes JSON se publican mediante reemplazo atómico y PHP los presenta sin ejecutar Python en cada visita. JavaScript dibuja gráficos SVG con tabla accesible. No se necesitan APIs externas para estos controles.

Se conservan los modelos anteriores, el asistente y los agentes. `admin/ml/controles.php` añade cinco funciones de negocio, distintas de los cinco algoritmos del selector de pronósticos. `ml/controles_negocio.py` publica `ml/cache_controles_negocio.json`. El entrenamiento completo existente ejecuta ambos procesos. Las sugerencias no cambian stock, no compran productos, no bloquean pedidos ni envían mensajes automáticamente.

Todos los datos de la base actual son académicos según el propietario. Evaluarlos no equivale a probar precisión con ventas reales de producción. La prueba etiquetada artificial de anomalías se identifica separadamente. No se rellenan métricas ausentes.

## Controles y criterios definidos antes de evaluar

| Control | Algoritmo y justificación | Separación / datos mínimos | Aprobación y límites |
|---|---|---|---|
| Ventas por producto | Random Forest regularizado global: aprende relaciones no lineales entre ventas previas, media y día semanal; alternativa semanal por producto | ≥56 días y ≥8 días con venta antes de validar. Últimos 7 días de prueba, 7 anteriores de selección; rezagos recursivos | MAE de prueba menor que referencia semanal. Se publican MAE y RMSE en soles sin envío. Importes históricos de las líneas, no precio actual. |
| Demanda e inventario | Random Forest global o referencia temporal seleccionada por MAE; relaciona unidades futuras y stock | Modelo existente con ≥70 días; validación y prueba separadas de siete días | Demanda: MAE. Alertas reales: no ejecutado si no se reconstruye stock disponible por fecha. La reposición es una propuesta, no una orden. |
| Anomalías | Isolation Forest: útil para priorizar rarezas sin etiquetas y múltiples escalas | ≥60 pedidos y ≥10 días; ajuste en primeras fechas y detección en últimas fechas, sin dividir un mismo día | Prueba sintética fija con 150 normales de entrenamiento y 30 normales / 10 anómalos de prueba; F1 ≥0.80 demuestra mecanismo. F1 real pendiente de etiquetas humanas. No identifica fraude ni analiza todos los movimientos manuales. |
| Recomendaciones | TF-IDF + coocurrencias: mezcla similitud textual y productos comprados juntos | ≥6 productos y ≥10 días; coocurrencias anteriores al corte, completar cestas posteriores ocultando un producto reproducible | Precision@5, Recall@5 y referencia de popularidad; aprobado si Recall@5 supera popularidad. Catálogo actual compartido: protocolo de catálogo conocido, no productos completamente nuevos. No mide conversión. |
| Categorías | TF-IDF + regresión logística balanceada: texto explicable y costo bajo; no usa ID de categoría como característica | ≥30 productos / ≥2 categorías / ≥6 grupos de texto. Separación por texto normalizado, variantes numéricas juntas | Accuracy superior a mayoría y F1 macro ≥0.50; precision y recall macro. Etiquetas actuales pueden estar mal. Modelo evaluado conservado sin reajustar con prueba; propuestas requieren revisión. |

`Aprobado` significa superar el criterio local descrito, no aprobación universal del negocio. `Fallido` significa que la evaluación se ejecutó y no superó el criterio; sus resultados se conservan para análisis. `No ejecutado` indica datos/etiquetas insuficientes para aprobar integralmente, aunque el componente operativo pueda funcionar. No se ajustan umbrales después de observar prueba.

## Prioridad recomendada

| Control | Utilidad | Datos disponibles | Dificultad | Mantenimiento | Validación | Beneficio esperado |
|---|---|---|---|---|---|---|
| Demanda/inventario | Alta: reposición | Unidades y stock; falta stock histórico fiable | Media | Medio | Parcial; registrar movimientos y snapshots | Evitar faltantes y compras excesivas, aún no medido |
| Ventas por producto | Alta: presupuesto | Líneas históricas disponibles | Media | Medio | Temporal con MAE/RMSE | Mejor planificación, sujeto a superar referencia |
| Anomalías | Media/alta: revisión | Operaciones sin etiquetas confiables | Media | Alto: etiquetar y revisar falsos positivos | Sintética ahora, negocio pendiente | Priorizar auditoría, sin acusar fraude |
| Recomendaciones | Media: descubrimiento | Cestas y catálogo disponibles | Media | Medio | Offline y después prueba A/B | Potencial mejora de conversión, aún no demostrada |
| Clasificación | Media: carga del catálogo | Texto y categorías actuales | Baja/media | Bajo/medio: revisar etiquetas | Catálogo reservado por grupos | Ahorro de clasificación manual, aún no medido |

Con los resultados medidos, priorizar **ventas por producto como control principal inicial**: dispone de importes históricos y evaluación temporal directa, y superó ligeramente la referencia semanal. La mejora observada es pequeña y no garantiza beneficio comercial; mantener revisión humana. No priorizar todavía demanda/inventario como decisión automática: su modelo no superó la mejor referencia y faltan etiquetas fiables de quiebre. Es la siguiente prioridad de desarrollo por su relación directa con reposición. Antes de automatizarla, reconstruir entradas/salidas y snapshots diarios, registrar alertas con fecha/modelo/stock y evaluarlas al cumplirse el horizonte. Medir precisión, recall y F1 de alertas solo cuando exista resultado observado fiable. Anomalías espera etiquetas humanas, recomendaciones debe superar popularidad y clasificación es útil pero de menor impacto diario en este catálogo ya categorizado. Los tres permanecen integrados para revisión y validación posterior.

## Revisión humana y seguridad

Las propuestas de categoría se confirman con sesión administrativa, POST y CSRF. El servidor toma la categoría del informe, no del navegador; verifica categoría anterior y huella del texto para evitar aplicar propuestas obsoletas. El cambio y el actor quedan en `ml_revision_categorias`; no se cambian categorías en lote. Reentrenar antes de revisar un producto modificado. Las recomendaciones públicas consultan disponibilidad actual y excluyen productos, marcas o categorías inactivos.

## Verificación reproducible

1. Abrir Administración → Cinco controles ML. Entrenar desde Control ML; esperar finalización.
2. Ventas: seleccionar producto y cambiar entre próximos siete días y prueba reservada. Comparar observado/estimado y revisar MAE/RMSE.
3. Inventario: revisar unidades, stock de instantánea, propuesta de reposición y limitación de validación de alertas.
4. Anomalías: abrir el pedido señalado y comprobar importe/unidades. Consultar fixture sintético y no confundirlo con F1 real.
5. Recomendaciones: revisar Precision@5/Recall@5 y popularidad; abrir producto en tienda y comprobar sugerencias disponibles.
6. Categorías: revisar conjuntos, métricas y propuestas; confirmar individualmente solo después de comprobar la categoría correcta.
7. Ejecutar `python -m unittest discover -s tests -p test_controles_negocio.py` y `php scripts/evidencia_controles_negocio.php`.

Resultados medidos: [tabla de validación](evidencias/controles-negocio.md) y [evidencia estructurada](evidencias/controles-negocio.json). La evidencia es una lectura fechada: regenerar tras cambios o nuevo entrenamiento. Pruebas sintéticas: casos normales, historial insuficiente, columnas faltantes, exclusión del producto origen y separación temporal. No sustituyen la evaluación del catálogo local.
