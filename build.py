"""
Genera una versión 100% estática (HTML/CSS/imágenes) de la web a partir de las
mismas plantillas Jinja2 que usa app.py, sin necesidad de un servidor Python
en producción. Uso: python build.py — el resultado queda en dist/.
"""
import shutil
import subprocess
import time
from pathlib import Path

from jinja2 import Environment, FileSystemLoader

RAIZ = Path(__file__).parent
PLANTILLAS = RAIZ / "templates"
ESTATICOS = RAIZ / "static"
SALIDA = RAIZ / "dist"
SERVIDOR = RAIZ / "server"

# "Contenido web" (textos/fotos editables desde el panel): plantillas con
# marcadores [[...]] y valores de fábrica, ambos FUERA de dist/ — el mismo
# nivel que ocupará contenido-datos/ (el JSON vivo) en producción, un nivel
# por encima de public_html. Ver server/panel-interno/_contenido.php.
PLANTILLAS_CONTENIDO = RAIZ / "plantillas-contenido"
CONTENT_EDITABLE = RAIZ / "content" / "editable"

# Páginas con contenido editable desde el panel: su HTML NO se escribe aquí
# directamente (llevarían marcadores sin resolver) — se guarda como
# plantilla, y el HTML público final lo genera el script PHP de
# regeneración al final de este archivo, combinando la plantilla con el
# contenido vivo (o el de fábrica si todavía no hay ninguno).
ENDPOINT_A_PAGINA_EDITABLE = {
    "inicio": "inicio",
    "empresa": "empresa",
    "sectores": "sectores",
    "servicios": "servicios",
    "proyectos": "proyectos",
    "como_trabajamos": "como-trabajamos",
}

# Archivos de server/ que se copian tal cual a la raíz de dist/ (backend PHP
# del formulario de contacto y de las publicaciones). config.example.php y
# composer.* se quedan fuera a propósito: no hacen falta en producción.
ARCHIVOS_SERVIDOR = [
    "enviar-contacto.php",
    "plantilla-correo.php",
    "publicaciones.php",
    "robots.txt",
    "sitemap.xml",
    ".htaccess",
    ".user.ini",
]

# Archivos de server/panel-interno/ que NO se copian a dist/panel-interno/
# (plantilla de configuración de ejemplo: no hace falta en producción, la
# real vive fuera de dist/).
ARCHIVOS_PANEL_EXCLUIDOS = {"config.example.php"}

# Mapea cada endpoint de Flask (los mismos nombres usados en url_for) al
# archivo .html final y a la plantilla que lo genera.
PAGINAS = {
    "inicio": ("index.html", "index.html"),
    "empresa": ("empresa.html", "empresa.html"),
    "servicios": ("servicios.html", "servicios.html"),
    "sectores": ("sectores.html", "sectores.html"),
    "proyectos": ("proyectos.html", "proyectos.html"),
    "como_trabajamos": ("como-trabajamos.html", "como-trabajamos.html"),
    "publicaciones": ("publicaciones.html", "publicaciones.html"),
    "legal": ("legal.html", "legal.html"),
    "privacidad": ("privacidad.html", "privacidad.html"),
    "contacto": ("contacto.html", "contacto.html"),
}


def url_for(endpoint, filename=None, **kwargs):
    if endpoint == "static":
        # Ruta absoluta (con "/" inicial): necesario para que la página de
        # error 404 cargue bien su CSS/imágenes sin importar la URL que
        # pidiera el visitante (Apache la sirve por dentro sin cambiar la
        # barra de direcciones, así que una ruta relativa se resolvería mal
        # en URLs con más de un nivel, p. ej. /carpeta/algo-inexistente).
        return f"/static/{filename}"
    if endpoint not in PAGINAS:
        raise ValueError(f"Endpoint desconocido en url_for: {endpoint}")
    if endpoint == "inicio":
        return "/"
    # URL "limpia" (sin ".html") para los enlaces internos — el .htaccess
    # sirve por dentro el archivo .html real sin que se note en la barra de
    # direcciones. El archivo en sí sigue generándose con su extensión.
    archivo_salida = PAGINAS[endpoint][0]
    return "/" + archivo_salida.rsplit(".", 1)[0]


def get_flashed_messages(with_categories=False, **kwargs):
    # No hay backend que genere mensajes flash en la versión estática.
    return []


def copiar_con_reintentos(origen, destino, intentos=5, espera=1.0, ignorar=None):
    """OneDrive puede bloquear un archivo un instante mientras sincroniza."""
    for intento in range(intentos):
        try:
            shutil.copytree(origen, destino, dirs_exist_ok=True, ignore=ignorar)
            return
        except PermissionError:
            if intento == intentos - 1:
                raise
            time.sleep(espera)


def regenerar_contenido_editable():
    """Invoca el script PHP que combina plantillas-contenido/ + el JSON vivo
    (o el de fábrica si no hay contenido vivo todavía) para producir
    dist/<pagina>.html de las 5 páginas editables. Mismo script que se usa
    en producción tras desplegar una plantilla nueva — ver
    server/panel-interno/_cli_regenerar_contenido.php."""
    script = SALIDA / "panel-interno" / "_cli_regenerar_contenido.php"
    if not script.is_file():
        print("  [aviso] no se encontró _cli_regenerar_contenido.php — las páginas editables no se han regenerado.")
        return

    php = shutil.which("php")
    if php is None:
        print("  [aviso] no se encontró 'php' en el PATH — instálalo o ejecútalo tú misma con:")
        print(f'          php "{script}"')
        return

    resultado = subprocess.run([php, str(script)], capture_output=True, text=True)
    for linea in resultado.stdout.splitlines():
        print("  " + linea)
    if resultado.returncode != 0:
        print("  [aviso] alguna página editable no se pudo regenerar (ver arriba).")
        if resultado.stderr.strip():
            print("  " + resultado.stderr.strip())


def construir():
    # No borramos dist/ entero: OneDrive puede tener un archivo bloqueado un
    # instante mientras sincroniza. Sobrescribimos página a página en su lugar.
    SALIDA.mkdir(parents=True, exist_ok=True)
    PLANTILLAS_CONTENIDO.mkdir(parents=True, exist_ok=True)
    (PLANTILLAS_CONTENIDO / "valores-fabrica").mkdir(parents=True, exist_ok=True)

    env = Environment(loader=FileSystemLoader(str(PLANTILLAS)))
    env.globals["url_for"] = url_for
    env.globals["get_flashed_messages"] = get_flashed_messages

    for endpoint, (archivo_salida, plantilla) in PAGINAS.items():
        html = env.get_template(plantilla).render()
        pagina_editable = ENDPOINT_A_PAGINA_EDITABLE.get(endpoint)
        if pagina_editable is not None:
            # Todavía lleva marcadores [[...]] sin resolver — se guarda como
            # plantilla, NUNCA como el HTML público (lo genera el script PHP
            # de más abajo, combinándola con el contenido vivo real). Se
            # nombra por la clave de la página (p. ej. "inicio.html"), no
            # por el nombre de archivo público final (p. ej. "index.html"),
            # para que coincida con lo que busca el esquema PHP.
            nombre_plantilla = f"{pagina_editable}.html"
            (PLANTILLAS_CONTENIDO / nombre_plantilla).write_text(html, encoding="utf-8")
            print(f"  {plantilla} -> plantillas-contenido/{nombre_plantilla}")
        else:
            (SALIDA / archivo_salida).write_text(html, encoding="utf-8")
            print(f"  {plantilla} -> dist/{archivo_salida}")

    # Página de error 404, servida por .htaccess (ErrorDocument) cuando la
    # URL pedida no existe. No es una página del menú, así que no está en
    # PAGINAS.
    html_404 = env.get_template("404.html").render()
    (SALIDA / "404.html").write_text(html_404, encoding="utf-8")
    print("  404.html -> dist/404.html")

    copiar_con_reintentos(ESTATICOS, SALIDA / "static")

    for nombre in ARCHIVOS_SERVIDOR:
        origen = SERVIDOR / nombre
        if origen.is_file():
            shutil.copy2(origen, SALIDA / nombre)
            print(f"  server/{nombre} -> dist/{nombre}")
        else:
            print(f"  [aviso] no encontrado: server/{nombre}")

    panel_origen = SERVIDOR / "panel-interno"
    if panel_origen.is_dir():
        copiar_con_reintentos(
            panel_origen,
            SALIDA / "panel-interno",
            ignorar=shutil.ignore_patterns(*ARCHIVOS_PANEL_EXCLUIDOS),
        )
        print("  server/panel-interno/ -> dist/panel-interno/")
    else:
        print("  [aviso] server/panel-interno/ no existe todavía.")

    vendor_origen = SERVIDOR / "vendor"
    if vendor_origen.is_dir():
        copiar_con_reintentos(vendor_origen, SALIDA / "vendor")
        print("  server/vendor/ -> dist/vendor/")
    else:
        print("  [aviso] server/vendor/ no existe todavía — ejecuta 'composer install' dentro de server/ antes de desplegar (ver server/composer.json).")

    for pagina in ENDPOINT_A_PAGINA_EDITABLE.values():
        origen = CONTENT_EDITABLE / f"{pagina}.json"
        if origen.is_file():
            shutil.copy2(origen, PLANTILLAS_CONTENIDO / "valores-fabrica" / f"{pagina}.json")
        else:
            print(f"  [aviso] falta content/editable/{pagina}.json (valores de fábrica).")

    regenerar_contenido_editable()

    print(f"\nListo. {len(PAGINAS)} páginas + static/ copiados a {SALIDA}")


if __name__ == "__main__":
    construir()
