<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
requerirSesionAdmin();

const MAX_ARCHIVO_BYTES = 15 * 1024 * 1024; // 15 MB
const TITULO_MAX = 150;
const RESUMEN_MAX = 3000; // como el limite de un post de LinkedIn

function generarId(): string
{
    return bin2hex(random_bytes(8));
}

/**
 * Detecta si el archivo subido es realmente un PDF o una imagen (JPG, PNG o
 * WEBP), mirando el contenido — nunca el nombre ni la extensión que mande el
 * navegador. Devuelve null si no es ninguno de los dos.
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

function slug(string $texto): string
{
    $transliterado = @iconv('UTF-8', 'ASCII//TRANSLIT', $texto);
    $texto = strtolower($transliterado !== false ? $transliterado : $texto);
    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto) ?? '';
    $texto = trim($texto, '-');
    return $texto !== '' ? $texto : 'publicacion';
}

$error = '';
$exito = '';
$edicionGuardada = false; // true tras guardar una edición: se sale del modo edición

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $token = (string) ($_POST['csrf'] ?? '');

    // Si el archivo subido supera "post_max_size", PHP vacía $_POST y
    // $_FILES enteros antes de que el script llegue a ejecutarse — sin
    // este chequeo, eso se ve exactamente igual que un fallo de sesión
    // (el campo csrf también desaparece), con un mensaje engañoso.
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if (empty($_POST) && empty($_FILES) && $contentLength > 0) {
        $_SESSION['aviso_flash'] = 'El archivo es demasiado grande para el servidor (supera '
            . ini_get('post_max_size') . '). Prueba con un archivo más pequeño o pide que se amplíe el límite del hosting.';
        header('Location: panel.php');
        exit;
    }

    if (!csrfValido($token)) {
        // No nos limitamos a mostrar un error en la misma página: si el
        // navegador reenvía el POST al recargar (F5), se vería el mismo
        // error en bucle sin poder hacer nada. Mejor redirigir a un
        // formulario limpio (con su propio token nuevo). El aviso se guarda
        // en la sesión (no en la URL) para que se muestre una sola vez y no
        // reaparezca cada vez que se recargue esa misma página.
        $_SESSION['aviso_flash'] = 'La página llevaba abierta demasiado tiempo y hubo que recargarla. Ya puedes continuar.';
        $idPost = (string) ($_POST['id'] ?? '');
        $destino = 'panel.php';
        if ($idPost !== '' && ($_POST['accion'] ?? '') === 'editar') {
            $destino .= '?editar=' . rawurlencode($idPost);
        }
        header('Location: ' . $destino);
        exit;
    } else {
        $accion = (string) ($_POST['accion'] ?? '');

        if ($accion === 'crear') {
            $titulo = trim(strip_tags((string) ($_POST['titulo'] ?? '')));
            $resumen = trim(strip_tags((string) ($_POST['resumen'] ?? '')));
            $fecha = (string) ($_POST['fecha'] ?? '');

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                $fecha = date('Y-m-d');
            }

            if ($titulo === '' || mb_strlen($titulo) > TITULO_MAX) {
                $error = 'Indica un título (máximo ' . TITULO_MAX . ' caracteres).';
            } elseif (mb_strlen($resumen) > RESUMEN_MAX) {
                $error = 'El resumen es demasiado largo (máximo ' . RESUMEN_MAX . ' caracteres).';
            } elseif (empty($_FILES['archivo']) || ($_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                $error = 'Selecciona un PDF o una imagen.';
            } elseif (($_FILES['archivo']['error'] ?? 1) !== UPLOAD_ERR_OK) {
                $error = 'No se ha podido subir el archivo (¿demasiado grande o conexión interrumpida?).';
            } elseif ($_FILES['archivo']['size'] > MAX_ARCHIVO_BYTES) {
                $error = 'El archivo supera el tamaño máximo permitido (15 MB).';
            } else {
                $tmpPath = $_FILES['archivo']['tmp_name'];
                $detectado = detectarTipoArchivo($tmpPath);

                if ($detectado === null) {
                    $error = 'El archivo debe ser un PDF o una imagen (JPG, PNG o WEBP) válidos.';
                } else {
                    asegurarCarpetaPublicaciones();
                    $nombreArchivo = date('Y-m-d') . '-' . slug($titulo) . '-' . substr(generarId(), 0, 6) . '.' . $detectado['extension'];
                    $destino = CARPETA_PUBLICACIONES . '/' . $nombreArchivo;

                    if (!move_uploaded_file($tmpPath, $destino)) {
                        $error = 'No se ha podido guardar el archivo en el servidor.';
                    } else {
                        $publicaciones = leerPublicaciones();
                        array_unshift($publicaciones, [
                            'id' => generarId(),
                            'titulo' => $titulo,
                            'resumen' => $resumen,
                            'fecha' => $fecha,
                            'archivo' => $nombreArchivo,
                            'tipo' => $detectado['tipo'],
                            'creado' => date('c'),
                        ]);
                        guardarPublicaciones($publicaciones);
                        $exito = 'Publicación añadida.';
                    }
                }
            }
        } elseif ($accion === 'editar') {
            $idEditar = (string) ($_POST['id'] ?? '');
            $titulo = trim(strip_tags((string) ($_POST['titulo'] ?? '')));
            $resumen = trim(strip_tags((string) ($_POST['resumen'] ?? '')));
            $fecha = (string) ($_POST['fecha'] ?? '');

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                $fecha = date('Y-m-d');
            }

            $publicaciones = leerPublicaciones();
            $indice = null;
            foreach ($publicaciones as $i => $publicacion) {
                if (($publicacion['id'] ?? '') === $idEditar) {
                    $indice = $i;
                    break;
                }
            }

            // Un archivo nuevo es opcional al editar: si no se sube ninguno
            // se mantiene el que ya tenía la publicación.
            $subioArchivo = !empty($_FILES['archivo'])
                && ($_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

            if ($indice === null) {
                $error = 'La publicación que intentas editar ya no existe.';
            } elseif ($titulo === '' || mb_strlen($titulo) > TITULO_MAX) {
                $error = 'Indica un título (máximo ' . TITULO_MAX . ' caracteres).';
            } elseif (mb_strlen($resumen) > RESUMEN_MAX) {
                $error = 'El resumen es demasiado largo (máximo ' . RESUMEN_MAX . ' caracteres).';
            } elseif ($subioArchivo && ($_FILES['archivo']['error'] ?? 1) !== UPLOAD_ERR_OK) {
                $error = 'No se ha podido subir el archivo (¿demasiado grande o conexión interrumpida?).';
            } elseif ($subioArchivo && $_FILES['archivo']['size'] > MAX_ARCHIVO_BYTES) {
                $error = 'El archivo supera el tamaño máximo permitido (15 MB).';
            } else {
                $archivoNuevo = null;

                if ($subioArchivo) {
                    $detectado = detectarTipoArchivo($_FILES['archivo']['tmp_name']);
                    if ($detectado === null) {
                        $error = 'El archivo debe ser un PDF o una imagen (JPG, PNG o WEBP) válidos.';
                    } else {
                        asegurarCarpetaPublicaciones();
                        $nombreArchivo = date('Y-m-d') . '-' . slug($titulo) . '-' . substr(generarId(), 0, 6) . '.' . $detectado['extension'];
                        $destino = CARPETA_PUBLICACIONES . '/' . $nombreArchivo;

                        if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $destino)) {
                            $error = 'No se ha podido guardar el archivo en el servidor.';
                        } else {
                            $archivoNuevo = ['archivo' => $nombreArchivo, 'tipo' => $detectado['tipo']];
                        }
                    }
                }

                if ($error === '') {
                    $archivoAntiguo = (string) ($publicaciones[$indice]['archivo'] ?? '');
                    $publicaciones[$indice]['titulo'] = $titulo;
                    $publicaciones[$indice]['resumen'] = $resumen;
                    $publicaciones[$indice]['fecha'] = $fecha;

                    if ($archivoNuevo !== null) {
                        $publicaciones[$indice]['archivo'] = $archivoNuevo['archivo'];
                        $publicaciones[$indice]['tipo'] = $archivoNuevo['tipo'];
                    }

                    guardarPublicaciones($publicaciones);

                    if ($archivoNuevo !== null && $archivoAntiguo !== '') {
                        $rutaAntigua = CARPETA_PUBLICACIONES . '/' . basename($archivoAntiguo);
                        if (is_file($rutaAntigua)) {
                            @unlink($rutaAntigua);
                        }
                    }

                    $exito = 'Publicación actualizada.';
                    $edicionGuardada = true;
                }
            }
        } elseif ($accion === 'eliminar') {
            $id = (string) ($_POST['id'] ?? '');
            $publicaciones = leerPublicaciones();
            $restantes = [];
            $borrada = null;

            foreach ($publicaciones as $publicacion) {
                if (($publicacion['id'] ?? '') === $id) {
                    $borrada = $publicacion;
                    continue;
                }
                $restantes[] = $publicacion;
            }

            if ($borrada !== null) {
                guardarPublicaciones($restantes);
                $rutaArchivo = CARPETA_PUBLICACIONES . '/' . basename((string) $borrada['archivo']);
                if (is_file($rutaArchivo)) {
                    @unlink($rutaArchivo);
                }
                $exito = 'Publicación eliminada.';
            }
        }
    }
}

$publicaciones = leerPublicaciones();
usort($publicaciones, fn($a, $b) => strcmp((string) ($b['fecha'] ?? ''), (string) ($a['fecha'] ?? '')));
$csrf = tokenCsrf();

if (!empty($_SESSION['aviso_flash'])) {
    $error = $_SESSION['aviso_flash'];
    unset($_SESSION['aviso_flash']); // se muestra una sola vez
}

function e(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}

// Modo edición: se activa navegando a "?editar=<id>" (enlace "Editar" de la
// lista). Si el id no existe ya no se pasa nada raro: se cae de vuelta al
// formulario de alta.
$idEditando = (!$edicionGuardada && isset($_GET['editar'])) ? (string) $_GET['editar'] : '';
$editando = null;
if ($idEditando !== '') {
    foreach ($publicaciones as $publicacion) {
        if (($publicacion['id'] ?? '') === $idEditando) {
            $editando = $publicacion;
            break;
        }
    }
    if ($editando === null) {
        $idEditando = '';
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Publicaciones · Panel Capturam</title>
<link rel="icon" href="../static/img/favicon-admin.ico" sizes="any">
<link rel="icon" type="image/png" sizes="16x16" href="../static/img/favicon-admin-16.png">
<link rel="icon" type="image/png" sizes="32x32" href="../static/img/favicon-admin-32.png">
<link rel="icon" type="image/png" sizes="48x48" href="../static/img/favicon-admin-48.png">
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
    background: var(--azul-profundo);
    color: #fff;
    padding: 18px 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
  }
  header h1 { font-family:'Poppins',sans-serif; font-size:1.15rem; margin:0; }
  header a { color:#fff; text-decoration:none; font-size:.9rem; opacity:.85; }
  header a:hover { opacity:1; text-decoration:underline; }
  main { max-width: 900px; width: 100%; margin: 0 auto; padding: 32px 20px 64px; flex: 1 0 auto; }

  /* En monitores muy anchos no basta con ensanchar el recuadro: si el
     texto se queda con el tamaño de siempre, se ve minúsculo igualmente.
     Con "zoom" se agranda TODO por igual (letra, botones, espaciados,
     el propio ancho del recuadro...) manteniendo las mismas proporciones
     que en una pantalla normal — como si se acercara la imagen entera.
     Igual que siempre hasta 1920px; a partir de ahí crece con la pantalla. */
  @media (min-width: 1920px) {
    body { zoom: clamp(1, calc(1 + (100vw - 1920px) / 3840px), 2); }
  }
  .aviso { border-radius:8px; padding:12px 16px; font-size:.9rem; margin-bottom:24px; }
  .aviso-error { background:#fdecea; color:#a33; }
  .aviso-exito { background:#e6f6f2; color:#1a7a63; }
  .tarjeta { background:#fff; border:1px solid #e2e5ea; border-radius:10px; padding:24px; margin-bottom:32px; }
  .tarjeta h2 { font-size:1.05rem; margin:0 0 18px; }
  label { display:block; font-size:.85rem; color:var(--azul-pizarra); margin-bottom:6px; font-weight:600; }
  input[type=text], input[type=date], textarea, input[type=file] {
    width:100%; padding:9px 11px; border:1px solid #d5dbe0; border-radius:6px;
    font-size:.95rem; font-family:inherit; margin-bottom:16px;
  }
  textarea { resize:vertical; min-height:180px; }
  .ayuda-formato { font-size:.8rem; color:var(--azul-pizarra); margin:-10px 0 16px; }
  .ayuda-formato code { background:#f0f2f4; border-radius:4px; padding:1px 5px; }
  .fila-doble { display:flex; gap:16px; flex-wrap:wrap; }
  .fila-doble > div { flex:1; min-width:180px; }
  button.principal {
    padding:10px 22px; border:none; border-radius:6px; background:var(--azul-profundo);
    color:#fff; font-weight:600; font-size:.95rem; cursor:pointer; font-family:inherit;
  }
  button.principal:hover { background:var(--azul-pizarra); }
  .enlace-cancelar { margin-left:12px; font-size:.9rem; color:var(--azul-pizarra); }
  li.editando { background:#f0f7f6; border-radius:8px; padding-left:8px; padding-right:8px; }
  ul.lista { list-style:none; margin:0; padding:0; }
  ul.lista li {
    display:flex; align-items:center; justify-content:space-between; gap:16px;
    flex-wrap:wrap; padding:16px 0; border-bottom:1px solid #e2e5ea;
  }
  ul.lista li:last-child { border-bottom:none; }
  .pub-info { min-width:0; flex:1 1 260px; }
  .pub-info h3 { font-size:.98rem; margin:0 0 4px; }
  .pub-info span { font-size:.8rem; color:var(--verde-agua); font-weight:700; }
  .pub-info p { font-size:.85rem; color:var(--azul-pizarra); margin:4px 0 0; max-width:520px; }
  .pub-acciones { display:flex; align-items:center; gap:12px; flex-shrink:0; }
  .pub-acciones a { font-size:.85rem; color:var(--azul-profundo); }
  button.enlace-ver {
    background:none; border:none; padding:0; margin:0; font-family:inherit;
    font-size:.85rem; color:var(--azul-profundo); text-decoration:underline; cursor:pointer;
  }
  button.borrar {
    background:none; border:1px solid #d99; color:#a33; border-radius:6px;
    padding:6px 12px; font-size:.8rem; cursor:pointer; font-family:inherit;
  }
  button.borrar:hover { background:#fdecea; }
  .vacio { color:var(--azul-pizarra); font-size:.9rem; }

  /* Visor a pantalla completa para "Ver imagen"/"Ver PDF" — mismo estilo
     que en la web pública (fondo oscuro, contenido centrado, "X" para
     cerrar), creado con JS al final de la página. */
  .visor-overlay {
    position:fixed; inset:0; z-index:2000; background:rgba(11,43,60,.92);
    display:flex; align-items:center; justify-content:center; padding:32px;
  }
  /* El atributo "hidden" tiene menos prioridad que cualquier regla CSS
     normal — sin esto, "display:flex" de arriba gana siempre y el visor
     se queda visible (vacío) aunque JS lo marque como oculto. */
  .visor-overlay[hidden] {
    display: none;
  }
  .visor-cerrar {
    position:fixed; top:16px; right:20px; z-index:2001; width:44px; height:44px; border-radius:50%;
    border:none; background:#fff; color:var(--azul-profundo); font-size:1.8rem;
    line-height:1; cursor:pointer; box-shadow:0 14px 28px rgba(16,45,58,.2);
  }
  .visor-cerrar:hover { background:var(--verde-agua); color:#fff; }
  .visor-contenido { width:100%; height:100%; display:flex; align-items:center; justify-content:center; }
  .visor-contenido img { max-width:100%; max-height:100%; object-fit:contain; border-radius:4px; }
  .visor-contenido iframe { width:min(100%,900px); height:100%; border:none; border-radius:4px; background:#fff; }
</style>
</head>
<body>
  <header>
    <h1>Panel de publicaciones — Capturam</h1>
    <a href="logout.php">Cerrar sesión</a>
  </header>

  <main>
    <?php if ($error !== ''): ?>
      <div class="aviso aviso-error"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($exito !== ''): ?>
      <div class="aviso aviso-exito"><?= e($exito) ?></div>
    <?php endif; ?>

    <div class="tarjeta">
      <h2><?= $editando !== null ? 'Editar publicación' : 'Nueva publicación' ?></h2>
      <form method="post" enctype="multipart/form-data" action="<?= $editando !== null ? 'panel.php?editar=' . rawurlencode($idEditando) : 'panel.php' ?>">
        <input type="hidden" name="accion" value="<?= $editando !== null ? 'editar' : 'crear' ?>">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <?php if ($editando !== null): ?>
          <input type="hidden" name="id" value="<?= e($idEditando) ?>">
        <?php endif; ?>

        <label for="titulo">Título</label>
        <input type="text" id="titulo" name="titulo" maxlength="<?= TITULO_MAX ?>" value="<?= e((string) ($editando['titulo'] ?? '')) ?>" required>

        <div class="fila-doble">
          <div>
            <label for="fecha">Fecha</label>
            <input type="date" id="fecha" name="fecha" value="<?= e($editando !== null ? (string) ($editando['fecha'] ?? '') : date('Y-m-d')) ?>">
          </div>
          <div>
            <label for="archivo">Archivo (PDF o imagen)</label>
            <input type="file" id="archivo" name="archivo" accept="application/pdf,image/jpeg,image/png,image/webp" <?= $editando !== null ? '' : 'required' ?>>
          </div>
        </div>
        <?php if ($editando !== null): ?>
          <p class="ayuda-formato">
            Archivo actual:
            <a href="../publicaciones/<?= rawurlencode((string) ($editando['archivo'] ?? '')) ?>" target="_blank" rel="noopener">
              <?= ($editando['tipo'] ?? '') === 'imagen' ? 'ver imagen' : 'ver PDF' ?>
            </a>. Sube uno nuevo solo si quieres reemplazarlo.
          </p>
        <?php endif; ?>

        <label for="resumen">Texto de la publicación (opcional)</label>
        <textarea id="resumen" name="resumen" maxlength="<?= RESUMEN_MAX ?>"><?= e((string) ($editando['resumen'] ?? '')) ?></textarea>
        <p class="ayuda-formato">
          Los saltos de línea se respetan tal cual. También puedes usar
          <code>**negrita**</code> y <code>[texto](https://enlace.com)</code> para enlaces.
        </p>

        <button type="submit" class="principal"><?= $editando !== null ? 'Guardar cambios' : 'Publicar' ?></button>
        <?php if ($editando !== null): ?>
          <a href="panel.php" class="enlace-cancelar">Cancelar</a>
        <?php endif; ?>
      </form>
    </div>

    <div class="tarjeta">
      <h2>Publicaciones (<?= count($publicaciones) ?>)</h2>
      <?php if (empty($publicaciones)): ?>
        <p class="vacio">Todavía no hay publicaciones.</p>
      <?php else: ?>
        <ul class="lista">
          <?php foreach ($publicaciones as $publicacion): ?>
            <li<?= ($publicacion['id'] ?? '') === $idEditando ? ' class="editando"' : '' ?>>
              <div class="pub-info">
                <span><?= e((string) ($publicacion['fecha'] ?? '')) ?></span>
                <h3><?= e((string) ($publicacion['titulo'] ?? '')) ?></h3>
                <?php if (!empty($publicacion['resumen'])): ?>
                  <p><?= e((string) $publicacion['resumen']) ?></p>
                <?php endif; ?>
              </div>
              <div class="pub-acciones">
                <button
                  type="button"
                  class="enlace-ver"
                  data-archivo="<?= e((string) ($publicacion['archivo'] ?? '')) ?>"
                  data-tipo="<?= e((string) ($publicacion['tipo'] ?? '')) ?>"
                ><?= ($publicacion['tipo'] ?? '') === 'imagen' ? 'Ver imagen' : 'Ver PDF' ?></button>
                <a href="?editar=<?= rawurlencode((string) ($publicacion['id'] ?? '')) ?>">Editar</a>
                <form method="post" action="panel.php" onsubmit="return confirm('¿Eliminar esta publicación? No se puede deshacer.');">
                  <input type="hidden" name="accion" value="eliminar">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="id" value="<?= e((string) ($publicacion['id'] ?? '')) ?>">
                  <button type="submit" class="borrar">Eliminar</button>
                </form>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </main>

  <script>
    (function () {
      var visor = null;
      var contenido = null;

      function asegurarVisor() {
        if (visor) { return visor; }

        visor = document.createElement('div');
        visor.className = 'visor-overlay';
        visor.hidden = true;

        var cerrar = document.createElement('button');
        cerrar.type = 'button';
        cerrar.className = 'visor-cerrar';
        cerrar.setAttribute('aria-label', 'Cerrar');
        cerrar.textContent = '×';

        contenido = document.createElement('div');
        contenido.className = 'visor-contenido';

        visor.appendChild(cerrar);
        visor.appendChild(contenido);
        document.body.appendChild(visor);

        function cerrarVisor() {
          visor.hidden = true;
          contenido.innerHTML = '';
        }

        cerrar.addEventListener('click', cerrarVisor);
        visor.addEventListener('click', function (evento) {
          if (evento.target === visor || evento.target === contenido) { cerrarVisor(); }
        });
        document.addEventListener('keydown', function (evento) {
          if (evento.key === 'Escape' && !visor.hidden) { cerrarVisor(); }
        });

        return visor;
      }

      document.querySelectorAll('.enlace-ver').forEach(function (boton) {
        boton.addEventListener('click', function () {
          var v = asegurarVisor();
          contenido.innerHTML = '';

          var url = '../publicaciones/' + encodeURIComponent(boton.getAttribute('data-archivo') || '');

          if (boton.getAttribute('data-tipo') === 'imagen') {
            var img = document.createElement('img');
            img.src = url;
            img.alt = '';
            contenido.appendChild(img);
          } else {
            var iframe = document.createElement('iframe');
            iframe.src = url;
            contenido.appendChild(iframe);
          }

          v.hidden = false;
        });
      });
    })();
  </script>
</body>
</html>
