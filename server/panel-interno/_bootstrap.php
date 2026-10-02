<?php
/**
 * Arranque común del panel de administración (sesión, configuración y
 * datos de publicaciones). Lo incluyen login.php, panel.php y logout.php.
 *
 * No debe pedirse nunca directamente por URL (bloqueado en
 * panel-interno/.htaccess); no imprime nada por sí mismo si alguien lo
 * consigue pedir igualmente.
 */

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);
date_default_timezone_set('Europe/Madrid');

/**
 * Carpeta privada fuera de httpdocs: distinto según el hosting. Azulae (y
 * Laragon en local) ponen los archivos directamente un nivel por encima de
 * la carpeta pública. Hostalia (Plesk) usa una carpeta "private/" hermana
 * de httpdocs, y además restringe con open_basedir el acceso de PHP
 * exactamente a esas dos rutas (httpdocs/ y private/) — el directorio raíz
 * del dominio en sí no es legible ni siquiera para comprobar si existe.
 * "@" aquí no oculta errores reales: is_dir() sobre una ruta fuera de
 * open_basedir emite un aviso esperado (estamos probando a propósito), no
 * un fallo.
 */
if (!function_exists('capturamCarpetaPrivada')) {
    function capturamCarpetaPrivada(string $nivelSuperior): string
    {
        $conPrivate = $nivelSuperior . '/private';
        return @is_dir($conPrivate) ? $conPrivate : $nivelSuperior;
    }
}

/**
 * Detecta si el archivo subido es realmente un PDF o una imagen (JPG, PNG o
 * WEBP), mirando el contenido — nunca el nombre ni la extensión que mande el
 * navegador. Devuelve null si no es ninguno de los dos. Compartida por el
 * panel de publicaciones y el de "Contenido web".
 */
function detectarTipoArchivo(string $tmpPath): ?array
{
    $cabecera = @file_get_contents($tmpPath, false, null, 0, 5);
    if ($cabecera === '%PDF-') {
        return ['tipo' => 'pdf', 'extension' => 'pdf'];
    }

    // getimagesize() forma parte del núcleo de PHP (no depende de GD ni de
    // fileinfo) y comprueba la estructura real del archivo, no solo la
    // cabecera — sirve como validación robusta también en hostings con
    // pocas extensiones activadas.
    $info = @getimagesize($tmpPath);
    if ($info !== false) {
        $extensionesPermitidas = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
        ];
        if (isset($extensionesPermitidas[$info[2]])) {
            return ['tipo' => 'imagen', 'extension' => $extensionesPermitidas[$info[2]]];
        }
    }

    return null;
}

// ---------------------------------------------------------------------
// Sesión reforzada: cookie restringida a /panel-interno/, inaccesible por
// JS y marcada "secure" cuando el sitio va por HTTPS (debería ir siempre).
// ---------------------------------------------------------------------

$sitioSeguro = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['SERVER_PORT'] ?? '') === '443'
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

session_name('capturam_admin_sesion');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/panel-interno/',
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
// Configuración: usuario y hash de la contraseña de acceso, guardados
// FUERA del directorio público (nunca en este repositorio ni accesible por
// HTTP). Misma convención que capturam-mail-config.php — ver
// panel-interno/config.example.php.
// ---------------------------------------------------------------------

function cargarConfiguracionAdmin(): array
{
    // panel-interno/_bootstrap.php -> dist/panel-interno -> dist -> fuera
    // de public_html (o de httpdocs/private, según el hosting).
    $rutaConfig = capturamCarpetaPrivada(dirname(__DIR__, 2)) . '/capturam-admin-config.php';
    if (is_file($rutaConfig)) {
        $config = require $rutaConfig;
        if (is_array($config)) {
            return $config;
        }
    }
    return [];
}

// ---------------------------------------------------------------------
// Publicaciones: almacenadas como PDFs + un índice JSON en
// dist/publicaciones-datos/ (fuera del control de versiones — vive solo en
// el servidor real y no debe borrarse ni sobrescribirse al desplegar
// cambios de la web).
//
// El nombre NO puede ser simplemente "publicaciones": esa carpeta
// colisiona con la URL limpia "/publicaciones" (la página). Apache
// encuentra la carpeta real antes de que la regla de reescritura pueda
// actuar y la redirige como si fuera un directorio, en vez de servir
// publicaciones.html.
// ---------------------------------------------------------------------

define('CARPETA_PUBLICACIONES', dirname(__DIR__) . '/publicaciones-datos');
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

/** Usuario de la sesión actual (para auditoría ligera en "Contenido web"). */
function sesionUsuarioActual(): string
{
    return (string) ($_SESSION['admin_usuario'] ?? 'admin');
}

function requerirSesionAdmin(): void
{
    if (!sesionAdminIniciada()) {
        header('Location: login');
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
