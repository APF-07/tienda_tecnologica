# Despliegue en Render

Este repositorio contiene un Dockerfile con Apache, PHP 8.2, PDO MySQL,
cURL, mbstring, Python y las dependencias de ML. El contenedor escucha en 80;
Render detecta ese puerto. No se ejecutan importaciones ni entrenamientos al arrancar.

## 1. Elegir base de datos y persistencia

Se necesita MySQL/MariaDB accesible desde Render. La base de datos de XAMPP
no es accesible usando `localhost` desde la nube. PostgreSQL no sirve como
sustituto directo del esquema actual. Antes de contratar un proveedor, comprobar
compatibilidad, conexiones externas, TLS, almacenamiento y condiciones del plan.

En el servicio web Free, las imágenes incluidas en Git están disponibles, pero
las subidas nuevas, sesiones, resultados ML y el historial de alertas pueden
desaparecer con reinicios o despliegues. No usarlo para alertas permanentes:
perder el registro de envíos puede repetir mensajes. El plan Free también se
suspende tras inactividad. Para funcionamiento permanente hace falta diseñar
persistencia para uploads, ml y storage y un programador de revisiones en la nube.
La tarea programada de Windows solo funciona en la computadora local.

## 2. Guardar el código en GitHub

Revisar `git status` y que `.env` no esté versionado (`git ls-files .env`
debe quedar vacío). El `.dockerignore` evita incluir secretos y estado local
en la imagen. Guardar los cambios mediante add, commit y push.
Si se publicó alguna clave previamente, reemplazarla antes del despliegue.

## 3. Crear servicio web

En Render: New → Web Service → conectar GitHub → seleccionar repositorio.
Elegir la rama que contiene estos cambios y runtime Docker. Root Directory
vacío si el Dockerfile está en la raíz; Dockerfile Path `./Dockerfile`.
No sobrescribir Docker Command: se usa Apache de la imagen.
Elegir conscientemente el plan antes de crear el servicio.

## 4. Variables de entorno

En Environment, configurar individualmente, sin subir .env:

| Variable | Valor |
| --- | --- |
| DB_HOST | Host de la base remota |
| DB_PORT | Puerto del proveedor |
| DB_NAME | Nombre de la base remota |
| DB_USER | Usuario remoto |
| DB_PASSWORD | Contraseña remota |
| GEMINI_API_KEY | Clave de Gemini vigente |
| GEMINI_ENABLED | true |
| GEMINI_MODEL | Modelo disponible en el proyecto Gemini |
| GOOGLE_MAPS_API_KEY | Clave Maps restringida al nuevo dominio |
| TELEGRAM_BOT_TOKEN | Solo después de configurar persistencia de alertas |
| TELEGRAM_CHAT_ID | Destinatario autorizado del bot |

Si el proveedor exige TLS, adaptar primero la conexión PDO y la de Python a
sus certificados/opciones; no desactivar verificación de certificados.
No asumir que el código actual configura TLS del proveedor automáticamente.

## 5. Importar una copia actual de los datos

Exportar la base actual desde phpMyAdmin (SQL, estructura y datos). El archivo
del repositorio puede no incluir las últimas modificaciones locales.
Importar esa copia en la base remota usando su herramienta o un cliente MySQL.
Si el proveedor entrega un nombre fijo de base, retirar de la copia las
sentencias CREATE DATABASE y USE que apunten a tienda_tecnologica; no modificar
el respaldo original. No importar encima de tablas existentes sin revisar.

## 6. Desplegar y verificar

Crear el servicio y revisar Logs. Abrir la URL HTTPS asignada y `/tienda.php`;
en Render el sitio queda en la raíz, sin `/tienda_tecnologica`.
Probar login, filtros, carrito, pedido, imágenes y ambos chats.
Cambiar la contraseña administrativa de demostración antes de hacerlo público.
Los caches locales del ML no viajan: entrenar desde Analítica y proyección
después de importar la base. En Free los recursos y tiempos pueden limitar
ese entrenamiento; no se ha validado todavía en un contenedor real.

No dar por migradas las alertas automáticas: necesitan otro paso de despliegue
y persistencia. El comando CLI es `php scripts/ejecutar_agentes.php --enviar-telegram`;
el proceso que lo ejecute debe compartir el estado persistente del servicio.
Un cron independiente con su propio disco efímero no conserva la deduplicación.

Referencias: https://render.com/docs/docker , https://render.com/docs/free ,
https://render.com/docs/disks , https://render.com/docs/cronjobs .
