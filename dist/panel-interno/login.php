<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

if (sesionAdminIniciada()) {
    header('Location: panel');
    exit;
}

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if (limiteDeIntentosSuperado($ip)) {
        $error = 'Demasiados intentos. Espera unos minutos e inténtalo de nuevo.';
    } else {
        $config = cargarConfiguracionAdmin();
        $usuarioValido = (string) ($config['admin_usuario'] ?? '');
        $hashValido = (string) ($config['admin_password_hash'] ?? '');
        $usuarioEnviado = (string) ($_POST['usuario'] ?? '');
        $passwordEnviada = (string) ($_POST['password'] ?? '');

        $configuracionCompleta = $usuarioValido !== '' && $hashValido !== '';
        $usuarioOk = $configuracionCompleta && hash_equals($usuarioValido, $usuarioEnviado);
        $passwordOk = $configuracionCompleta && password_verify($passwordEnviada, $hashValido);

        if ($configuracionCompleta && $usuarioOk && $passwordOk) {
            session_regenerate_id(true);
            $_SESSION['admin_autenticado'] = true;
            $_SESSION['admin_usuario'] = $usuarioValido;
            $_SESSION['ultima_actividad'] = time();
            registrarIntentoOk($ip);
            header('Location: panel');
            exit;
        }

        // El mismo mensaje tanto si falla el usuario como la contraseña
        // (no dar pistas de cuál de los dos está mal).
        $error = !$configuracionCompleta
            ? 'El panel no está configurado todavía (falta capturam-admin-config.php).'
            : 'Usuario o contraseña incorrectos.';
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Acceso · Panel Capturam</title>
<link rel="icon" href="../static/img/favicon-panel-interno.ico" sizes="any">
<link rel="icon" type="image/png" sizes="16x16" href="../static/img/favicon-panel-interno-16.png">
<link rel="icon" type="image/png" sizes="32x32" href="../static/img/favicon-panel-interno-32.png">
<link rel="icon" type="image/png" sizes="48x48" href="../static/img/favicon-panel-interno-48.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root { --azul-profundo:#0b2b3c; --verde-agua:#3fb8a4; --azul-pizarra:#4b6472; }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background:
      radial-gradient(circle at 18% 14%, rgba(63, 184, 164, .25), transparent 42%),
      radial-gradient(circle at 85% 90%, rgba(75, 100, 114, .35), transparent 45%),
      var(--azul-profundo);
    font-family: 'Inter', sans-serif;
    padding: 24px;
  }
  .caja {
    background: #fff;
    border-radius: 14px;
    padding: 40px 36px;
    width: 100%;
    max-width: 380px;
    box-shadow: 0 24px 60px rgba(0, 0, 0, .3);
  }
  .logo {
    display: block;
    width: 100%;
    max-width: 190px;
    height: auto;
    margin: 0 auto 20px;
  }
  h1 {
    font-size: 1rem;
    font-weight: 600;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: var(--azul-pizarra);
    margin: 0 0 28px;
    text-align: center;
  }
  label {
    display: block;
    font-size: .9rem;
    color: var(--azul-pizarra);
    margin-bottom: 6px;
    font-weight: 600;
  }
  input[type=text],
  input[type=password] {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #d5dbe0;
    border-radius: 6px;
    font-size: 1rem;
    margin-bottom: 18px;
    font-family: inherit;
  }
  input[type=text]:focus,
  input[type=password]:focus {
    outline: none;
    border-color: var(--verde-agua);
    box-shadow: 0 0 0 3px rgba(63, 184, 164, .18);
  }
  button {
    width: 100%;
    padding: 11px;
    border: none;
    border-radius: 6px;
    background: var(--azul-profundo);
    color: #fff;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    font-family: inherit;
  }
  button:hover { background: var(--azul-pizarra); }
  .error {
    background: #fdecea;
    color: #a33;
    border-radius: 6px;
    padding: 10px 12px;
    font-size: .85rem;
    margin-bottom: 18px;
  }
</style>
</head>
<body>
  <div class="caja">
    <img class="logo" src="../static/img/Logo-capturam-no-fondo.png" alt="Capturam Ingeniería">
    <h1>Panel de gestión interna</h1>
    <?php if ($error !== ''): ?>
      <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <form method="post" autocomplete="off">
      <label for="usuario">Usuario</label>
      <input type="text" id="usuario" name="usuario" required autofocus autocapitalize="off" autocorrect="off" spellcheck="false" autocomplete="username">
      <label for="password">Contraseña</label>
      <input type="password" id="password" name="password" required autocomplete="current-password">
      <button type="submit">Entrar</button>
    </form>
  </div>
</body>
</html>
