<?php
/**
 * "Contenido web": permite editar ciertos textos e imágenes de Inicio,
 * Empresa, Sectores, Servicios y Cómo trabajamos desde el panel, sin
 * convertir la web en un CMS.
 *
 * Arquitectura (ver plan): plantilla fija (con marcadores) + JSON editable
 * + HTML estático generado. Lo único persistente es el JSON vivo — el HTML
 * publicado es siempre un artefacto derivado, regenerable en cualquier
 * momento a partir de la plantilla más reciente y ese JSON.
 *
 * No debe pedirse nunca directamente por URL (bloqueado en
 * panel-interno/.htaccess); no imprime nada por sí mismo.
 */

declare(strict_types=1);

// _cli_regenerar_contenido.php carga este archivo directamente, sin pasar
// por _bootstrap.php (que es donde normalmente vive esta función) — con el
// guard de function_exists(), da igual el orden o la combinación en que se
// carguen los dos.
if (!function_exists('capturamCarpetaPrivada')) {
    function capturamCarpetaPrivada(string $nivelSuperior): string
    {
        $conPrivate = $nivelSuperior . '/private';
        return @is_dir($conPrivate) ? $conPrivate : $nivelSuperior;
    }
}

// ---------------------------------------------------------------------
// Rutas. contenido-datos/ y plantillas-contenido/ viven FUERA de
// public_html (mismo patrón que capturam-admin-config.php:
// panel-interno/_contenido.php -> dist/panel-interno -> dist -> fuera de
// public_html — o de httpdocs/private, según el hosting). Las imágenes
// subidas sí son públicas (como las de publicaciones), así que esas sí
// viven dentro de dist/static/.
// ---------------------------------------------------------------------

define('CONTENIDO_CARPETA_PRIVADA', capturamCarpetaPrivada(dirname(__DIR__, 2)));
define('CONTENIDO_DATOS_DIR', CONTENIDO_CARPETA_PRIVADA . '/contenido-datos');
define('CONTENIDO_BACKUPS_DIR', CONTENIDO_DATOS_DIR . '/backups');
define('CONTENIDO_PLANTILLAS_DIR', CONTENIDO_CARPETA_PRIVADA . '/plantillas-contenido');
define('CONTENIDO_VALORES_FABRICA_DIR', CONTENIDO_PLANTILLAS_DIR . '/valores-fabrica');
define('CONTENIDO_UPLOADS_DIR', dirname(__DIR__) . '/static/uploads/contenido');
// Email/teléfono/LinkedIn: a diferencia del resto, este archivo SÍ es
// público a propósito (lo lee el JavaScript de cualquier página para
// rellenar el pie de página, Contacto, etc. — ver static/js/datos-contacto.js).
// Los datos en sí no son sensibles, ya están visibles en la web de todas
// formas — pero el usuario del panel que hizo el último cambio (_admin) NO
// debe ir aquí: es la mitad de las credenciales de acceso al panel, y este
// archivo es público. Esos metadatos van aparte, fuera de public_html.
define('CONTENIDO_DATOS_CONTACTO_RUTA', dirname(__DIR__) . '/datos-contacto.json');
define('CONTENIDO_DATOS_CONTACTO_META_RUTA', CONTENIDO_DATOS_DIR . '/datos-contacto-meta.json');
define('CONTENIDO_IMG_MAX_DIMENSION', 6000);

// ---------------------------------------------------------------------
// Esquema: qué páginas y qué campos son editables. Es la única fuente de
// verdad de qué existe — contenido.php lo recorre para pintar el
// formulario y regenerarPaginaHtml() lo recorre para sustituir marcadores.
// ---------------------------------------------------------------------

function esquemaPaginasContenido(): array
{
    static $esquema = null;
    if ($esquema !== null) {
        return $esquema;
    }

    $esquema = [
        'inicio' => [
            'etiqueta' => 'Inicio',
            'archivo' => 'index.html',
            'campos' => [
                'hero_titulo' => ['tipo' => 'texto', 'etiqueta' => 'Título principal', 'max' => 150],
                'hero_subtitulo' => ['tipo' => 'texto', 'etiqueta' => 'Subtítulo', 'max' => 300],
                // La foto de portada (hero_imagen) se deja fija a propósito
                // — no es editable desde el panel.
                'vision_titulo' => ['tipo' => 'texto', 'etiqueta' => 'Título — bloque "visión integral"', 'max' => 150],
                'vision_texto' => ['tipo' => 'texto', 'etiqueta' => 'Texto — bloque "visión integral"', 'max' => 800],
                'vision_imagen' => ['tipo' => 'imagen', 'etiqueta' => 'Foto — bloque "visión integral"'],
                // Las fotos del bloque "Sectores" de Inicio son las MISMAS
                // que las de la página Sectores (igual que en el código
                // original) — no se editan aquí, se toman siempre de lo que
                // haya guardado en la página Sectores. Por eso no llevan
                // 'etiqueta': "compartido" hace que no aparezcan como campo
                // en el formulario de Inicio.
                'sectores.solar-fotovoltaica.imagen' => ['tipo' => 'imagen', 'compartido_desde' => 'sectores'],
                'sectores.eolica.imagen' => ['tipo' => 'imagen', 'compartido_desde' => 'sectores'],
                'sectores.bess.imagen' => ['tipo' => 'imagen', 'compartido_desde' => 'sectores'],
                'sectores.hibridacion.imagen' => ['tipo' => 'imagen', 'compartido_desde' => 'sectores'],
                'sectores.autoconsumo.imagen' => ['tipo' => 'imagen', 'compartido_desde' => 'sectores'],
            ],
        ],
        'servicios' => [
            'etiqueta' => 'Servicios',
            'archivo' => 'servicios.html',
            'campos' => contenidoCamposRepetidos('servicios', [
                'acceso-conexion' => 'Estrategia de acceso y conexión',
                'terrenos-viabilidad' => 'Terrenos y viabilidad',
                'ingenieria-plantas' => 'Ingeniería de plantas e infraestructuras',
                'medio-ambiente' => 'Medio ambiente',
                'permitting' => 'Permitting, RtB y PES',
                'asesoramiento' => 'Asesoramiento técnico-regulatorio',
            ], ['imagen' => 'Foto', 'alcance' => 'Alcance', 'resultado' => 'Resultado'], [
                'titulo' => ['tipo' => 'texto', 'etiqueta' => 'Título de la página', 'max' => 150],
                'introduccion' => ['tipo' => 'texto', 'etiqueta' => 'Introducción', 'max' => 800],
            ]),
        ],
        'sectores' => [
            'etiqueta' => 'Sectores',
            'archivo' => 'sectores.html',
            'campos' => contenidoCamposRepetidos('sectores', [
                'solar-fotovoltaica' => 'Solar fotovoltaica',
                'eolica' => 'Eólica',
                'bess' => 'BESS stand-alone',
                'hibridacion' => 'Instalaciones hibridadas',
                'autoconsumo' => 'Autoconsumo industrial',
            ], ['imagen' => 'Foto', 'texto' => 'Texto'], [
                'titulo' => ['tipo' => 'texto', 'etiqueta' => 'Título de la página', 'max' => 150],
                'introduccion' => ['tipo' => 'texto', 'etiqueta' => 'Introducción', 'max' => 800],
            ]),
        ],
        'proyectos' => [
            'etiqueta' => 'Proyectos',
            'archivo' => 'proyectos.html',
            'campos' => array_merge(
                contenidoCamposRepetidos('proyectos', [
                    'hibrida-fv-bess' => 'Instalación híbrida FV + BESS',
                    'parque-eolico' => 'Parque eólico',
                    'bess-standalone' => 'BESS stand-alone',
                    'agrovoltaica' => 'Planta solar agrovoltaica',
                    'autoconsumo-industrial' => 'Autoconsumo industrial',
                    'psf-400kv' => 'PSF 3 × 50 MW',
                ], ['imagen' => 'Foto', 'anio' => 'Año', 'titulo' => 'Título', 'texto' => 'Descripción'], [
                    'titulo' => ['tipo' => 'texto', 'etiqueta' => 'Título de la página', 'max' => 150],
                    'introduccion' => ['tipo' => 'texto', 'etiqueta' => 'Introducción', 'max' => 800],
                ]),
                ['foto_ancha' => ['tipo' => 'imagen', 'etiqueta' => 'Foto panorámica final']]
            ),
        ],
        'empresa' => [
            'etiqueta' => 'Quiénes somos',
            'archivo' => 'empresa.html',
            'campos' => [
                'titulo' => ['tipo' => 'texto', 'etiqueta' => 'Título', 'max' => 150],
                'introduccion' => ['tipo' => 'texto', 'etiqueta' => 'Introducción', 'max' => 800],
                'imagen_principal' => ['tipo' => 'imagen', 'etiqueta' => 'Foto principal'],
                'bloque_subtitulo' => ['tipo' => 'texto', 'etiqueta' => 'Subtítulo del bloque', 'max' => 150],
                'bloque_texto' => ['tipo' => 'texto_multiparrafo', 'etiqueta' => 'Texto del bloque', 'max' => 1500],
            ],
        ],
        'como-trabajamos' => [
            'etiqueta' => 'Cómo trabajamos',
            'archivo' => 'como-trabajamos.html',
            'campos' => [
                'introduccion' => ['tipo' => 'texto', 'etiqueta' => 'Introducción', 'max' => 800],
                'enfoque_intro' => ['tipo' => 'texto', 'etiqueta' => 'Frase — "Nuestro enfoque"', 'max' => 300],
                'enfoque_cierre' => ['tipo' => 'texto', 'etiqueta' => 'Texto de cierre — "Nuestro enfoque"', 'max' => 500],
                'cierre_texto' => ['tipo' => 'texto', 'etiqueta' => 'Resultado final', 'max' => 400],
            ],
        ],
    ];

    return $esquema;
}

/**
 * Orden del menú del panel: el mismo que el menú de la web pública para las
 * páginas que existen en ambos sitios, intercalando "Publicaciones" y
 * "Datos de contacto" (que no son páginas de esquema, son otras pantallas
 * del panel) en el hueco que les corresponde según esa misma referencia.
 */
function ordenNavPanel(): array
{
    return [
        ['tipo' => 'pagina', 'clave' => 'inicio'],
        ['tipo' => 'pagina', 'clave' => 'servicios'],
        ['tipo' => 'pagina', 'clave' => 'sectores'],
        ['tipo' => 'pagina', 'clave' => 'proyectos'],
        ['tipo' => 'publicaciones'],
        ['tipo' => 'pagina', 'clave' => 'empresa'],
        ['tipo' => 'pagina', 'clave' => 'como-trabajamos'],
        ['tipo' => 'contacto'],
    ];
}

/** Genera los campos "foto"/"texto"/... de cada elemento de una lista repetida (sectores, servicios). */
function contenidoCamposRepetidos(string $prefijo, array $items, array $subcampos, array $camposFijos): array
{
    $campos = $camposFijos;
    foreach ($items as $id => $etiquetaItem) {
        foreach ($subcampos as $subclave => $subetiqueta) {
            $clave = "{$prefijo}.{$id}.{$subclave}";
            $tipo = $subclave === 'imagen' ? 'imagen' : 'texto';
            $max = $subclave === 'alcance' || $subclave === 'texto' ? 700 : 400;
            $campos[$clave] = [
                'tipo' => $tipo,
                'etiqueta' => "{$etiquetaItem} — {$subetiqueta}",
                'max' => $max,
            ];
        }
    }
    return $campos;
}

// ---------------------------------------------------------------------
// Rutas de archivos por página.
// ---------------------------------------------------------------------

function contenidoRutaJsonVivo(string $pagina): string
{
    return CONTENIDO_DATOS_DIR . '/' . $pagina . '.json';
}

function contenidoRutaJsonFabrica(string $pagina): string
{
    return CONTENIDO_VALORES_FABRICA_DIR . '/' . $pagina . '.json';
}

function contenidoRutaPlantilla(string $pagina): string
{
    return CONTENIDO_PLANTILLAS_DIR . '/' . $pagina . '.html';
}

function contenidoRutaHtmlPublico(string $pagina): string
{
    $esquema = esquemaPaginasContenido();
    $archivo = $esquema[$pagina]['archivo'] ?? ($pagina . '.html');
    return dirname(__DIR__) . '/' . $archivo;
}

function asegurarCarpetasContenido(): void
{
    if (!is_dir(CONTENIDO_DATOS_DIR)) {
        @mkdir(CONTENIDO_DATOS_DIR, 0700, true);
    }
    if (!is_dir(CONTENIDO_BACKUPS_DIR)) {
        @mkdir(CONTENIDO_BACKUPS_DIR, 0700, true);
    }
    if (!is_dir(CONTENIDO_UPLOADS_DIR)) {
        @mkdir(CONTENIDO_UPLOADS_DIR, 0755, true);
    }
    // Igual que en publicaciones: nunca ejecutar PHP ni listar esta carpeta,
    // aunque solo debieran llegar aquí imágenes ya validadas.
    $htaccess = CONTENIDO_UPLOADS_DIR . '/.htaccess';
    if (!is_file($htaccess)) {
        @file_put_contents(
            $htaccess,
            "<FilesMatch \"\\.(php|phtml|php\\d)\$\">\n    Require all denied\n</FilesMatch>\nOptions -ExecCGI -Indexes\n"
        );
    }
}

// ---------------------------------------------------------------------
// Lectura/escritura de valores por ruta con puntos (p. ej.
// "sectores.eolica.imagen"), para poder tener listas repetidas dentro del
// mismo JSON sin duplicar lógica de acceso.
// ---------------------------------------------------------------------

function contenidoValorPorRuta(array $datos, string $clave)
{
    $actual = $datos;
    foreach (explode('.', $clave) as $parte) {
        if (!is_array($actual) || !array_key_exists($parte, $actual)) {
            return null;
        }
        $actual = $actual[$parte];
    }
    return $actual;
}

function contenidoEstablecerValorPorRuta(array &$datos, string $clave, $valor): void
{
    $partes = explode('.', $clave);
    $ultima = array_pop($partes);
    $ref = &$datos;
    foreach ($partes as $parte) {
        if (!isset($ref[$parte]) || !is_array($ref[$parte])) {
            $ref[$parte] = [];
        }
        $ref = &$ref[$parte];
    }
    $ref[$ultima] = $valor;
}

// ---------------------------------------------------------------------
// Saneado de texto y negrita segura. Se escapa SIEMPRE primero; la
// sustitución de "**negrita**" actúa sobre el texto ya escapado, así que
// nunca puede colar ningún tag distinto de <strong>.
// ---------------------------------------------------------------------

function contenidoSaneaTexto(string $texto, int $maxLongitud): string
{
    // Quita caracteres de control (deja \n para los campos multiparrafo).
    $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $texto) ?? $texto;
    $texto = trim($texto);
    if (mb_strlen($texto) > $maxLongitud) {
        $texto = mb_substr($texto, 0, $maxLongitud);
    }
    return $texto;
}

function contenidoTextoConNegritaSegura(string $textoYaEscapado): string
{
    return preg_replace('/\*\*(.+?)\*\*/su', '<strong>$1</strong>', $textoYaEscapado) ?? $textoYaEscapado;
}

function contenidoRenderizarTextoLinea(string $texto): string
{
    $unaLinea = str_replace(["\r\n", "\r", "\n"], ' ', $texto);
    $unaLinea = preg_replace('/\s+/', ' ', trim($unaLinea)) ?? trim($unaLinea);
    return contenidoTextoConNegritaSegura(htmlspecialchars($unaLinea, ENT_QUOTES, 'UTF-8'));
}

/**
 * Igual que arriba, pero además entiende listas: un bloque (separado por
 * línea en blanco) donde TODAS las líneas empiezan por "- " se convierte en
 * <ul><li>...</li></ul> en vez de un párrafo. El resto de bloques son
 * párrafos normales, igual que antes.
 */
function contenidoRenderizarTextoMultiparrafo(string $texto): string
{
    $bloques = preg_split('/\n\s*\n/', trim($texto)) ?: [];
    $piezas = [];

    foreach ($bloques as $bloque) {
        $lineas = preg_split('/\n/', trim($bloque)) ?: [];
        $lineas = array_values(array_filter(array_map('trim', $lineas), fn ($linea) => $linea !== ''));
        if ($lineas === []) {
            continue;
        }

        $esLista = true;
        foreach ($lineas as $linea) {
            if (mb_substr($linea, 0, 2) !== '- ') {
                $esLista = false;
                break;
            }
        }

        if ($esLista) {
            $items = array_map(function (string $linea): string {
                $textoItem = mb_substr($linea, 2);
                return '<li>' . contenidoTextoConNegritaSegura(htmlspecialchars($textoItem, ENT_QUOTES, 'UTF-8')) . '</li>';
            }, $lineas);
            $piezas[] = "<ul>\n      " . implode("\n      ", $items) . "\n    </ul>";
        } else {
            $unaLinea = preg_replace('/\s+/', ' ', implode(' ', $lineas)) ?? implode(' ', $lineas);
            $piezas[] = '<p>' . contenidoTextoConNegritaSegura(htmlspecialchars($unaLinea, ENT_QUOTES, 'UTF-8')) . '</p>';
        }
    }

    return implode("\n    ", $piezas);
}

/** Resuelve un valor de imagen del JSON a una ruta pública real y verificada — nunca vuelca el valor tal cual. */
function contenidoRutaImagenSegura(?string $valor): string
{
    $valor = (string) $valor;

    if ($valor !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $valor) === 1) {
        $rutaSubida = CONTENIDO_UPLOADS_DIR . '/' . $valor;
        if (is_file($rutaSubida)) {
            return '/static/uploads/contenido/' . rawurlencode($valor);
        }

        $rutaFabrica = dirname(__DIR__) . '/static/img/' . $valor;
        if (is_file($rutaFabrica)) {
            return '/static/img/' . rawurlencode($valor);
        }
    }

    // Nombre no válido o archivo inexistente: no se vuelca nada del JSON,
    // se deja una imagen de fábrica neutra en vez de romper la página.
    return '/static/img/favicon-512.png';
}

// ---------------------------------------------------------------------
// Lectura del contenido vivo (con caída a los valores de fábrica).
// ---------------------------------------------------------------------

function leerContenidoVivo(string $pagina): array
{
    $ruta = contenidoRutaJsonVivo($pagina);
    if (is_file($ruta)) {
        $datos = json_decode((string) file_get_contents($ruta), true);
        if (is_array($datos)) {
            return $datos;
        }
    }

    $fabrica = contenidoRutaJsonFabrica($pagina);
    if (is_file($fabrica)) {
        $datos = json_decode((string) file_get_contents($fabrica), true);
        if (is_array($datos)) {
            return $datos;
        }
    }

    return [];
}

// ---------------------------------------------------------------------
// Generación del HTML a partir de la plantilla + unos datos dados. Función
// pura (no escribe nada) para poder validar ANTES de tocar ningún archivo
// definitivo.
// ---------------------------------------------------------------------

function contenidoGenerarHtml(string $pagina, array $datos): ?string
{
    $esquema = esquemaPaginasContenido()[$pagina] ?? null;
    $rutaPlantilla = contenidoRutaPlantilla($pagina);
    if ($esquema === null || !is_file($rutaPlantilla)) {
        return null;
    }

    $html = (string) file_get_contents($rutaPlantilla);

    foreach ($esquema['campos'] as $clave => $definicion) {
        // Campo "compartido": su valor real vive en OTRA página (misma
        // clave), no en la de aquí — así una foto de sectores se ve igual
        // en la página Sectores y en el bloque de Inicio, sin duplicarla.
        if (isset($definicion['compartido_desde'])) {
            $datosOrigen = leerContenidoVivo($definicion['compartido_desde']);
            $valorBruto = contenidoValorPorRuta($datosOrigen, $clave);
        } else {
            $valorBruto = contenidoValorPorRuta($datos, $clave);
        }

        switch ($definicion['tipo']) {
            case 'imagen':
                $reemplazo = contenidoRutaImagenSegura(is_string($valorBruto) ? $valorBruto : null);
                $html = str_replace('[[IMAGEN:' . $clave . ']]', $reemplazo, $html);
                break;
            case 'texto_multiparrafo':
                $reemplazo = contenidoRenderizarTextoMultiparrafo((string) $valorBruto);
                $html = str_replace('[[TEXTO_MULTIPARRAFO:' . $clave . ']]', $reemplazo, $html);
                break;
            default:
                $reemplazo = contenidoRenderizarTextoLinea((string) $valorBruto);
                $html = str_replace('[[TEXTO:' . $clave . ']]', $reemplazo, $html);
        }
    }

    // Cinturón de seguridad final: si queda cualquier marcador sin resolver
    // (esquema desincronizado con la plantilla, campo nuevo sin dato...) no
    // se publica nada a medias.
    if (trim($html) === '' || preg_match('/\[\[(TEXTO_MULTIPARRAFO|TEXTO|IMAGEN):[^\]]*\]\]/', $html) === 1) {
        return null;
    }

    return $html;
}

function contenidoEscribirAtomico(string $rutaFinal, string $contenido): bool
{
    $tmp = $rutaFinal . '.tmp-' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $contenido, LOCK_EX) === false) {
        return false;
    }
    if (!@rename($tmp, $rutaFinal)) {
        @unlink($tmp);
        return false;
    }
    return true;
}

/** Recompone dist/<pagina>.html a partir de la plantilla más reciente + el JSON vivo actual, sin tocar el JSON. */
function regenerarPaginaHtml(string $pagina): bool
{
    $html = contenidoGenerarHtml($pagina, leerContenidoVivo($pagina));
    if ($html === null) {
        return false;
    }
    return contenidoEscribirAtomico(contenidoRutaHtmlPublico($pagina), $html);
}

/** Regenera las 5 páginas. Devuelve [pagina => bool] con el resultado de cada una. */
function regenerarTodoElContenido(): array
{
    $resultados = [];
    foreach (array_keys(esquemaPaginasContenido()) as $pagina) {
        $resultados[$pagina] = regenerarPaginaHtml($pagina);
    }
    return $resultados;
}

/**
 * Genera el HTML de unos datos ya finales y los publica: backup del estado
 * actual -> escribir JSON -> escribir HTML. Compartido por
 * guardarYPublicarContenido() y deshacerUltimoCambio() — ambos acaban en el
 * mismo sitio, solo cambia de dónde sale $datosFinales.
 */
function contenidoPublicarDatos(string $pagina, array $datosFinales): array
{
    $html = contenidoGenerarHtml($pagina, $datosFinales);
    if ($html === null) {
        return ['ok' => false, 'error' => 'No se ha podido generar la página con esos datos. No se ha guardado nada.'];
    }

    contenidoCrearBackup($pagina);

    // Cinturón de seguridad: si por lo que sea llegara algún byte de texto
    // que no sea UTF-8 válido (encoding raro del navegador, copia/pega
    // extraña...), que se sustituya por un carácter de reemplazo en vez de
    // que falle el guardado entero sin explicación.
    $json = json_encode($datosFinales, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false || !contenidoEscribirAtomico(contenidoRutaJsonVivo($pagina), $json)) {
        return ['ok' => false, 'error' => 'No se ha podido guardar el contenido.'];
    }

    if (!contenidoEscribirAtomico(contenidoRutaHtmlPublico($pagina), $html)) {
        return ['ok' => false, 'error' => 'El contenido se guardó, pero la página pública no se pudo actualizar. Prueba a regenerar todas las páginas.'];
    }

    return ['ok' => true, 'error' => ''];
}

/**
 * Guarda cambios de un formulario y publica el HTML resultante.
 * $camposNuevos: ['clave.con.puntos' => 'valor', ...] — solo los campos que
 * cambian; el resto del contenido vivo se mantiene igual.
 */
function guardarYPublicarContenido(string $pagina, array $camposNuevos, string $usuarioAdmin): array
{
    if (!isset(esquemaPaginasContenido()[$pagina])) {
        return ['ok' => false, 'error' => 'Página desconocida.'];
    }

    asegurarCarpetasContenido();

    $datosNuevos = leerContenidoVivo($pagina);
    foreach ($camposNuevos as $clave => $valor) {
        contenidoEstablecerValorPorRuta($datosNuevos, $clave, $valor);
    }
    $datosNuevos['_actualizado'] = date('c');
    $datosNuevos['_admin'] = $usuarioAdmin;

    return contenidoPublicarDatos($pagina, $datosNuevos);
}

/** Ruta del único backup (la versión anterior) de una página. */
function contenidoRutaBackup(string $pagina): string
{
    return CONTENIDO_BACKUPS_DIR . "/{$pagina}.json";
}

/** Guarda el JSON vivo actual como "versión anterior", sobrescribiendo el backup previo si lo había. */
function contenidoCrearBackup(string $pagina): void
{
    $rutaViva = contenidoRutaJsonVivo($pagina);
    if (!is_file($rutaViva)) {
        return; // primera vez que se guarda esta página: nada que respaldar
    }

    if (!is_dir(CONTENIDO_BACKUPS_DIR)) {
        @mkdir(CONTENIDO_BACKUPS_DIR, 0700, true);
    }

    @copy($rutaViva, contenidoRutaBackup($pagina));
}

/** Ruta del backup de una página, o null si todavía no hay ninguno. */
function contenidoUltimoBackup(string $pagina): ?string
{
    $ruta = contenidoRutaBackup($pagina);
    return is_file($ruta) ? $ruta : null;
}

function hayVersionAnterior(string $pagina): bool
{
    return contenidoUltimoBackup($pagina) !== null;
}

/**
 * Deshace el último cambio guardado: recupera el backup más reciente y lo
 * vuelve a publicar como contenido vivo. Como esto pasa por
 * contenidoPublicarDatos() igual que un guardado normal, también crea un
 * backup del estado que se abandona — así que "deshacer" es, a su vez,
 * deshacible pulsando el botón otra vez.
 */
function deshacerUltimoCambio(string $pagina, string $usuarioAdmin): array
{
    if (!isset(esquemaPaginasContenido()[$pagina])) {
        return ['ok' => false, 'error' => 'Página desconocida.'];
    }

    $rutaBackup = contenidoUltimoBackup($pagina);
    if ($rutaBackup === null) {
        return ['ok' => false, 'error' => 'Todavía no hay ninguna versión anterior guardada de esta página.'];
    }

    $datosBackup = json_decode((string) file_get_contents($rutaBackup), true);
    if (!is_array($datosBackup)) {
        return ['ok' => false, 'error' => 'La copia de seguridad anterior está dañada.'];
    }

    $datosBackup['_actualizado'] = date('c');
    $datosBackup['_admin'] = $usuarioAdmin;

    asegurarCarpetasContenido();

    return contenidoPublicarDatos($pagina, $datosBackup);
}

// ---------------------------------------------------------------------
// Datos de contacto (email/teléfono/LinkedIn), usados en toda la web vía
// JavaScript — no pasan por el mecanismo de plantillas/marcadores porque
// aparecen en el pie de página de TODAS las páginas, no solo en 5.
// ---------------------------------------------------------------------

function contenidoValoresContactoPorDefecto(): array
{
    // Los valores que ya tenía la web escritos a mano en las plantillas —
    // se usan mientras nadie haya guardado nada desde el panel todavía.
    return [
        'email' => 'ingenieria@capturam.es',
        'telefono_texto' => '656  66  50 24',
        'telefono_href' => '+34656665024',
        'linkedin' => 'https://www.linkedin.com/in/francisco-martin-lopez-acu%C3%B1a-234b00329/',
    ];
}

/**
 * Los datos públicos (email/teléfono/LinkedIn) más, solo para uso interno
 * del panel, "_actualizado"/"_admin" leídos del archivo privado aparte —
 * nunca se mezclan dentro del JSON público.
 */
function leerDatosContacto(): array
{
    $datos = contenidoValoresContactoPorDefecto();
    if (is_file(CONTENIDO_DATOS_CONTACTO_RUTA)) {
        $publicos = json_decode((string) file_get_contents(CONTENIDO_DATOS_CONTACTO_RUTA), true);
        if (is_array($publicos)) {
            $datos = $publicos;
        }
    }

    if (is_file(CONTENIDO_DATOS_CONTACTO_META_RUTA)) {
        $meta = json_decode((string) file_get_contents(CONTENIDO_DATOS_CONTACTO_META_RUTA), true);
        if (is_array($meta)) {
            $datos['_actualizado'] = $meta['_actualizado'] ?? '';
            $datos['_admin'] = $meta['_admin'] ?? '';
        }
    }

    return $datos;
}

function guardarDatosContacto(array $datosFormulario, string $usuarioAdmin): array
{
    $email = trim((string) ($datosFormulario['email'] ?? ''));
    $telefonoTexto = trim((string) ($datosFormulario['telefono_texto'] ?? ''));
    $telefonoHref = str_replace(' ', '', trim((string) ($datosFormulario['telefono_href'] ?? '')));
    $linkedin = trim((string) ($datosFormulario['linkedin'] ?? ''));

    if ($email === '' || mb_strlen($email) > 200 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return ['ok' => false, 'error' => 'El correo electrónico no es válido.'];
    }
    if ($telefonoTexto === '' || mb_strlen($telefonoTexto) > 40) {
        return ['ok' => false, 'error' => 'Indica un teléfono para mostrar en la web.'];
    }
    if (preg_match('/^\+?[0-9]{8,15}$/', $telefonoHref) !== 1) {
        return ['ok' => false, 'error' => 'El teléfono para el enlace debe ser solo números (puede empezar por "+"), sin espacios ni guiones.'];
    }
    if ($linkedin !== '' && (mb_strlen($linkedin) > 300 || filter_var($linkedin, FILTER_VALIDATE_URL) === false)) {
        return ['ok' => false, 'error' => 'El enlace de LinkedIn no es válido.'];
    }

    $datosPublicos = [
        'email' => $email,
        'telefono_texto' => $telefonoTexto,
        'telefono_href' => $telefonoHref,
        'linkedin' => $linkedin,
    ];

    $json = json_encode($datosPublicos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false || !contenidoEscribirAtomico(CONTENIDO_DATOS_CONTACTO_RUTA, $json)) {
        return ['ok' => false, 'error' => 'No se ha podido guardar.'];
    }

    asegurarCarpetasContenido();
    $meta = [
        '_actualizado' => date('c'),
        '_admin' => $usuarioAdmin,
    ];
    $jsonMeta = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($jsonMeta !== false) {
        contenidoEscribirAtomico(CONTENIDO_DATOS_CONTACTO_META_RUTA, $jsonMeta);
    }

    return ['ok' => true, 'error' => ''];
}
