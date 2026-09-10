<?php
/**
 * Plantilla de configuración del panel de administración (publicaciones).
 *
 * Este archivo de EJEMPLO no contiene ningún secreto real y sí puede subirse
 * al repositorio. El archivo REAL, con el usuario y la contraseña
 * verdaderos, debe:
 *
 *   1. Llamarse "capturam-admin-config.php".
 *   2. Copiarse en el servidor de producción UN NIVEL POR ENCIMA del directorio
 *      público (public_html) — igual que capturam-mail-config.php. Es decir,
 *      si el panel vive en /home/TUUSUARIO/public_html/panel-interno/panel,
 *      este archivo debe ir en /home/TUUSUARIO/capturam-admin-config.php
 *      (fuera de public_html, para que nadie pueda pedirlo por HTTP).
 *   3. NO subirse nunca a git.
 *
 * Para generar el hash de la contraseña, ejecuta en PHP (por ejemplo desde
 * la terminal, con "php -a" o "php -r"):
 *
 *   php -r "echo password_hash('LA_CONTRASEÑA_QUE_ELIJA_EL_CEO', PASSWORD_DEFAULT), PHP_EOL;"
 *
 * y pega el resultado (empieza por $2y$...) abajo. Nunca guardes aquí la
 * contraseña en texto plano, solo el hash. El usuario, en cambio, sí se
 * guarda tal cual (no es un secreto por sí solo, es la segunda barrera
 * junto con la contraseña).
 */

return [
    'admin_usuario' => 'CAMBIA_ESTO_POR_EL_USUARIO_ELEGIDO',
    'admin_password_hash' => 'CAMBIA_ESTO_POR_EL_HASH_GENERADO',
];
