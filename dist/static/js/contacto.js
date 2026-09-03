document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('form-contacto');
  var mensaje = document.getElementById('form-mensaje');
  if (!form) return;

  var enviando = false;
  var MENSAJE_ERROR_GENERICO = 'No se ha podido enviar el mensaje. Inténtalo de nuevo o escríbenos directamente a ingenieria@capturam.es.';

  form.addEventListener('submit', function (evento) {
    evento.preventDefault();
    if (enviando) return;
    enviando = true;

    var boton = form.querySelector('button[type="submit"]');
    var textoOriginal = boton.textContent;
    boton.disabled = true;
    boton.textContent = 'Enviando…';
    mensaje.hidden = true;

    fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      headers: { Accept: 'application/json' }
    })
      .then(function (respuesta) {
        return respuesta.json()
          .catch(function () { return {}; })
          .then(function (datos) { return { estado: respuesta.status, datos: datos }; });
      })
      .then(function (resultado) {
        var datos = resultado.datos || {};
        if (resultado.estado >= 200 && resultado.estado < 300 && datos.ok) {
          form.reset();
          mensaje.textContent = datos.mensaje || 'Gracias por tu mensaje. Te responderemos lo antes posible.';
          mensaje.className = 'form-mensaje form-mensaje--exito';
        } else {
          mensaje.textContent = datos.error || MENSAJE_ERROR_GENERICO;
          mensaje.className = 'form-mensaje form-mensaje--error';
        }
        mensaje.hidden = false;
      })
      .catch(function () {
        mensaje.textContent = MENSAJE_ERROR_GENERICO;
        mensaje.className = 'form-mensaje form-mensaje--error';
        mensaje.hidden = false;
      })
      .finally(function () {
        boton.disabled = false;
        boton.textContent = textoOriginal;
        enviando = false;
      });
  });
});
