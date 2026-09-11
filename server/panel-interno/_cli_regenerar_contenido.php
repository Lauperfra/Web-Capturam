<?php
/**
 * Regenera dist/<pagina>.html para las 5 páginas de "Contenido web" a
 * partir de la plantilla más reciente (plantillas-contenido/) y el JSON
 * vivo existente (contenido-datos/), sin tocar ese JSON.
 *
 * Pensado para ejecutarse por línea de comandos tras desplegar cambios en
 * las plantillas (build.py lo invoca así en local), y también se ofrece
 * como botón "Regenerar todas las páginas" dentro del panel — ambos casos
 * llaman a la misma función, regenerarTodoElContenido().
 *
 * No debe pedirse nunca por URL (bloqueado en panel-interno/.htaccess).
 * Si alguien lo pidiera igualmente por HTTP, php_sapi_name() lo corta aquí.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require __DIR__ . '/_contenido.php';

$resultados = regenerarTodoElContenido();

$fallos = 0;
foreach ($resultados as $pagina => $ok) {
    echo ($ok ? '[OK]   ' : '[FALLO]') . ' ' . $pagina . "\n";
    if (!$ok) {
        $fallos++;
    }
}

if ($fallos > 0) {
    fwrite(STDERR, "\n{$fallos} página(s) no se pudieron regenerar — revisa que exista su plantilla en plantillas-contenido/ y que el JSON vivo (o el de fábrica) tenga todos los campos.\n");
    exit(1);
}

echo "\nTodo regenerado correctamente.\n";
