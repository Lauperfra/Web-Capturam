<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

if (sesionAdminIniciada()) {
    header('Location: panel.php');
    exit;
}

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if (limiteDeIntentosSuperado($ip)) {
        $error = 'Demasiados intentos. Espera unos minutos e inténtalo de nuevo.';
    } else {
        $config = cargarConfiguracionAdmin();
        $hashValido = (string) ($config['admin_password_hash'] ?? '');
        $passwordEnviada = (string) ($_POST['password'] ?? '');

        if ($hashValido !== '' && password_verify($passwordEnviada, $hashValido)) {
            session_regenerate_id(true);
            $_SESSION['admin_autenticado'] = true;
            $_SESSION['ultima_actividad'] = time();
            registrarIntentoOk($ip);
            header('Location: panel.php');
            exit;
        }

        $error = $hashValido === ''
            ? 'El panel no está configurado todavía (falta capturam-admin-config.php).'
            : 'Contraseña incorrecta.';
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
<link rel="icon" href="../static/img/favicon-admin.ico" sizes="any">
<link rel="icon" type="image/png" sizes="16x16" href="../static/img/favicon-admin-16.png">
<link rel="icon" type="image/png" sizes="32x32" href="../static/img/favicon-admin-32.png">
<link rel="icon" type="image/png" sizes="48x48" href="../static/img/favicon-admin-48.png">
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
    background: var(--azul-profundo);
    font-family: 'Inter', sans-serif;
    padding: 24px;
  }
  .caja {
    background: #fff;
    border-radius: 12px;
    padding: 40px 36px;
    width: 100%;
    max-width: 360px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, .25);
  }
  h1 {
    font-size: 1.3rem;
    color: var(--azul-profundo);
    margin: 0 0 24px;
    text-align: center;
  }
  label {
    display: block;
    font-size: .9rem;
    color: var(--azul-pizarra);
    margin-bottom: 6px;
  }
  input[type=password] {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #d5dbe0;
    border-radius: 6px;
    font-size: 1rem;
    margin-bottom: 18px;
    font-family: inherit;
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
    <h1>Panel de publicaciones</h1>
    <?php if ($error !== ''): ?>
      <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <form method="post" autocomplete="off">
      <label for="password">Contraseña</label>
      <input type="password" id="password" name="password" required autofocus>
      <button type="submit">Entrar</button>
    </form>
  </div>
</body>
</html>
