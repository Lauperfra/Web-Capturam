# Capturam Ingeniería — Web corporativa

Web corporativa de **Capturam Ingeniería**, desarrollo de proyectos de energías renovables (solar fotovoltaica, eólica, BESS e hibridación, desde Greenfield hasta Ready to Build/PES).

🌐 **[capturam.es](https://capturam.es)**

## Stack

**En desarrollo** (en este repositorio, para generar el sitio):
- **Python + Flask + Jinja2** — plantillas, rutas y `build.py`.

**En producción** (lo que corre de verdad en `capturam.es`):
- **Nada de Python ni Flask.** `build.py` genera un sitio **100% estático** (`dist/`) a partir de las plantillas, y eso es lo único que se sube al hosting — solo HTML servido por Apache.
- **PHP** únicamente para lo que de verdad necesita servidor: el formulario de contacto (`enviar-contacto.php`, vía PHPMailer/SMTP), el panel de administración interno (`panel-interno/`) y el listado de publicaciones.
- **Bootstrap 5** + CSS propio, sin frameworks de JS — JavaScript vanilla donde hace falta (visor de publicaciones, miniaturas de PDF con PDF.js, etc.).

## Desarrollo local

```bash
python -m venv venv
venv\Scripts\Activate.ps1      # Windows PowerShell — en macOS/Linux: source venv/bin/activate
pip install -r requirements.txt
python app.py
```

Para generar el sitio estático final:

```bash
python build.py
```

Esto deja el sitio completo en `dist/`, listo para servir con Apache (requiere `mod_rewrite` para las URLs limpias).

## Estructura

```
templates/          Plantillas Jinja2
content/             Copy real de la web (fuente de verdad del contenido)
static/              CSS, JS, imágenes
server/              Backend PHP (contacto, publicaciones, panel-interno)
build.py             Genera dist/ a partir de templates/ + content/
dist/                Sitio estático generado (no se edita a mano)
```

Ver `CLAUDE.md` y `GUIA.md` para más detalle sobre la arquitectura y las convenciones del proyecto.

## Configuración fuera del repositorio

El panel de administración y el envío de correo necesitan dos archivos de credenciales que **nunca viven en este repositorio**, fuera del directorio público del hosting: `capturam-admin-config.php` y `capturam-mail-config.php` (ver las plantillas de ejemplo en `server/`).
