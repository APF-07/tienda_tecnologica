# Tienda Tecnológica 🛒💻

Sistema web moderno de comercio electrónico enfocado en la comercialización de productos tecnológicos, optimizado para ofrecer una experiencia de usuario fluida, gestión eficiente de inventario y geolocalización integrada.

---

## 🔗 Enlace del Sistema en Línea
Puedes acceder y probar el sistema funcionando en producción mediante el siguiente enlace:
* **URL de Render:** https://tienda-tecnologica-plvr.onrender.com/
Para el ingreso al sistema del Administrador es: admin@admin.com - 123456admin
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
* **Credenciales:** Utiliza el usuario y la contraseña de administrador registrados en la base de datos de tu base de datos (Clever Cloud / XAMPP).

---

## 🛠️ Stack Tecnológico
* **Lenguaje Backend:** PHP
* **Base de Datos Relacional:** MySQL (Alojado en Clever Cloud)
* **Frontend e Interfaz:** HTML5, CSS3, JavaScript
* **Despliegue:** Render