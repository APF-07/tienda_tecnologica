# Tienda Tecnológica 🛒💻

Sistema web moderno de comercio electrónico enfocado en la comercialización de productos tecnológicos, optimizado para ofrecer una experiencia de usuario fluida, gestión eficiente de inventario y geolocalización integrada.

---

## 🔗 Enlace del Sistema en Línea
Puedes acceder y probar el sistema funcionando en producción mediante el siguiente enlace:
* **URL de Render:** https://tienda-tecnologica-plvr.onrender.com/

---

## 📋 Descripción General del Proyecto
Este sistema fue desarrollado bajo una arquitectura web robusta, permitiendo cubrir el ciclo completo de una tienda virtual. Entre sus características principales destacan:
* **Gestión de Catálogo y Stock:** Control de productos tecnológicos (laptops, componentes, periféricos) con categorías dinámicas.
* **Carrito y Ventas:** Flujo interactivo de compras, cálculo de subtotales y procesamiento de pedidos.
* **Panel de Administración:** Módulo privado para la administración de usuarios, productos y visualización de registros.
* **Seguridad y Control de Acceso:** Autenticación de usuarios mediante sesiones y encriptación de credenciales.

---

## 🗺️ Ubicación y Configuración de la API de Google Maps
La integración con los servicios de geolocalización de Google Maps (utilizada para mostrar sucursales y puntos de entrega a los clientes) está estructurada en los siguientes componentes del código fuente:

* **Interfaz de Usuario (Vista):** 
  * Ubicada en la carpeta `views/` (específicamente en `contacto.php` o el módulo de sucursales), donde se dibuja el contenedor HTML del mapa (`<div id="map"></div>`).
* **Lógica del Cliente (JavaScript):** 
  * Se encuentra en el archivo `assets/js/mapa.js` o scripts embebidos encargados de invocar la librería asíncrona de Google Maps, instanciar el objeto `google.maps.Map` y colocar los marcadores interactivos.
* **Gestión de Credenciales (API Key):** 
  * La clave secreta de la API se configura de forma segura mediante variables de entorno en el servidor o se centraliza en el archivo de configuración de la conexión del sistema.

---

## 🔐 Credenciales de Acceso (Administrador)
Para realizar pruebas de las funciones administrativas del sistema:
* **Ruta de Acceso:** `/login.php`
* **Credenciales:** Utiliza el usuario y la contraseña de administrador registrados en la base de datos del sistema.

---

## 🚀 Paso a Paso del Despliegue del Sistema

El proceso de despliegue se estructuró en tres fases principales (Base de Datos, Servidor y Control de Versiones):

### Fase 1: Configuración de la Base de Datos en la Nube (Clever Cloud)
1. Se creó una instancia de base de datos MySQL en la plataforma de **Clever Cloud** para gestionar los datos de forma remota.
2. Se exportó la estructura de datos local desde XAMPP e importó a la base de datos en la nube.
3. Se obtuvieron las credenciales de conexión necesarias (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` y `DB_PORT`).

### Fase 2: Configuración del Servidor y Despliegue (Render)
1. Se conectó el repositorio de GitHub del proyecto con la plataforma **Render** como un Web Service.
2. En la sección de **Environment Variables** de Render, se configuraron las variables de entorno con los datos obtenidos de Clever Cloud:
   * `DB_HOST` = `b5y02incgntyip75n5rg-mysql.services.clever-cloud.com`
   * `DB_NAME` = `b5y02incgntyip75n5rg`
   * `DB_USER` = `urb7agde8bbcghep`
   * `DB_PASSWORD` = 76GLvrteU13eknjTHO50
   * `DB_PORT` = `3306`
3. Se aplicó la opción de compilación y despliegue automático (*Save, rebuild, and deploy*).

### Fase 3: Sincronización y Control de Versiones (Git y GitHub)
1. Se documentaron los cambios técnicos, enlaces y manuales directamente en el archivo `README.md`.
2. Se subieron y actualizaron los cambios al repositorio remoto ejecutando los comandos de Git:
   ```bash
   git add .
   git commit -m "Agrega README detallado y documentacion de despliegue"
   git push origin main