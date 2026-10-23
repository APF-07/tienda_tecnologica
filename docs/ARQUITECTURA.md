# Arquitectura de TechStore · versión local

Actualizada el 16/10/2026. Detalle: [Dashboard automático](DASHBOARD_AUTOMATICO.md).

```mermaid
flowchart TB
    Usuario[Cliente o administrador] --> PHP[Apache y PHP en XAMPP]
    PHP <--> DB[(MySQL local)]
    Dashboard[Dashboard: lectura cada 30 segundos] --> PHP
    Dashboard -->|POST autenticado cada 60 segundos| C[Coordinador]
    Tarea[Tarea local de Windows] --> C
    C --> I[1. Inventario]
    C --> S[2. Seguimiento de pedidos]
    C --> P[3. Proyección de demanda]
    I --> A[4. Análisis y recomendaciones]
    S --> A
    P --> A
    A --> N[5. Notificaciones]
    I --> DB
    S --> DB
    P --> Informes[Informes ML con métricas y fechas]
    N --> Cola[Cola común y ejecuciones en MySQL]
    PHP -->|Compra o cambio de stock confirmado| Cola
    Cola --> Telegram[Bot Telegram del administrador]
    Entrenar[Entrenamiento Python separado] --> DB
    Entrenar --> Informes
    P --> Historial[Pronósticos futuros archivados y evaluación posterior]
    Historial --> DB
    PHP --> Chat[Asistente: consulta local y contexto limitado]
    Chat --> DB
    Chat --> Gemini[Gemini LLM: explicación conversacional]
    Chat -->|Prioridades y vigencia| C
    PHP --> Maps[Google Maps: selección de destino]
```

Los cinco agentes tienen reglas y entradas concretas. Gemini explica resultados,
no modifica registros ni sustituye las predicciones de los modelos. El gráfico
representa dependencias; preguntar al asistente no ejecuta el coordinador: lee
su último resultado y advierte si está desactualizado.

La aplicación registra la transición de stock y la alerta en la transacción de
compra/edición; el envío externo se intenta después del commit. Las revisiones
usan el mismo registro para no repetir esa transición.

MySQL conserva ejecuciones, avisos y seguimiento de pronósticos. Los modelos y
los informes JSON siguen en disco local. No hay entrenamiento automático al
refrescar. El panel deja de solicitar agentes si se oculta o se cierra; la tarea
Windows opera por separado mientras el equipo y MySQL estén disponibles.

El proveedor puede fallar. Los rechazos permiten reintentos con espera; un timeout
incierto requiere revisión para no duplicar una entrega posiblemente aceptada.
No se promete entrega exactamente una vez. Las claves permanecen en el servidor,
excepto la clave Maps para navegador, que debe restringirse por dominio y API.

[Evidencias de pruebas](EVIDENCIAS_PRUEBAS.md).
