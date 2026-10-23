-- Aditiva: no borra datos ni cambia existencias.
CREATE TABLE IF NOT EXISTS ml_control (clave VARCHAR(40) PRIMARY KEY, valor TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS ml_entrenamientos (
 id VARCHAR(40) PRIMARY KEY, inicio VARCHAR(19) NOT NULL, fin VARCHAR(19),
 origen VARCHAR(20) NOT NULL, estado VARCHAR(30) NOT NULL, detalle TEXT NOT NULL
);
-- Los resultados del auditor y el reporte se guardan en las tablas ya existentes
-- agentes_estado y agentes_ejecuciones. La entrega usa alertas_operativas.
