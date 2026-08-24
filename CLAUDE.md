# Capturam Ingeniería — Web corporativa

## Qué es este proyecto
Web corporativa de **Capturam Ingeniería** (desarrollo de proyectos de energías renovables: solar fotovoltaica, eólica, BESS e hibridación, desde Greenfield hasta Ready to Build/PES). Construida en Python con **Flask + Jinja2 + Bootstrap 5**.

Lee `GUIA.md` antes de generar nada: contiene el plan de construcción paso a paso (estructura de carpetas, rutas, plantillas, formulario de contacto).

## Fuente de verdad del contenido
Toda la copy real de la web (textos, tablas, cifras, paleta de color) vive en la carpeta `content/`. Es contenido validado por Capturam para su dossier de web — **no lo reescribas, no inventes datos nuevos y no completes huecos por inferencia.**

- `content/00-resumen-ejecutivo.md` — posicionamiento y tono de voz
- `content/01-arquitectura.md` — mapa del sitio, navegación, CTAs
- `content/02-home.md` — textos literales de Inicio
- `content/03-quienes-somos.md`
- `content/04-servicios.md`
- `content/05-sectores.md`
- `content/06-como-trabajamos.md`
- `content/07-proyectos.md` — tablas de referencias (⚠️ ver aviso de autorización de clientes)
- `content/08-marca.md` — paleta de color exacta, tipografía, fotografía, formatos de assets
- `content/09-seo.md` — keywords objetivo y metadatos
- `content/10-pendientes.md` — datos que Capturam debe validar antes de publicar (NO inventar mientras tanto)

## Reglas al generar código o contenido
1. Usa el texto de `content/` **literalmente** para el copy de cada página. Si una sección necesita texto que no está en `content/`, deja un marcador `[PENDIENTE: ...]` en vez de inventarlo.
2. Respeta exactamente la paleta de `content/08-marca.md` en el CSS (son los colores reales de marca, ya en hexadecimal).
3. No publiques nombres de cliente ni cifras agregadas de MW/proyectos sin dejar constancia de que están pendientes de autorización (`content/10-pendientes.md`).
4. El logo real está en `static/img/logo-capturam.png`. No generar ni sustituir por otro.
5. Sigue la estructura de carpetas, nombres de archivo y patrón de plantillas Jinja2 descritos en `GUIA.md`.
6. Si vas a crear una página nueva que no está en `content/01-arquitectura.md`, pregunta primero en vez de decidirlo por tu cuenta.

## Cómo trabajar en este repo, paso a paso
Un flujo recomendado al abrir este proyecto en el editor con Claude activado:
1. "Lee CLAUDE.md, GUIA.md y todo `content/`, y resume el plan antes de tocar código."
2. "Crea la estructura base de Flask siguiendo GUIA.md (venv, app.py, templates/base.html)."
3. "Genera la plantilla de Inicio usando content/02-home.md."
4. Repetir página a página: Servicios (`content/04-servicios.md`), Sectores (`content/05-sectores.md`), etc.
5. "Configura el formulario de contacto con Flask-Mail tal como describe GUIA.md."
6. "Aplica la paleta de content/08-marca.md a estilos.css."

Pide siempre una página o un bloque cada vez — es más fácil de revisar que pedir "toda la web" de golpe.
