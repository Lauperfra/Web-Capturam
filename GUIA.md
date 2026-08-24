# Guía de construcción — Web de Capturam (Flask)

Este documento es la versión "de trabajo" (para que Claude Code la lea dentro del proyecto) de la guía completa, que también existe como página navegable aquí:
https://claude.ai/code/artifact/f3f1e634-e2b4-4cd0-aca4-3eca9816b98e

## Stack
Flask + Jinja2 (plantillas) + Bootstrap 5 (CDN) + Flask-Mail (formulario de contacto) + python-dotenv (credenciales).

## Estado de este kit
Ya incluye una base **ejecutable**: `app.py` con las 9 rutas del sitio, `templates/base.html` con el menú real, una plantilla por página con marcadores de contenido, `static/css/estilos.css` con la paleta real de marca, y el logo real en `static/img/logo-capturam.png`.

Lo que falta — y para lo que sirve pedirle ayuda a Claude Code — es **rellenar cada plantilla con el copy de `content/`** y pulir el diseño.

## Puesta en marcha
```
cd capturam-web-kit
python -m venv venv
venv\Scripts\Activate.ps1      # Windows PowerShell — en macOS/Linux: source venv/bin/activate
pip install -r requirements.txt
copy .env.example .env         # macOS/Linux: cp .env.example .env
python app.py
```
Abre `http://127.0.0.1:5000`. Verás las 9 páginas ya navegables, con títulos pero sin el copy final — ese es el trabajo que le vas a pedir a Claude Code, página a página, usando los archivos de `content/`.

## Estructura
```
capturam-web-kit/
├── CLAUDE.md              ← contexto para Claude Code (léelo primero)
├── GUIA.md                ← este archivo
├── content/                ← copy real de Capturam, por sección
├── requirements.txt
├── .env.example
├── .gitignore
├── app.py                  ← 9 rutas + formulario de contacto con Flask-Mail
├── static/
│   ├── css/estilos.css     ← paleta real de marca
│   └── img/logo-capturam.png
└── templates/
    ├── base.html            ← menú y pie reales
    ├── index.html
    ├── empresa.html
    ├── servicios.html
    ├── sectores.html
    ├── proyectos.html
    ├── como-trabajamos.html
    ├── conocimiento.html
    ├── contacto.html
    └── legal.html
```

## Formulario de contacto: credenciales de Gmail
1. Activa la verificación en dos pasos en la cuenta de Gmail que enviará los correos.
2. `myaccount.google.com → Seguridad → Contraseñas de aplicaciones` → genera una para "Capturam Web".
3. Copia el código de 16 caracteres en `.env` como `MAIL_PASSWORD`.
4. `MAIL_DESTINO` es el correo donde quieres recibir los mensajes del formulario (puede ser el mismo que `MAIL_USERNAME` o uno distinto, p. ej. info@capturam.com).

`.env` nunca se sube a git — ya está en `.gitignore`.

## Cómo pedirle a Claude Code que rellene cada página
Ejemplos de prompts, uno por página, para ir dando pasos pequeños y revisables:

1. "Lee CLAUDE.md y content/02-home.md, y rellena templates/index.html con ese contenido, manteniendo la estructura Bootstrap de la plantilla."
2. "Ahora haz lo mismo con templates/empresa.html usando content/03-quienes-somos.md."
3. "Rellena templates/servicios.html con las 6 secciones de content/04-servicios.md, una tarjeta por servicio."
4. "Rellena templates/sectores.html con content/05-sectores.md, tarjetas para los 5 sectores."
5. "Rellena templates/proyectos.html con las tablas de content/07-proyectos.md — recuerda el aviso de autorización de clientes que hay al principio del archivo."
6. "Rellena templates/como-trabajamos.html con las 6 fases de content/06-como-trabajamos.md, como una línea de tiempo simple."
7. "Deja templates/conocimiento.html como una página sencilla de 'próximamente', ya que los artículos son fase 2 (ver content/09-seo.md)."
8. "Revisa que static/css/estilos.css use exactamente los colores de content/08-marca.md."

## Despliegue
Cuando esté lista: PythonAnywhere (más sencillo, sin tarjeta ni GitHub), Render o Railway (requieren subir el repo a GitHub primero). Pide una guía de despliegue cuando llegues a ese punto.
