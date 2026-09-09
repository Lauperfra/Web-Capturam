<?php
/**
 * Script SOLO para pruebas locales con "php -S" (no se sube al hosting ni se
 * copia a dist/). El servidor de pruebas de PHP ignora .htaccess, así que no
 * sabe nada del ErrorDocument 404. Este router hace lo mismo a mano: si la
 * URL pedida no es un archivo real, devuelve dist/404.html con código 404.
 *
 * Uso (desde la raíz del proyecto):
 *   php -S localhost:8000 -t dist router-local.php
 */

$ruta = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$archivo = $_SERVER['DOCUMENT_ROOT'] . $ruta;

if ($ruta === '/' || is_dir($archivo) || is_file($archivo)) {
    return false; // deja que el servidor de PHP lo sirva normalmente
}

http_response_code(404);
readfile($_SERVER['DOCUMENT_ROOT'] . '/404.html');
