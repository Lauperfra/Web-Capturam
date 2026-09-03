<?php
/**
 * Plantilla de configuración del formulario de contacto.
 *
 * Este archivo de EJEMPLO no contiene ningún secreto real y sí puede subirse
 * al repositorio. El archivo REAL, con las credenciales verdaderas, debe:
 *
 *   1. Llamarse "capturam-mail-config.php".
 *   2. Copiarse en el servidor de producción UN NIVEL POR ENCIMA del directorio
 *      público (public_html). Es decir, si "enviar-contacto.php" vive en
 *      /home/TUUSUARIO/public_html/enviar-contacto.php, este archivo debe ir en
 *      /home/TUUSUARIO/capturam-mail-config.php (fuera de public_html, para que
 *      nadie pueda pedirlo por HTTP).
 *   3. NO subirse nunca a git.
 *
 * Alternativa: si el hosting permite variables de entorno reales (algunos
 * paneles de control lo permiten), se pueden definir con esos mismos nombres
 * en mayúsculas (ver cargarConfiguracion() en enviar-contacto.php) y este
 * archivo no sería necesario.
 */

return [
    // Datos SMTP de Microsoft 365 (Exchange Online).
    'smtp_host' => 'smtp.office365.com',
    'smtp_port' => 587,
    'smtp_secure' => 'tls', // STARTTLS

    // Buzón corporativo que autentica y envía el correo (@capturam.es).
    // Con Microsoft 365, "From" debe coincidir con este buzón (o una
    // dirección sobre la que tenga permiso "Enviar como"), si no el envío
    // será rechazado por protección antisuplantación.
    'smtp_username' => 'contacto@capturam.es',
    'smtp_password' => 'CAMBIA_ESTO_POR_LA_CONTRASEÑA_O_APP_PASSWORD',

    'mail_from' => 'contacto@capturam.es',
    'mail_from_name' => 'Web Capturam Ingeniería',

    // Buzón donde se reciben las consultas del formulario. Puede ser el
    // mismo que smtp_username o uno distinto (p. ej. ingenieria@capturam.es).
    'mail_to' => 'contacto@capturam.es',

    // Dominio final de la web (sin barra al final). Se usa para el logo y
    // los enlaces del correo de aviso. Cámbialo si el dominio real es otro.
    'site_url' => 'https://capturam.es',

    // Cloudflare Turnstile (antispam). Déjalo vacío para mantenerlo
    // desactivado hasta que tengas las claves; el formulario funciona igual
    // sin él (honeypot + límite de envíos siguen activos).
    'turnstile_secret_key' => '',
];
