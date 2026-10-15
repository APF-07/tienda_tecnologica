# Evidencia de pruebas y demostración

Fecha de verificación local: **15 de octubre de 2026** (America/Lima).

## Resultados ejecutados

| Suite | Comprobaciones correctas | Qué acredita |
| --- | ---: | --- |
| scripts/pruebas_alerta_guardado.php | 5 | Recuperación 0 a 15, stock bajo 15 a 3, sin aviso si no cambia, desactivación y manejo de error del proveedor |
| scripts/pruebas_telegram.php | 14 | Construcción de mensajes, destinatario configurado, deduplicación, timeout, rechazo y recuperación |
| scripts/pruebas_destino.php | 6 | Coordenadas/place ID, URL de navegación, direcciones anteriores y rechazo de coordenadas inválidas |
| scripts/pruebas_multiagentes.php | 19 | Flujo del coordinador, reglas de prioridad, exclusión de ventas no válidas, recuperación y ausencia de cambios de stock |
| scripts/pruebas_gemini.php | 13 | Contexto limitado, protección de contactos/credenciales, cuotas, fallback y respuesta con transporte simulado |
| scripts/pruebas_asistente_integral.php | 26 | Consultas exactas por marca/categoría, privacidad, filtros y ausencia de escritura en base |
| tests/test_modelos.py | 11 | Pruebas unitarias de preparación y evaluación de modelos |
| **Total** | **94** | Todas las suites indicadas terminaron con código 0 |

Salida PHP conservada en [pruebas-locales.txt](evidencias/pruebas-locales.txt).
Salida de ML en [pruebas-ml.txt](evidencias/pruebas-ml.txt).
Son pruebas locales; no acreditan rendimiento, conectividad de Render, aceptación
real de Telegram, funcionamiento de la clave Maps ni calidad de respuestas reales
de Gemini. Los proveedores externos de estas suites PHP son simulados.
Las pruebas unitarias ML tampoco sustituyen las métricas del entrenamiento.

## Reproducir las pruebas

Desde PowerShell en la raíz del proyecto:

```powershell
.\scripts\generar_evidencias.ps1
python -m unittest discover -s tests -p test_modelos.py
```

El script genera la salida PHP y devuelve error si falla alguna suite.
Requiere PHP de XAMPP con SQLite, mbstring y extensiones del proyecto; Python
necesita las dependencias de `ml/requirements.txt`. No hace falta configurar
credenciales externas para estas suites ni modificar datos de negocio.

## Verificación manual en Render: pendiente de evidencia formal

El usuario informó previamente que el envío manual a Telegram funciona. No se
ha vuelto a verificar aquí la versión pública. Completar la tabla durante la
exposición, anotando fecha, versión desplegada y resultado observado; no marcar
como aprobado únicamente porque el servicio figure Live.

| Caso | Procedimiento | Resultado esperado | Evidencia a capturar |
| --- | --- | --- | --- |
| Catálogo | Filtrar Celulares; combinar con una marca sin coincidencias | Solo celulares y resultado vacío si la combinación no existe | Filtros y listado visibles |
| Compra | Completar un pedido de prueba | Pedido y detalles guardados; stock descontado una vez | Número de pedido y stock antes/después, sin datos de contacto |
| ML | Entrenar y abrir los informes | Fecha de corte, métricas y proyecciones disponibles | Informe con periodo y métrica visible |
| LLM | Preguntar por una explicación abierta del ML | Respuesta identificada como generada por Gemini, si el proveedor está disponible | Pregunta, respuesta y estado del proveedor |
| Coordinador | Ejecutar revisión de agentes | Traza Inventario → Análisis → Notificaciones | Panel con agentes, prioridades y resultado |
| Stock bajo inmediato | Guardar un producto activo de prueba de 15 a 3 desde Editar producto | Aviso de stock bajo después de guardar, sin ejecutar otra revisión | Confirmación del admin y mensaje con SKU/hora |
| Recuperación inmediata | Guardar el mismo producto de 3 a 15 | Aviso de recuperación con stock anterior y actual | Confirmación del admin y mensaje con SKU/hora |
| Sin cambio | Guardar de nuevo el mismo stock | No se solicita otro aviso inmediato | Stock sin cambio y comprobación del chat |
| Destino | Seleccionar sugerencia al comprar; abrir el pedido | Coordenadas conservadas y enlace hacia el punto seleccionado | Destino esperado y destino abierto, sin dirección personal |

Utilizar datos de prueba autorizados. Anotar el stock inicial y restaurarlo al
final, teniendo en cuenta que la restauración puede generar otro aviso.
No capturar tokens, contraseñas, identificadores privados del chat ni datos de
contacto. No incluir `.env` como evidencia.

## Cómo explicar los resultados ML

- Mostrar el informe producido por el entrenamiento desplegado; no reutilizar
  un porcentaje antiguo como si correspondiera a los datos actuales.
- Pronósticos: explicar MAE/RMSE y días dentro del margen de ±20%; ese porcentaje
  no es la probabilidad de acertar mañana.
- Segmentación: Silhouette mide separación/cohesión, no precisión porcentual.
- Cancelación: explicar las métricas de clasificación y la separación de datos.
- Los datos son académicos: el resultado no acredita eficacia en una tienda real.

## Alcance pendiente

Persistencia de archivos en Render Free, programador periódico en la nube,
reintentos durables y validación integral de la versión desplegada. El aviso al
guardar es inmediato y distinto de una tarea cada 15 minutos. Los agentes son
componentes deterministas coordinados; no se presentan como agentes LLM autónomos.
