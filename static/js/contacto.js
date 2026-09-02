document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('form-contacto');
  var mensaje = document.getElementById('form-mensaje');
  if (!form) return;

  form.addEventListener('submit', function (evento) {
    evento.preventDefault();

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
        if (respuesta.ok) {
          form.reset();
          mensaje.textContent = 'Gracias por tu mensaje. Te responderemos lo antes posible.';
          mensaje.className = 'form-mensaje form-mensaje--exito';
        } else {
          mensaje.textContent = 'No se ha podido enviar el mensaje. Inténtalo de nuevo o escríbenos directamente a ingenieria@capturam.es.';
          mensaje.className = 'form-mensaje form-mensaje--error';
        }
        mensaje.hidden = false;
      })
      .catch(function () {
        mensaje.textContent = 'No se ha podido enviar el mensaje. Inténtalo de nuevo o escríbenos directamente a ingenieria@capturam.es.';
        mensaje.className = 'form-mensaje form-mensaje--error';
        mensaje.hidden = false;
      })
      .finally(function () {
        boton.disabled = false;
        boton.textContent = textoOriginal;
      });
  });
});
