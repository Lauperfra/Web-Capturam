<?php
/**
 * Endpoint del formulario de contacto de la web de Capturam Ingeniería.
 *
 * - No usa base de datos ni guarda el mensaje/datos personales en disco:
 *   el correo se envía y se descarta. Lo único que se guarda temporalmente
 *   es un contador de envíos por IP (para el límite antiabuso), sin ningún
 *   dato personal ni el contenido del mensaje.
 * - Envía el correo por SMTP a través de Microsoft 365 usando PHPMailer
 *   (nunca mail() de PHP).
 * - Las credenciales SMTP se cargan desde variables de entorno o desde un
 *   archivo de configuración fuera del directorio público — nunca desde
 *   este archivo ni desde el HTML/JS público.
 *
 * Requiere PHPMailer (ver server/composer.json). Ejecutar "composer install"
 * dentro de server/ antes de desplegar, o colocar manualmente la carpeta
 * vendor/phpmailer/phpmailer/src/ junto a este archivo.
 */

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// Nunca mostrar errores de PHP al visitante: solo registrarlos en el log
// del servidor. Un mensaje de error filtrado podría revelar rutas internas
// o (en el peor caso) fragmentos de configuración.
ini_set('display_errors', '0');
error_reporting(E_ALL);
date_default_timezone_set('Europe/Madrid');

require __DIR__ . '/plantilla-correo.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function responder(int $codigoHttp, array $cuerpo): void
{
    http_response_code($codigoHttp);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    responder(405, ['ok' => false, 'error' => 'Método no permitido.']);
}

// ---------------------------------------------------------------------
// 1) Configuración (variables de entorno o archivo externo al público)
// ---------------------------------------------------------------------

function cargarConfiguracion(): array
{
    $desdeEntorno = [
        'smtp_host' => getenv('SMTP_HOST') ?: null,
        'smtp_port' => getenv('SMTP_PORT') ?: null,
        'smtp_secure' => getenv('SMTP_SECURE') ?: null,
        'smtp_username' => getenv('SMTP_USERNAME') ?: null,
        'smtp_password' => getenv('SMTP_PASSWORD') ?: null,
        'mail_from' => getenv('MAIL_FROM') ?: null,
        'mail_from_name' => getenv('MAIL_FROM_NAME') ?: null,
        'mail_to' => getenv('MAIL_TO') ?: null,
        'turnstile_secret_key' => getenv('TURNSTILE_SECRET_KEY') ?: '',
        'site_url' => getenv('SITE_URL') ?: null,
    ];

    if (!empty($desdeEntorno['smtp_host']) && !empty($desdeEntorno['smtp_username'])) {
        return $desdeEntorno;
    }

    // Un nivel por encima del directorio público (fuera de public_html) —
    // salvo en hostings tipo Hostalia (Plesk), donde ese nivel superior no
    // es legible para PHP por open_basedir y hay que usar en su lugar la
    // carpeta "private/" que sí tienen permitida, hermana de httpdocs.
    $nivelSuperior = dirname(__DIR__);
    $conPrivate = $nivelSuperior . '/private';
    $carpetaPrivada = @is_dir($conPrivate) ? $conPrivate : $nivelSuperior;
    $rutaConfig = $carpetaPrivada . '/capturam-mail-config.php';
    if (is_file($rutaConfig)) {
        $config = require $rutaConfig;
        if (is_array($config)) {
            return $config;
        }
    }

    return [];
}

$config = cargarConfiguracion();

if (empty($config['smtp_host']) || empty($config['smtp_username']) || empty($config['smtp_password']) || empty($config['mail_to'])) {
    error_log('[contacto] Falta configuración SMTP (capturam-mail-config.php o variables de entorno).');
    responder(500, [
        'ok' => false,
        'error' => 'El envío de correo no está disponible en este momento. Escríbenos directamente a ' . (getenv('MAIL_TO') ?: 'ingenieria@capturam.es') . '.',
    ]);
}

// ---------------------------------------------------------------------
// 2) Honeypot: si el campo trampa viene relleno, es un bot. Respondemos
//    "éxito" para no darle pistas, pero no enviamos nada.
// ---------------------------------------------------------------------

if (!empty($_POST['pagina_web'] ?? '')) {
    responder(200, ['ok' => true, 'mensaje' => 'Gracias por tu mensaje.']);
}

// ---------------------------------------------------------------------
// 3) Límite de envíos por IP (sin guardar datos personales: solo un
//    contador de marcas de tiempo, indexado por el hash de la IP).
// ---------------------------------------------------------------------

function limiteDeEnviosSuperado(string $ip, int $maxEnvios = 5, int $ventanaSegundos = 900): bool
{
    $dir = sys_get_temp_dir() . '/capturam_contacto_rl';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    if (!is_dir($dir) || !is_writable($dir)) {
        // Si no se puede usar el límite de envíos, no bloqueamos el
        // formulario por ello (el honeypot y Turnstile siguen activos).
        return false;
    }

    $archivo = $dir . '/' . hash('sha256', $ip) . '.json';
    $ahora = time();

    $envios = [];
    if (is_file($archivo)) {
        $contenido = @file_get_contents($archivo);
        $decodificado = $contenido ? json_decode($contenido, true) : null;
        if (is_array($decodificado)) {
            $envios = $decodificado;
        }
    }

    $envios = array_values(array_filter($envios, function ($marca) use ($ahora, $ventanaSegundos) {
        return is_int($marca) && ($ahora - $marca) < $ventanaSegundos;
    }));

    if (count($envios) >= $maxEnvios) {
        return true;
    }

    $envios[] = $ahora;
    @file_put_contents($archivo, json_encode($envios), LOCK_EX);
    return false;
}

$ipVisitante = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if (limiteDeEnviosSuperado($ipVisitante)) {
    responder(429, ['ok' => false, 'error' => 'Has enviado demasiados mensajes seguidos. Inténtalo de nuevo en unos minutos.']);
}

// ---------------------------------------------------------------------
// 4) Cloudflare Turnstile (opcional): solo se comprueba si hay clave
//    secreta configurada. Sin clave, este paso se omite por completo.
// ---------------------------------------------------------------------

/**
 * Llama a la petición HTTPS de verificación (cURL si está disponible —
 * más fiable en hosting compartido y nos deja distinguir "no se pudo
 * contactar" de "respuesta no válida" — con file_get_contents() como
 * respaldo si el hosting no tuviera la extensión curl activada).
 * Devuelve null si no se ha podido contactar con el servicio en absoluto.
 */
function turnstileConsultarApi(string $datosPeticion): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $datosPeticion,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $respuesta = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($respuesta === false) {
            error_log('[contacto] Turnstile: fallo de cURL al contactar con Cloudflare: ' . $error);
            return null;
        }
        return $respuesta;
    }

    // Respaldo si el hosting no tiene la extensión curl activada.
    $contexto = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $datosPeticion,
            'timeout' => 5,
        ],
    ]);
    $respuesta = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $contexto);
    if ($respuesta === false) {
        error_log('[contacto] Turnstile: file_get_contents() no pudo contactar con Cloudflare (¿allow_url_fopen desactivado?).');
        return null;
    }
    return $respuesta;
}

function turnstileValido(string $secretKey, string $token, string $ip): bool
{
    if ($token === '') {
        return false;
    }

    $datosPeticion = http_build_query([
        'secret' => $secretKey,
        'response' => $token,
        'remoteip' => $ip,
    ]);

    $respuesta = turnstileConsultarApi($datosPeticion);
    if ($respuesta === null) {
        // Si el servicio de verificación falla, no bloqueamos el envío solo
        // por eso (evita que un fallo externo tumbe el formulario) — pero
        // turnstileConsultarApi() ya ha dejado constancia clara en el log
        // de PHP del motivo exacto, para poder detectarlo cuanto antes.
        return true;
    }

    $resultado = json_decode($respuesta, true);
    if (!is_array($resultado)) {
        error_log('[contacto] Turnstile: respuesta de Cloudflare no es JSON válido: ' . $respuesta);
        return true;
    }

    if (empty($resultado['success'])) {
        error_log('[contacto] Turnstile: verificación rechazada — ' . json_encode($resultado['error-codes'] ?? []));
        return false;
    }

    return true;
}

if (!empty($config['turnstile_secret_key'])) {
    $tokenTurnstile = trim((string) ($_POST['cf-turnstile-response'] ?? ''));
    if (!turnstileValido($config['turnstile_secret_key'], $tokenTurnstile, $ipVisitante)) {
        responder(400, ['ok' => false, 'error' => 'No se ha podido verificar que eres una persona. Inténtalo de nuevo.']);
    }
}

// ---------------------------------------------------------------------
// 5) Validación y saneado de los campos (SIEMPRE en servidor).
// ---------------------------------------------------------------------

function limpiarTexto(string $valor): string
{
    $valor = trim($valor);
    $valor = str_replace(["\r", "\n"], ' ', $valor);
    return trim(strip_tags($valor));
}

$nombre = limpiarTexto((string) ($_POST['nombre'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$mensajeBruto = trim((string) ($_POST['mensaje'] ?? ''));
$mensaje = trim(strip_tags($mensajeBruto));
$aceptaPrivacidad = !empty($_POST['privacidad'] ?? '');

$errores = [];

if ($nombre === '' || mb_strlen($nombre) < 2) {
    $errores[] = 'Indica tu nombre.';
} elseif (mb_strlen($nombre) > 100) {
    $errores[] = 'El nombre es demasiado largo.';
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 180 || preg_match('/[\r\n]/', $email)) {
    $errores[] = 'Indica un correo electrónico válido.';
}

if ($mensaje === '' || mb_strlen($mensaje) < 10) {
    $errores[] = 'El mensaje es demasiado corto.';
} elseif (mb_strlen($mensaje) > 4000) {
    $errores[] = 'El mensaje es demasiado largo (máximo 4000 caracteres).';
}

if (!$aceptaPrivacidad) {
    $errores[] = 'Debes aceptar la Política de Privacidad para enviar el formulario.';
}

if (!empty($errores)) {
    responder(400, ['ok' => false, 'error' => $errores[0]]);
}

// ---------------------------------------------------------------------
// 6) Envío por SMTP (Microsoft 365) usando PHPMailer.
// ---------------------------------------------------------------------

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
} else {
    // Ruta de respaldo si PHPMailer se instaló a mano (sin Composer),
    // manteniendo la misma estructura de carpetas que usa Packagist.
    $rutaManual = __DIR__ . '/vendor/phpmailer/phpmailer/src/';
    if (is_file($rutaManual . 'Exception.php')) {
        require $rutaManual . 'Exception.php';
        require $rutaManual . 'PHPMailer.php';
        require $rutaManual . 'SMTP.php';
    }
}

if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    error_log('[contacto] PHPMailer no está instalado (falta server/vendor/).');
    responder(500, ['ok' => false, 'error' => 'El envío de correo no está disponible en este momento. Escríbenos directamente a ' . $config['mail_to'] . '.']);
}

try {
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';

    $mail->isSMTP();
    $mail->Host = $config['smtp_host'];
    $mail->Port = (int) ($config['smtp_port'] ?? 587);
    $mail->SMTPAuth = true;
    $mail->Username = $config['smtp_username'];
    $mail->Password = $config['smtp_password'];
    $mail->SMTPSecure = ($config['smtp_secure'] ?? 'tls') === 'ssl' ? 'ssl' : 'tls';

    // El remitente SIEMPRE es el buzón corporativo, nunca el correo que
    // escribe el visitante (evita suplantación y cumple con Microsoft 365).
    $mail->setFrom($config['mail_from'] ?: $config['smtp_username'], $config['mail_from_name'] ?? 'Web Capturam');
    $mail->addAddress($config['mail_to']);
    $mail->addReplyTo($email, $nombre);

    $datosCorreo = [
        'nombre' => $nombre,
        'email' => $email,
        'mensaje' => $mensaje,
        'fecha' => date('d/m/Y'),
        'hora' => date('H:i:s'),
    ];
    $urlBase = $config['site_url'] ?? 'https://capturam.es';

    $rutaLogo = __DIR__ . '/static/img/03_color_positivo.png';
    if (is_file($rutaLogo)) {
        // El tercer argumento es el nombre que ven los clientes de correo
        // que listan las imágenes incrustadas como adjunto (Outlook,
        // Gmail...) — el nombre real del archivo en disco no tiene por qué
        // salir ahí.
        $mail->addEmbeddedImage($rutaLogo, 'logo-capturam', 'logo-capturam.png');
    }

    $mail->Subject = 'Un usuario ha rellenado el formulario de contacto — Web Capturam';
    $mail->isHTML(true);
    $mail->Body = construirCorreoHtml($datosCorreo, $urlBase);
    $mail->AltBody = construirCorreoTexto($datosCorreo);

    $mail->send();
} catch (PHPMailerException $e) {
    error_log('[contacto] Error al enviar el correo: ' . $e->getMessage());
    responder(502, ['ok' => false, 'error' => 'No se ha podido enviar el mensaje. Inténtalo de nuevo o escríbenos directamente a ' . $config['mail_to'] . '.']);
}

responder(200, ['ok' => true, 'mensaje' => 'Gracias por tu mensaje. Te responderemos lo antes posible.']);
