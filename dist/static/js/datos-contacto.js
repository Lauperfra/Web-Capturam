// Rellena el email/teléfono/LinkedIn en cualquier elemento marcado con
// data-campo-contacto-href / data-campo-contacto-texto, con los datos que
// el CEO haya guardado desde "Contenido web" en el panel. Si el archivo no
// existe todavía (nadie lo ha editado) o falla la petición, la página se
// queda con los valores ya escritos en el propio HTML — no rompe nada.
(function () {
  function aplicar(datos) {
    if (!datos) return;

    document.querySelectorAll('[data-campo-contacto-href]').forEach(function (el) {
      var campo = el.getAttribute('data-campo-contacto-href');
      if (campo === 'email' && datos.email) {
        el.href = 'mailto:' + datos.email;
      } else if (campo === 'telefono' && datos.telefono_href) {
        el.href = 'tel:' + datos.telefono_href;
      } else if (campo === 'linkedin' && datos.linkedin) {
        el.href = datos.linkedin;
      }
    });

    document.querySelectorAll('[data-campo-contacto-texto]').forEach(function (el) {
      var campo = el.getAttribute('data-campo-contacto-texto');
      if (campo === 'email' && datos.email) {
        el.textContent = datos.email;
      } else if (campo === 'telefono' && datos.telefono_texto) {
        el.textContent = 'Teléfono: ' + datos.telefono_texto;
      }
    });
  }

  fetch('/datos-contacto.json', { cache: 'no-cache' })
    .then(function (respuesta) { return respuesta.ok ? respuesta.json() : null; })
    .then(aplicar)
    .catch(function () {});
})();
