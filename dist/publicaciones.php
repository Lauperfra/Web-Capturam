<?php
/**
 * Endpoint público de solo lectura para la página "Publicaciones": devuelve
 * el listado (título, fecha, resumen, archivo y tipo) en JSON. No requiere
 * sesión ni admite escritura — el alta/baja se hace desde /panel-interno/panel
 * (con contraseña).
 */

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache');

// Solo debe responder cuando lo pide el propio JavaScript de la página
// /publicaciones (no si alguien abre este archivo directamente en el
// navegador, o lo pide desde fuera). No es una medida de seguridad fuerte
// (un cliente que falsee las cabeceras podría saltársela), pero evita el
// acceso "casual" directo al endpoint.
function peticionDesdeLaPropiaWeb(): bool
{
    $secFetchSite = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
    if ($secFetchSite !== '') {
        return $secFetchSite === 'same-origin' || $secFetchSite === 'none';
    }
    // Navegadores/clientes antiguos que no mandan Sec-Fetch-Site: nos
    // conformamos con comprobar que el Referer sea de este mismo dominio.
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $hostReferer = $referer !== '' ? parse_url($referer, PHP_URL_HOST) : null;
    return $hostReferer !== null && $hostReferer === ($_SERVER['HTTP_HOST'] ?? '');
}

if (!peticionDesdeLaPropiaWeb()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Acceso no permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$rutaDatos = __DIR__ . '/publicaciones-datos/datos.json';

$publicaciones = [];
if (is_file($rutaDatos)) {
    $contenido = @file_get_contents($rutaDatos);
    $decodificado = $contenido ? json_decode($contenido, true) : null;
    if (is_array($decodificado)) {
        $publicaciones = $decodificado;
    }
}

usort($publicaciones, function ($a, $b) {
    return strcmp((string) ($b['fecha'] ?? ''), (string) ($a['fecha'] ?? ''));
});

// Solo se exponen los campos que necesita la página pública (nunca el "id"
// interno ni nada más que pueda haber en el JSON).
$salida = array_map(function ($publicacion) {
    return [
        'titulo' => (string) ($publicacion['titulo'] ?? ''),
        'resumen' => (string) ($publicacion['resumen'] ?? ''),
        'fecha' => (string) ($publicacion['fecha'] ?? ''),
        'archivo' => (string) ($publicacion['archivo'] ?? ''),
        'tipo' => (string) ($publicacion['tipo'] ?? 'pdf'),
    ];
}, $publicaciones);

echo json_encode(['ok' => true, 'publicaciones' => $salida], JSON_UNESCAPED_UNICODE);
