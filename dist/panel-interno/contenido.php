<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/_contenido.php';
requerirSesionAdmin();

const CONTENIDO_MAX_IMG_BYTES = 8 * 1024 * 1024; // 8 MB, de sobra para fotos de web

$esquema = esquemaPaginasContenido();
$paginas = array_keys($esquema);
const CONTENIDO_PAGINA_CONTACTO = 'datos-contacto'; // pestaña extra: no es una página con plantilla, guarda directo en datos-contacto.json

$paginaActual = (string) ($_GET['pagina'] ?? $paginas[0]);
if ($paginaActual !== CONTENIDO_PAGINA_CONTACTO && !isset($esquema[$paginaActual])) {
    $paginaActual = $paginas[0];
}

$error = '';
$exito = '';
$csrf = tokenCsrf();

if (!empty($_SESSION['aviso_flash'])) {
    $error = $_SESSION['aviso_flash'];
    unset($_SESSION['aviso_flash']);
}

function e(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}

/** Valida y guarda una imagen nueva subida para un campo tipo "imagen". Devuelve el nombre de archivo generado, o null si no se subió nada válido. */
function contenidoProcesarImagenSubida(array $archivo): array
{
    // ['ok' => bool, 'nombre' => string, 'error' => string]
    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'nombre' => null, 'error' => '']; // no se subió nada: no es un error
    }
    if (($archivo['error'] ?? 1) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'nombre' => null, 'error' => 'No se ha podido subir la imagen (¿demasiado grande o conexión interrumpida?).'];
    }
    if ($archivo['size'] > CONTENIDO_MAX_IMG_BYTES) {
        return ['ok' => false, 'nombre' => null, 'error' => 'La imagen supera el tamaño máximo permitido (8 MB).'];
    }

    $detectado = detectarTipoArchivo($archivo['tmp_name']);
    if ($detectado === null || $detectado['tipo'] !== 'imagen') {
        return ['ok' => false, 'nombre' => null, 'error' => 'El archivo debe ser una imagen JPG, PNG o WEBP válida (no se admite SVG ni PDF aquí).'];
    }

    $dimensiones = @getimagesize($archivo['tmp_name']);
    if ($dimensiones === false || $dimensiones[0] > CONTENIDO_IMG_MAX_DIMENSION || $dimensiones[1] > CONTENIDO_IMG_MAX_DIMENSION) {
        return ['ok' => false, 'nombre' => null, 'error' => 'La imagen es demasiado grande en píxeles (máximo ' . CONTENIDO_IMG_MAX_DIMENSION . 'x' . CONTENIDO_IMG_MAX_DIMENSION . ').'];
    }

    asegurarCarpetasContenido();
    $nombreArchivo = date('Y-m-d') . '-' . bin2hex(random_bytes(8)) . '.' . $detectado['extension'];
    $destino = CONTENIDO_UPLOADS_DIR . '/' . $nombreArchivo;

    if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
        return ['ok' => false, 'nombre' => null, 'error' => 'No se ha podido guardar la imagen en el servidor.'];
    }

    return ['ok' => true, 'nombre' => $nombreArchivo, 'error' => ''];
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $accion = (string) ($_POST['accion'] ?? '');
    $token = (string) ($_POST['csrf'] ?? '');

    if (!csrfValido($token)) {
        $_SESSION['aviso_flash'] = 'La página llevaba abierta demasiado tiempo y hubo que recargarla. Ya puedes continuar.';
        header('Location: contenido?pagina=' . rawurlencode($paginaActual));
        exit;
    }

    if ($accion === 'regenerar_todas') {
        $resultados = regenerarTodoElContenido();
        $fallidas = array_keys(array_filter($resultados, fn ($ok) => !$ok));
        if ($fallidas === []) {
            $_SESSION['aviso_exito_flash'] = 'Todas las páginas se han regenerado correctamente.';
        } else {
            $_SESSION['aviso_flash'] = 'No se pudieron regenerar: ' . implode(', ', $fallidas) . '.';
        }
        header('Location: contenido?pagina=' . rawurlencode($paginaActual));
        exit;
    }

    if ($accion === 'deshacer') {
        $paginaForm = (string) ($_POST['pagina'] ?? '');
        if (!isset($esquema[$paginaForm])) {
            $error = 'Página desconocida.';
        } else {
            $paginaActual = $paginaForm;
            $resultadoDeshacer = deshacerUltimoCambio($paginaForm, sesionUsuarioActual());
            if ($resultadoDeshacer['ok']) {
                $exito = 'Se ha vuelto a la versión anterior.';
                if ($paginaForm === 'sectores') {
                    regenerarPaginaHtml('inicio');
                }
            } else {
                $error = $resultadoDeshacer['error'];
            }
        }
    }

    if ($accion === 'guardar_contacto') {
        $paginaActual = CONTENIDO_PAGINA_CONTACTO;
        $resultadoContacto = guardarDatosContacto((array) ($_POST['contacto'] ?? []), sesionUsuarioActual());
        if ($resultadoContacto['ok']) {
            $exito = 'Datos de contacto guardados y publicados.';
        } else {
            $error = $resultadoContacto['error'];
        }
    }

    if ($accion === 'guardar') {
        $paginaForm = (string) ($_POST['pagina'] ?? '');
        if (!isset($esquema[$paginaForm])) {
            $error = 'Página desconocida.';
        } else {
            $paginaActual = $paginaForm;
            $camposTexto = (array) ($_POST['campos'] ?? []);
            $archivosImagen = (array) ($_FILES['imagenes'] ?? []);

            $camposNuevos = [];
            $errorImagen = '';

            foreach ($esquema[$paginaForm]['campos'] as $clave => $definicion) {
                if (isset($definicion['compartido_desde'])) {
                    continue; // no tiene formulario propio, se edita desde la página de origen
                }
                if ($definicion['tipo'] === 'imagen') {
                    $archivo = null;
                    if (isset($archivosImagen['name'][$clave])) {
                        $archivo = [
                            'name' => $archivosImagen['name'][$clave],
                            'type' => $archivosImagen['type'][$clave],
                            'tmp_name' => $archivosImagen['tmp_name'][$clave],
                            'error' => $archivosImagen['error'][$clave],
                            'size' => $archivosImagen['size'][$clave],
                        ];
                    }
                    if ($archivo !== null) {
                        $resultado = contenidoProcesarImagenSubida($archivo);
                        if (!$resultado['ok']) {
                            $errorImagen = $resultado['error'];
                            break;
                        }
                        if ($resultado['nombre'] !== null) {
                            $camposNuevos[$clave] = $resultado['nombre'];
                        }
                    }
                } else {
                    $valor = (string) ($camposTexto[$clave] ?? '');
                    $camposNuevos[$clave] = contenidoSaneaTexto($valor, $definicion['max'] ?? 1000);
                }
            }

            if ($errorImagen !== '') {
                $error = $errorImagen;
            } else {
                $resultadoGuardado = guardarYPublicarContenido($paginaForm, $camposNuevos, sesionUsuarioActual());
                if ($resultadoGuardado['ok']) {
                    $exito = 'Cambios guardados y publicados.';
                    // Las fotos de Sectores también se ven en el bloque
                    // "Sectores" de Inicio (campos "compartido_desde") —
                    // hay que regenerar Inicio también para que lo recoja.
                    if ($paginaForm === 'sectores') {
                        regenerarPaginaHtml('inicio');
                    }
                } else {
                    $error = $resultadoGuardado['error'];
                }
            }
        }
    }
}

if (!empty($_SESSION['aviso_exito_flash'])) {
    $exito = $_SESSION['aviso_exito_flash'];
    unset($_SESSION['aviso_exito_flash']);
}

$esContacto = $paginaActual === CONTENIDO_PAGINA_CONTACTO;
$datosPagina = $esContacto ? leerDatosContacto() : leerContenidoVivo($paginaActual);
$ultimaActualizacion = (string) ($datosPagina['_actualizado'] ?? '');
$ultimoAdmin = (string) ($datosPagina['_admin'] ?? '');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Contenido web · Panel Capturam</title>
<link rel="icon" href="../static/img/favicon-panel-interno.ico" sizes="any">
<link rel="icon" type="image/png" sizes="16x16" href="../static/img/favicon-panel-interno-16.png">
<link rel="icon" type="image/png" sizes="32x32" href="../static/img/favicon-panel-interno-32.png">
<link rel="icon" type="image/png" sizes="48x48" href="../static/img/favicon-panel-interno-48.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@700&display=swap" rel="stylesheet">
<style>
  :root { --azul-profundo:#0b2b3c; --verde-agua:#3fb8a4; --azul-pizarra:#4b6472; --blanco-calido:#f7f8f6; }
  * { box-sizing: border-box; }
  body {
    margin:0; min-height:100vh; display:flex; flex-direction:column;
    background: var(--blanco-calido); font-family:'Inter',sans-serif; color:var(--azul-profundo);
  }
  header {
    /* Mismo color y estructura que el navbar de la web pública. */
    background: #1e3c4b; color:#fff; padding:14px 28px;
    display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px;
  }
  .header-marca { display:flex; align-items:center; gap:20px; }
  .header-marca img { height:44px; width:auto; display:block; }
  .enlace-salir {
    color:#f3f6f4; text-decoration:none; font-size:.9rem; font-weight:600; opacity:.85;
    border-left:1px solid rgba(255,255,255,.22); padding-left:20px; transition:opacity .15s, color .15s;
  }
  .enlace-salir:hover { opacity:1; color:#91b5b4; }
  header nav { display:flex; gap:30px; align-items:center; }
  header nav a {
    position:relative; color:#f3f6f4; text-decoration:none; font-weight:600; font-size:.95rem;
    opacity:.85; padding-bottom:4px; transition:opacity .15s, color .15s;
  }
  header nav a::after {
    content:""; position:absolute; left:0; bottom:0; width:0; height:2px;
    background-color:#91b5b4; transition:width .2s;
  }
  header nav a:hover, header nav a.activa { color:#91b5b4; opacity:1; }
  header nav a:hover::after, header nav a.activa::after { width:100%; }
  .menu-toggle {
    display:none; background:transparent; border:1px solid rgba(255,255,255,.4); color:#fff;
    font-size:1.2rem; line-height:1; padding:7px 12px; border-radius:6px; cursor:pointer;
  }
  @media (max-width: 860px) {
    body { overflow-x:hidden; }
    .menu-toggle { display:block; }
    header nav {
      display:none; flex-basis:100%; flex-direction:column; align-items:flex-start; gap:2px;
      padding-top:14px; margin-top:14px; border-top:1px solid rgba(255,255,255,.15);
    }
    header nav.abierta { display:flex; }
    header nav a { width:100%; padding:10px 0; }
    header nav a::after { display:none; }
  }
  main { max-width: 900px; width: 100%; margin: 0 auto; padding: 32px 20px 64px; flex: 1 0 auto; }

  @media (min-width: 1920px) {
    body { zoom: clamp(1, calc(1 + (100vw - 1920px) / 3840px), 2); }
  }

  .aviso { border-radius:8px; padding:12px 16px; font-size:.9rem; margin-bottom:24px; }
  .aviso-error { background:#fdecea; color:#a33; }
  .aviso-exito { background:#e6f6f2; color:#1a7a63; }

  .tarjeta { background:#fff; border:1px solid #e2e5ea; border-radius:12px; padding:26px 28px; margin-top:32px; margin-bottom:24px; box-shadow:0 2px 10px rgba(11,43,60,.04); }
  .tarjeta h2 { font-family:'Poppins',sans-serif; font-size:1.1rem; margin:0 0 4px; }
  .meta-actualizacion { font-size:.8rem; color:var(--azul-pizarra); margin:0 0 22px; padding-bottom:18px; border-bottom:1px solid #eef0f2; }
  .meta-actualizacion-fila { display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap; }
  .boton-deshacer {
    border:1px solid #d5dbe0; background:#fff; color:var(--azul-pizarra); font-size:.8rem; font-weight:600;
    padding:6px 12px; border-radius:6px; cursor:pointer; font-family:inherit; white-space:nowrap;
  }
  .boton-deshacer:hover { border-color:var(--azul-profundo); color:var(--azul-profundo); }

  label { display:block; font-size:.85rem; color:var(--azul-pizarra); margin-bottom:6px; font-weight:600; }
  input[type=text], input[type=file], textarea {
    width:100%; padding:9px 11px; border:1px solid #d5dbe0; border-radius:6px;
    font-size:.95rem; font-family:inherit; margin-bottom:6px; background:#fff;
  }
  input[type=text]:focus, textarea:focus {
    outline:none; border-color:var(--verde-agua); box-shadow:0 0 0 3px rgba(63,184,164,.15);
  }
  textarea { resize:vertical; min-height:90px; }
  .campo {
    margin-bottom:20px; padding-bottom:20px; border-bottom:1px solid #f0f2f4;
  }
  .campo:last-of-type { border-bottom:none; margin-bottom:0; padding-bottom:0; }
  .ayuda-formato { font-size:.8rem; color:var(--azul-pizarra); margin:0 0 14px; }
  .ayuda-formato code { background:#f0f2f4; border-radius:4px; padding:1px 5px; }

  .previsualizacion { display:flex; align-items:center; gap:14px; margin-bottom:10px; }
  .previsualizacion img {
    width:110px; height:72px; object-fit:cover; border-radius:6px; border:1px solid #e2e5ea; background:#f0f2f4;
  }

  button.principal, button.secundario {
    padding:10px 22px; border-radius:6px; font-weight:600; font-size:.95rem; cursor:pointer; font-family:inherit; transition: all .15s;
  }
  button.principal { border:none; background:var(--azul-profundo); color:#fff; }
  button.principal:hover { background:var(--azul-pizarra); }
  button.secundario { border:1px solid var(--azul-profundo); background:#fff; color:var(--azul-profundo); }
  button.secundario:hover { background:#f0f2f4; }
  .barra-guardar { display:flex; align-items:center; gap:14px; margin-top:8px; }
</style>
</head>
<body>
  <header>
    <div class="header-marca">
      <img src="../static/img/logo-capturam-claro.png" alt="Capturam Ingeniería">
      <a href="logout" class="enlace-salir">Cerrar sesión</a>
    </div>
    <button type="button" class="menu-toggle" onclick="document.getElementById('menu-panel').classList.toggle('abierta')" aria-label="Abrir menú">☰</button>
    <nav id="menu-panel">
      <?php foreach (ordenNavPanel() as $item): ?>
        <?php if ($item['tipo'] === 'publicaciones'): ?>
          <a href="panel">Publicaciones</a>
        <?php elseif ($item['tipo'] === 'contacto'): ?>
          <a class="<?= $esContacto ? 'activa' : '' ?>" href="contenido?pagina=<?= rawurlencode(CONTENIDO_PAGINA_CONTACTO) ?>">Datos de contacto</a>
        <?php else: ?>
          <a class="<?= $item['clave'] === $paginaActual ? 'activa' : '' ?>" href="contenido?pagina=<?= rawurlencode($item['clave']) ?>">
            <?= e($esquema[$item['clave']]['etiqueta']) ?>
          </a>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>
  </header>

  <main>
    <?php if ($error !== ''): ?>
      <div class="aviso aviso-error"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($exito !== ''): ?>
      <div class="aviso aviso-exito"><?= e($exito) ?></div>
    <?php endif; ?>

    <?php if ($esContacto): ?>
      <div class="tarjeta">
        <h2>Datos de contacto</h2>
        <p class="meta-actualizacion">
          <?php if ($ultimaActualizacion !== ''): ?>
            Última modificación: <?= e(date('d/m/Y H:i', strtotime($ultimaActualizacion) ?: time())) ?>
            <?= $ultimoAdmin !== '' ? ' — ' . e($ultimoAdmin) : '' ?>
          <?php else: ?>
            Todavía no se ha guardado ningún cambio (se muestran los datos actuales de la web).
          <?php endif; ?>
        </p>
        <p class="ayuda-formato">
          Se actualiza automáticamente en toda la web: pie de página, la página de Contacto, y las menciones en
          el Aviso legal y la Política de privacidad.
        </p>

        <form method="post">
          <input type="hidden" name="accion" value="guardar_contacto">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

          <div class="campo">
            <label for="c-email">Correo electrónico</label>
            <input type="text" id="c-email" name="contacto[email]" value="<?= e((string) ($datosPagina['email'] ?? '')) ?>" required>
          </div>
          <div class="campo">
            <label for="c-tel-texto">Teléfono (como se muestra en la web)</label>
            <input type="text" id="c-tel-texto" name="contacto[telefono_texto]" value="<?= e((string) ($datosPagina['telefono_texto'] ?? '')) ?>" required>
            <p class="ayuda-formato">Por ejemplo: <code>656  66  50 24</code></p>
          </div>
          <div class="campo">
            <label for="c-tel-href">Teléfono (solo números, para el botón de llamar)</label>
            <input type="text" id="c-tel-href" name="contacto[telefono_href]" value="<?= e((string) ($datosPagina['telefono_href'] ?? '')) ?>" required>
            <p class="ayuda-formato">Con prefijo de país, sin espacios. Por ejemplo: <code>+34656665024</code></p>
          </div>
          <div class="campo">
            <label for="c-linkedin">Enlace de LinkedIn</label>
            <input type="text" id="c-linkedin" name="contacto[linkedin]" value="<?= e((string) ($datosPagina['linkedin'] ?? '')) ?>">
          </div>

          <div class="barra-guardar">
            <button type="submit" class="principal">Guardar y publicar</button>
          </div>
        </form>
      </div>
    <?php else: ?>
    <div class="tarjeta">
      <h2><?= e($esquema[$paginaActual]['etiqueta']) ?></h2>
      <p class="meta-actualizacion meta-actualizacion-fila">
        <span>
          <?php if ($ultimaActualizacion !== ''): ?>
            Última modificación: <?= e(date('d/m/Y H:i', strtotime($ultimaActualizacion) ?: time())) ?>
            <?= $ultimoAdmin !== '' ? ' — ' . e($ultimoAdmin) : '' ?>
          <?php else: ?>
            Todavía no se ha guardado ningún cambio en esta página (se muestra el contenido de fábrica).
          <?php endif; ?>
        </span>
        <?php if (hayVersionAnterior($paginaActual)): ?>
          <form method="post" onsubmit="return confirm('¿Volver a la versión anterior de esta página? El cambio actual quedará guardado como copia, por si quieres deshacerlo también.');">
            <input type="hidden" name="accion" value="deshacer">
            <input type="hidden" name="pagina" value="<?= e($paginaActual) ?>">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <button type="submit" class="boton-deshacer">↺ Volver a la versión anterior</button>
          </form>
        <?php endif; ?>
      </p>

      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="pagina" value="<?= e($paginaActual) ?>">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

        <?php foreach ($esquema[$paginaActual]['campos'] as $clave => $definicion):
          if (isset($definicion['compartido_desde'])) {
              continue; // se edita desde la página de origen, no tiene campo propio aquí
          }
          $valorActual = contenidoValorPorRuta($datosPagina, $clave);
          $valorActual = is_string($valorActual) ? $valorActual : '';
        ?>
          <div class="campo">
            <label for="campo-<?= e($clave) ?>"><?= e($definicion['etiqueta']) ?></label>

            <?php if ($definicion['tipo'] === 'imagen'): ?>
              <div class="previsualizacion">
                <img id="previsualizacion-<?= e($clave) ?>" src="<?= e(contenidoRutaImagenSegura($valorActual)) ?>" alt="">
                <input type="file" id="campo-<?= e($clave) ?>" name="imagenes[<?= e($clave) ?>]" accept="image/jpeg,image/png,image/webp"
                       class="input-imagen" data-previsualizacion="previsualizacion-<?= e($clave) ?>">
              </div>
              <p class="ayuda-formato">JPG, PNG o WEBP, máximo 8 MB. Déjalo en blanco para no cambiar la foto actual.</p>
            <?php elseif ($definicion['tipo'] === 'texto_multiparrafo'): ?>
              <textarea id="campo-<?= e($clave) ?>" name="campos[<?= e($clave) ?>]" maxlength="<?= (int) ($definicion['max'] ?? 1000) ?>" style="min-height:150px"><?= e($valorActual) ?></textarea>
              <p class="ayuda-formato">Deja una línea en blanco entre párrafos. Puedes usar <code>**negrita**</code> y listas (una línea por punto, empezando por <code>- </code>).</p>
            <?php else: ?>
              <textarea id="campo-<?= e($clave) ?>" name="campos[<?= e($clave) ?>]" maxlength="<?= (int) ($definicion['max'] ?? 1000) ?>"><?= e($valorActual) ?></textarea>
              <p class="ayuda-formato">Un único párrafo (los saltos de línea se ignoran). Puedes usar <code>**negrita**</code>.</p>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

        <div class="barra-guardar">
          <button type="submit" class="principal">Guardar y publicar</button>
        </div>
      </form>
    </div>
    <?php endif; ?>
  </main>
  <script>
    document.querySelectorAll('.input-imagen').forEach(function (input) {
      input.addEventListener('change', function () {
        if (!this.files || !this.files[0]) return;
        var img = document.getElementById(this.getAttribute('data-previsualizacion'));
        if (img) img.src = URL.createObjectURL(this.files[0]);
      });
    });
  </script>
</body>
</html>
