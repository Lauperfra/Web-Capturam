<?php
/**
 * Arranque común del panel de administración (sesión, configuración y
 * datos de publicaciones). Lo incluyen login.php, panel.php y logout.php.
 *
 * No debe pedirse nunca directamente por URL (bloqueado en admin/.htaccess);
 * no imprime nada por sí mismo si alguien lo consigue pedir igualmente.
 */

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);
date_default_timezone_set('Europe/Madrid');

// ---------------------------------------------------------------------
// Sesión reforzada: cookie restringida a /admin/, inaccesible por JS y
// marcada "secure" cuando el sitio va por HTTPS (debería ir siempre).
// ---------------------------------------------------------------------

$sitioSeguro = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['SERVER_PORT'] ?? '') === '443'
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

session_name('capturam_admin_sesion');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/admin/',
    'secure' => $sitioSeguro,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Cierre de sesión automático tras 2 horas de inactividad.
const SESION_INACTIVIDAD_MAX = 7200;

if (!empty($_SESSION['admin_autenticado'])) {
    if (!empty($_SESSION['ultima_actividad']) && (time() - $_SESSION['ultima_actividad']) > SESION_INACTIVIDAD_MAX) {
        $_SESSION = [];
        session_destroy();
    } else {
        $_SESSION['ultima_actividad'] = time();
    }
}

// ---------------------------------------------------------------------
// Configuración: hash de la contraseña de acceso, guardado FUERA del
// directorio público (nunca en este repositorio ni accesible por HTTP).
// Misma convención que capturam-mail-config.php — ver admin/config.example.php.
// ---------------------------------------------------------------------

function cargarConfiguracionAdmin(): array
{
    // admin/_bootstrap.php -> dist/admin -> dist -> fuera de public_html.
    $rutaConfig = dirname(__DIR__, 2) . '/capturam-admin-config.php';
    if (is_file($rutaConfig)) {
        $config = require $rutaConfig;
        if (is_array($config)) {
            return $config;
        }
    }
    return [];
}

// ---------------------------------------------------------------------
// Publicaciones: almacenadas como PDFs + un índice JSON en dist/publicaciones/
// (fuera del control de versiones — vive solo en el servidor real y no debe
// borrarse ni sobrescribirse al desplegar cambios de la web).
// ---------------------------------------------------------------------

define('CARPETA_PUBLICACIONES', dirname(__DIR__) . '/publicaciones');
define('RUTA_DATOS_PUBLICACIONES', CARPETA_PUBLICACIONES . '/datos.json');

function asegurarCarpetaPublicaciones(): void
{
    if (!is_dir(CARPETA_PUBLICACIONES)) {
        @mkdir(CARPETA_PUBLICACIONES, 0755, true);
    }
    // Cinturón de seguridad: aunque solo deberían llegar aquí PDFs ya
    // validados, esta carpeta nunca debe poder ejecutar PHP ni listarse.
    $htaccess = CARPETA_PUBLICACIONES . '/.htaccess';
    if (!is_file($htaccess)) {
        @file_put_contents(
            $htaccess,
            "<FilesMatch \"\\.(php|phtml|php\\d)\$\">\n    Require all denied\n</FilesMatch>\nOptions -ExecCGI -Indexes\n"
        );
    }
}

function leerPublicaciones(): array
{
    asegurarCarpetaPublicaciones();
    if (!is_file(RUTA_DATOS_PUBLICACIONES)) {
        return [];
    }
    $contenido = @file_get_contents(RUTA_DATOS_PUBLICACIONES);
    $datos = $contenido ? json_decode($contenido, true) : null;
    return is_array($datos) ? $datos : [];
}

function guardarPublicaciones(array $publicaciones): void
{
    asegurarCarpetaPublicaciones();
    file_put_contents(
        RUTA_DATOS_PUBLICACIONES,
        json_encode($publicaciones, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    );
}

// ---------------------------------------------------------------------
// Sesión / CSRF / límite de intentos.
// ---------------------------------------------------------------------

function sesionAdminIniciada(): bool
{
    return !empty($_SESSION['admin_autenticado']);
}

function requerirSesionAdmin(): void
{
    if (!sesionAdminIniciada()) {
        header('Location: login.php');
        exit;
    }
}

function tokenCsrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrfValido(string $token): bool
{
    return !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

function limiteDeIntentosSuperado(string $ip, int $maxIntentos = 8, int $ventanaSegundos = 900): bool
{
    $dir = sys_get_temp_dir() . '/capturam_admin_rl';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    if (!is_dir($dir) || !is_writable($dir)) {
        // Si no se puede usar el límite, no bloqueamos el login solo por
        // eso (la contraseña sigue siendo la única barrera real).
        return false;
    }

    $archivo = $dir . '/' . hash('sha256', $ip) . '.json';
    $ahora = time();

    $intentos = [];
    if (is_file($archivo)) {
        $contenido = @file_get_contents($archivo);
        $decodificado = $contenido ? json_decode($contenido, true) : null;
        if (is_array($decodificado)) {
            $intentos = $decodificado;
        }
    }

    $intentos = array_values(array_filter($intentos, function ($marca) use ($ahora, $ventanaSegundos) {
        return is_int($marca) && ($ahora - $marca) < $ventanaSegundos;
    }));

    if (count($intentos) >= $maxIntentos) {
        return true;
    }

    $intentos[] = $ahora;
    @file_put_contents($archivo, json_encode($intentos), LOCK_EX);
    return false;
}

function registrarIntentoOk(string $ip): void
{
    $archivo = sys_get_temp_dir() . '/capturam_admin_rl/' . hash('sha256', $ip) . '.json';
    @unlink($archivo);
}
