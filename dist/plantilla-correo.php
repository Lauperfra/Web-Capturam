<?php
/**
 * Plantilla HTML del correo de aviso del formulario de contacto.
 *
 * Tabla-based a propósito (nada de flexbox/grid): es lo único que renderiza
 * de forma fiable en clientes de correo como Outlook de escritorio.
 */

declare(strict_types=1);

/**
 * @param array{nombre:string, email:string, mensaje:string, fecha:string, hora:string} $datos
 * @param string $urlBase p.ej. "https://capturam.es" (sin barra final)
 */
function construirCorreoHtml(array $datos, string $urlBase): string
{
    $nombre = htmlspecialchars($datos['nombre'], ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars($datos['email'], ENT_QUOTES, 'UTF-8');
    $mensaje = nl2br(htmlspecialchars($datos['mensaje'], ENT_QUOTES, 'UTF-8'));
    $fecha = htmlspecialchars($datos['fecha'], ENT_QUOTES, 'UTF-8');
    $hora = htmlspecialchars($datos['hora'], ENT_QUOTES, 'UTF-8');
    $urlBase = rtrim($urlBase, '/');

    $azulProfundo = '#102d3a';
    $verdeAgua = '#91b5b4';
    $blancoCalido = '#f3f6f4';

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nuevo mensaje de contacto</title>
</head>
<body style="margin:0; padding:0; background-color:{$blancoCalido};">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:{$blancoCalido}; padding:32px 16px;">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; max-width:600px; width:100%; border-radius:10px; overflow:hidden; border:1px solid #e2e5ea;">

          <tr>
            <td style="background-color:{$azulProfundo}; padding:14px 28px;">
              <a href="{$urlBase}" style="color:{$verdeAgua}; text-decoration:underline; font-family:Arial,Helvetica,sans-serif; font-size:13px;">Visita nuestra web</a>
            </td>
          </tr>

          <tr>
            <td align="center" style="padding:32px 28px 8px;">
              <img src="{$urlBase}/static/img/logo-capturam.png" alt="Capturam Ingeniería" width="200" style="display:block; max-width:200px; height:auto; border:0;">
            </td>
          </tr>

          <tr>
            <td style="padding:24px 28px 8px; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:{$azulProfundo}; line-height:1.6;">
              Un usuario ha rellenado el formulario de contacto de la web el <strong>{$fecha}</strong> a las <strong>{$hora} h.</strong> con los siguientes datos:
            </td>
          </tr>

          <tr>
            <td style="padding:16px 28px 32px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-family:Arial,Helvetica,sans-serif; font-size:14px; color:{$azulProfundo};">
                <tr>
                  <td style="padding:12px 0; border-bottom:1px solid #e2e5ea; width:110px; font-weight:bold; vertical-align:top;">Nombre:</td>
                  <td style="padding:12px 0; border-bottom:1px solid #e2e5ea; vertical-align:top;">{$nombre}</td>
                </tr>
                <tr>
                  <td style="padding:12px 0; border-bottom:1px solid #e2e5ea; font-weight:bold; vertical-align:top;">Email:</td>
                  <td style="padding:12px 0; border-bottom:1px solid #e2e5ea; vertical-align:top;">
                    <a href="mailto:{$email}" style="color:{$azulProfundo};">{$email}</a>
                  </td>
                </tr>
                <tr>
                  <td style="padding:12px 0; font-weight:bold; vertical-align:top;">Mensaje:</td>
                  <td style="padding:12px 0; vertical-align:top;">{$mensaje}</td>
                </tr>
              </table>
            </td>
          </tr>

          <tr>
            <td style="background-color:{$azulProfundo}; padding:20px 28px; font-family:Arial,Helvetica,sans-serif; font-size:11px; color:#c7d1cf; line-height:1.6;">
              Este mensaje se ha generado automáticamente desde el formulario de contacto de {$urlBase} y puede contener información confidencial. Si no eres el destinatario previsto, te rogamos que lo elimines y nos lo comuniques.
              <br><br>
              De acuerdo con el Reglamento (UE) 2016/679 (RGPD), el responsable del tratamiento de estos datos es <strong>Capturam Ingeniería S.L.</strong> Más información en nuestra
              <a href="{$urlBase}/privacidad.html" style="color:{$verdeAgua};">Política de Privacidad</a>.
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}

/**
 * Versión en texto plano (para clientes de correo sin HTML).
 * @param array{nombre:string, email:string, mensaje:string, fecha:string, hora:string} $datos
 */
function construirCorreoTexto(array $datos): string
{
    return "Nuevo mensaje desde el formulario de contacto de la web"
        . " ({$datos['fecha']} a las {$datos['hora']} h.)\n\n"
        . "Nombre: {$datos['nombre']}\n"
        . "Email: {$datos['email']}\n\n"
        . "Mensaje:\n{$datos['mensaje']}\n";
}
