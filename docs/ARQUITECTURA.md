# Arquitectura de TechStore

## Vista general del despliegue

```mermaid
flowchart TB
    Cliente[Cliente: catálogo, carrito y checkout]
    Admin[Administrador: productos, pedidos y analítica]
    subgraph Render[Render: contenedor Docker]
        PHP[Apache + PHP: sesiones, permisos, validación y compras]
        Chat[Asistentes: consultas locales y contexto para Gemini]
        Python[Python: entrenamiento y evaluación ML]
        Reportes[Informes JSON y modelos locales]
        Agentes[Coordinador de agentes]
        Guardado[Aviso inmediato después de guardar stock]
        Estado[Archivos de estado y control de envíos]
    end
    DB[(Clever Cloud: MySQL)]
    Gemini[API Gemini: LLM]
    Telegram[Bot Telegram: chat autorizado]
    Maps[Google Maps: lugar y coordenadas]
    Cliente --> PHP
    Admin --> PHP
    PHP <--> DB
    PHP --> Chat
    Chat -->|Consultas SQL parametrizadas| DB
    Chat -->|Contexto limitado| Gemini
    Admin -->|Entrenar desde analítica| Python
    Python -->|Instantánea de datos| DB
    Python --> Reportes
    Reportes --> PHP
    PHP -->|Revisión manual| Agentes
    Agentes --> DB
    Agentes --> Estado
    PHP -->|Editar producto: después del commit| Guardado
    Guardado --> Estado
    Guardado --> Telegram
    Agentes -->|Envío solicitado| Telegram
    Cliente -->|Selecciona dirección| Maps
    Maps -->|Coordenadas y place ID del destino| PHP
```

La aplicación contiene PHP y Python; MySQL es un servicio separado. Las claves
se configuran en variables del servidor. La clave de Maps usada en el navegador
debe restringirse por dominio y API. Las claves Gemini y Telegram permanecen
del lado del servidor.

## Coordinación de agentes y aviso inmediato

```mermaid
sequenceDiagram
    actor Admin as Administrador
    participant PHP as Aplicación PHP
    participant DB as MySQL
    participant C as Coordinador
    participant I as Agente Inventario
    participant A as Agente Análisis
    participant N as Agente Notificaciones
    participant T as Telegram
    alt Revisión de agentes
        Admin->>PHP: Ejecutar revisión
        PHP->>C: Iniciar flujo
        C->>I: Solicitar inventario
        I->>DB: Productos activos con stock de 0 a 5
        I-->>C: Hallazgos
        C->>A: Hallazgos y periodo de ventas
        A->>DB: Ventas confirmadas de 30 días
        A-->>C: Prioridad, cobertura y reposición sugerida
        C->>N: Hallazgos y estado anterior
        N-->>C: Alertas nuevas y recuperaciones
        C-->>PHP: Resultado y traza
        opt Revisión con envío
            PHP->>T: Avisos pendientes con control de duplicados
        end
    else Cambio desde Editar producto
        Admin->>PHP: Guardar nuevo stock
        PHP->>DB: Bloquear fila, actualizar y confirmar transacción
        DB-->>PHP: Guardado confirmado y stock anterior
        PHP->>PHP: Comparar stock anterior y actual
        opt Cambio relevante de producto activo
            PHP->>T: Aviso inmediato de stock bajo o recuperado
        end
        PHP-->>Admin: Confirmación de guardado y resultado de Telegram
    end
```

Son agentes especializados con reglas deterministas y mensajes estructurados.
No utilizan Gemini para decidir prioridades ni realizan compras autónomas.
El aviso inmediato de Editar producto es un camino adicional: no ejecuta todo
el coordinador ni calcula la recomendación completa sobre ventas históricas.
Solo se intenta después de guardar; un fallo del proveedor no revierte el stock.

## Diferencia entre ML, LLM y agentes

| Componente | Fuente y método | Resultado | Límite |
| --- | --- | --- | --- |
| ML | Datos de productos, clientes, pedidos y detalles; K-Means, clasificación y pronósticos temporales | Segmentos, riesgo de cancelación y proyecciones con métricas | Las métricas corresponden al conjunto académico; no garantizan resultados futuros |
| LLM | Gemini con contexto limitado y consultas locales exactas | Respuestas conversacionales | Depende de disponibilidad y cuota; una respuesta local no acredita una llamada a Gemini |
| Agentes | Stock y ventas históricas, reglas y coordinador | Prioridad, reposición sugerida y avisos | Cobertura histórica no equivale a una predicción ML de agotamiento |

## Persistencia y automatización pendientes en Render Free

- MySQL conserva los datos de negocio en Clever Cloud.
- Los archivos nuevos de uploads, informes ML, sesiones e historial de alertas
  están en el contenedor y pueden perderse con reinicios o nuevos despliegues.
- La tarea de Windows cada 15 minutos pertenece a la computadora local. No
  representa una tarea programada dentro de Render.
- El guardado de stock desde el administrador sí intenta el aviso inmediatamente.
  Cambiar MySQL con phpMyAdmin no ejecuta ese código PHP.
- El control de envíos usa archivos: no garantiza entrega exactamente una vez
  entre reinicios ni reintentos automáticos durables. Esas mejoras están pendientes.

Detalle de modelos: [ML.md](ML.md). Evidencias: [EVIDENCIAS_PRUEBAS.md](EVIDENCIAS_PRUEBAS.md).
