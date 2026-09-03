"""
Genera una versión 100% estática (HTML/CSS/imágenes) de la web a partir de las
mismas plantillas Jinja2 que usa app.py, sin necesidad de un servidor Python
en producción. Uso: python build.py — el resultado queda en dist/.
"""
import shutil
import time
from pathlib import Path

from jinja2 import Environment, FileSystemLoader

RAIZ = Path(__file__).parent
PLANTILLAS = RAIZ / "templates"
ESTATICOS = RAIZ / "static"
SALIDA = RAIZ / "dist"
SERVIDOR = RAIZ / "server"

# Archivos de server/ que se copian tal cual a la raíz de dist/ (el backend
# PHP del formulario de contacto). config.example.php y composer.* se
# quedan fuera a propósito: no hacen falta en producción.
ARCHIVOS_SERVIDOR = ["enviar-contacto.php", "plantilla-correo.php", ".htaccess"]

# Mapea cada endpoint de Flask (los mismos nombres usados en url_for) al
# archivo .html final y a la plantilla que lo genera.
PAGINAS = {
    "inicio": ("index.html", "index.html"),
    "empresa": ("empresa.html", "empresa.html"),
    "servicios": ("servicios.html", "servicios.html"),
    "sectores": ("sectores.html", "sectores.html"),
    "proyectos": ("proyectos.html", "proyectos.html"),
    "como_trabajamos": ("como-trabajamos.html", "como-trabajamos.html"),
    "conocimiento": ("conocimiento.html", "conocimiento.html"),
    "legal": ("legal.html", "legal.html"),
    "privacidad": ("privacidad.html", "privacidad.html"),
    "contacto": ("contacto.html", "contacto.html"),
}


def url_for(endpoint, filename=None, **kwargs):
    if endpoint == "static":
        return f"static/{filename}"
    if endpoint not in PAGINAS:
        raise ValueError(f"Endpoint desconocido en url_for: {endpoint}")
    return PAGINAS[endpoint][0]


def get_flashed_messages(with_categories=False, **kwargs):
    # No hay backend que genere mensajes flash en la versión estática.
    return []


def copiar_con_reintentos(origen, destino, intentos=5, espera=1.0):
    """OneDrive puede bloquear un archivo un instante mientras sincroniza."""
    for intento in range(intentos):
        try:
            shutil.copytree(origen, destino, dirs_exist_ok=True)
            return
        except PermissionError:
            if intento == intentos - 1:
                raise
            time.sleep(espera)


def construir():
    # No borramos dist/ entero: OneDrive puede tener un archivo bloqueado un
    # instante mientras sincroniza. Sobrescribimos página a página en su lugar.
    SALIDA.mkdir(parents=True, exist_ok=True)

    env = Environment(loader=FileSystemLoader(str(PLANTILLAS)))
    env.globals["url_for"] = url_for
    env.globals["get_flashed_messages"] = get_flashed_messages

    for endpoint, (archivo_salida, plantilla) in PAGINAS.items():
        html = env.get_template(plantilla).render()
        (SALIDA / archivo_salida).write_text(html, encoding="utf-8")
        print(f"  {plantilla} -> dist/{archivo_salida}")

    copiar_con_reintentos(ESTATICOS, SALIDA / "static")

    for nombre in ARCHIVOS_SERVIDOR:
        origen = SERVIDOR / nombre
        if origen.is_file():
            shutil.copy2(origen, SALIDA / nombre)
            print(f"  server/{nombre} -> dist/{nombre}")
        else:
            print(f"  [aviso] no encontrado: server/{nombre}")

    vendor_origen = SERVIDOR / "vendor"
    if vendor_origen.is_dir():
        copiar_con_reintentos(vendor_origen, SALIDA / "vendor")
        print("  server/vendor/ -> dist/vendor/")
    else:
        print("  [aviso] server/vendor/ no existe todavía — ejecuta 'composer install' dentro de server/ antes de desplegar (ver server/composer.json).")

    print(f"\nListo. {len(PAGINAS)} páginas + static/ copiados a {SALIDA}")


if __name__ == "__main__":
    construir()
