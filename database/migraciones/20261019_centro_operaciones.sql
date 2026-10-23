-- Migración aditiva e idempotente. Seleccionar antes la base del proyecto.
-- La aplicación prepara estas tablas también de forma automática.
CREATE TABLE IF NOT EXISTS ml_pronosticos (
 clave VARCHAR(64) PRIMARY KEY, tipo VARCHAR(20) NOT NULL,
 generado VARCHAR(40) NOT NULL, objetivo VARCHAR(10) NOT NULL,
 estimado DOUBLE NOT NULL, referencia DOUBLE NOT NULL,
 observado DOUBLE NULL, evaluado VARCHAR(19) NULL
);
CREATE TABLE IF NOT EXISTS ml_pronosticos_auditoria (
 clave VARCHAR(64) PRIMARY KEY, registrado VARCHAR(19) NOT NULL,
 corte VARCHAR(10) NOT NULL
);
CREATE TABLE IF NOT EXISTS acciones_dia (
 clave VARCHAR(64) PRIMARY KEY, dia VARCHAR(10) NOT NULL,
 tipo VARCHAR(30) NOT NULL, entidad INT NOT NULL, prioridad INT NOT NULL,
 titulo VARCHAR(190) NOT NULL, motivo TEXT NOT NULL, url VARCHAR(190) NOT NULL,
 estado VARCHAR(20) NOT NULL, vigente INT NOT NULL DEFAULT 1,
 creada VARCHAR(19) NOT NULL, actualizada VARCHAR(19) NOT NULL
);
CREATE TABLE IF NOT EXISTS acciones_historial (
 id VARCHAR(64) PRIMARY KEY, accion VARCHAR(64) NOT NULL,
 fecha VARCHAR(19) NOT NULL, usuario INT NOT NULL,
 estado VARCHAR(20) NOT NULL, motivo TEXT NOT NULL
);
