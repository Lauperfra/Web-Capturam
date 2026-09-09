/**
 * Renderiza tarjetas de publicaciones a partir de publicaciones.php.
 * Usado por Inicio (últimas 3) y por Publicaciones (todas), con el mismo
 * truncado "ver más" de un texto largo y un pequeño formato tipo LinkedIn:
 * saltos de línea, **negrita** y [texto](url) como enlaces.
 */
(function (global) {
  var MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
  var LIMITE_RESUMEN = 220;
  var REGEX_FORMATO = /\*\*(.+?)\*\*|\[([^\]]+)\]\(([^)]+)\)/g;

  function formatearFecha(iso) {
    var partes = (iso || '').split('-');
    if (partes.length !== 3) { return ''; }
    var mes = MESES[parseInt(partes[1], 10) - 1] || '';
    return partes[2] + ' ' + mes + '. ' + partes[0];
  }

  // Añade una línea de texto a "contenedor", interpretando **negrita** y
  // [texto](url) como nodos reales (nunca innerHTML: así no hay forma de
  // colar HTML/JS aunque el texto de la publicación contuviera algo raro).
  function anadirLineaFormateada(contenedor, linea) {
    var ultimoIndice = 0;
    var match;
    REGEX_FORMATO.lastIndex = 0;

    while ((match = REGEX_FORMATO.exec(linea)) !== null) {
      if (match.index > ultimoIndice) {
        contenedor.appendChild(document.createTextNode(linea.slice(ultimoIndice, match.index)));
      }

      if (match[1] !== undefined) {
        var fuerte = document.createElement('strong');
        fuerte.textContent = match[1];
        contenedor.appendChild(fuerte);
      } else {
        var url = match[3];
        // Solo enlaces http(s) o mailto — nunca javascript: ni otros
        // esquemas, aunque el texto de la publicación lo intente.
        if (/^https?:\/\//i.test(url) || /^mailto:/i.test(url)) {
          var enlace = document.createElement('a');
          enlace.textContent = match[2];
          enlace.href = url;
          enlace.target = '_blank';
          enlace.rel = 'noopener';
          contenedor.appendChild(enlace);
        } else {
          contenedor.appendChild(document.createTextNode(match[0]));
        }
      }

      ultimoIndice = REGEX_FORMATO.lastIndex;
    }

    if (ultimoIndice < linea.length) {
      contenedor.appendChild(document.createTextNode(linea.slice(ultimoIndice)));
    }
  }

  // Texto completo con formato: cada salto de línea se respeta (como
  // escribió el CEO), con negrita y enlaces ya interpretados.
  function crearContenidoFormateado(texto) {
    var frag = document.createDocumentFragment();
    var lineas = texto.split('\n');
    lineas.forEach(function (linea, indice) {
      anadirLineaFormateada(frag, linea);
      if (indice < lineas.length - 1) {
        frag.appendChild(document.createElement('br'));
      }
    });
    return frag;
  }

  // Versión sin formato ni saltos de línea, solo para medir/cortar la vista
  // previa antes de expandir (evita cortar a medio "**" o a medio enlace).
  function textoPlanoParaPreview(texto) {
    return texto
      .replace(/\*\*(.+?)\*\*/g, '$1')
      .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
      .replace(/\s*\n+\s*/g, ' ')
      .trim();
  }

  function crearParrafoResumen(texto) {
    var p = document.createElement('p');
    var plano = textoPlanoParaPreview(texto);

    if (plano.length <= LIMITE_RESUMEN) {
      p.appendChild(crearContenidoFormateado(texto));
      return p;
    }

    var expandido = false;
    var contenedorTexto = document.createElement('span');
    var boton = document.createElement('button');
    boton.type = 'button';
    boton.className = 'enlace-ver-mas';

    function actualizar() {
      contenedorTexto.textContent = '';
      if (expandido) {
        contenedorTexto.appendChild(crearContenidoFormateado(texto));
      } else {
        contenedorTexto.textContent = plano.slice(0, LIMITE_RESUMEN).trim() + '… ';
      }
      boton.textContent = expandido ? 'ver menos' : 'ver más';
    }

    boton.addEventListener('click', function (evento) {
      evento.stopPropagation(); // no abrir el visor al plegar/desplegar
      expandido = !expandido;
      actualizar();
    });

    actualizar();
    p.appendChild(contenedorTexto);
    p.appendChild(document.createTextNode(' '));
    p.appendChild(boton);
    return p;
  }

  // Visor a pantalla completa (imagen grande o PDF embebido) con una "X"
  // para cerrar — se crea una sola vez y lo reutilizan todas las tarjetas.
  function asegurarVisor() {
    var visor = document.getElementById('capturam-visor');
    if (visor) { return visor; }

    visor = document.createElement('div');
    visor.id = 'capturam-visor';
    visor.className = 'visor-overlay';
    visor.hidden = true;

    var cerrar = document.createElement('button');
    cerrar.type = 'button';
    cerrar.className = 'visor-cerrar';
    cerrar.setAttribute('aria-label', 'Cerrar');
    cerrar.textContent = '×';

    var contenido = document.createElement('div');
    contenido.className = 'visor-contenido';

    visor.appendChild(cerrar);
    visor.appendChild(contenido);
    document.body.appendChild(visor);

    function cerrarVisor() {
      visor.hidden = true;
      contenido.innerHTML = '';
    }

    cerrar.addEventListener('click', cerrarVisor);
    // Clic en cualquier parte del fondo oscuro cierra el visor — el propio
    // "contenido" (flex a pantalla completa) es quien realmente recibe el
    // clic en la zona vacía alrededor del PDF/imagen, no el overlay.
    visor.addEventListener('click', function (evento) {
      if (evento.target === visor || evento.target === contenido) { cerrarVisor(); }
    });
    document.addEventListener('keydown', function (evento) {
      if (evento.key === 'Escape' && !visor.hidden) { cerrarVisor(); }
    });

    visor._contenido = contenido;
    return visor;
  }

  function abrirVisor(publicacion) {
    var visor = asegurarVisor();
    var contenido = visor._contenido;
    contenido.innerHTML = '';

    var url = 'publicaciones/' + encodeURIComponent(publicacion.archivo);

    if (publicacion.tipo === 'imagen') {
      var img = document.createElement('img');
      img.src = url;
      img.alt = publicacion.titulo;
      contenido.appendChild(img);
    } else {
      var iframe = document.createElement('iframe');
      iframe.src = url;
      iframe.title = publicacion.titulo;
      contenido.appendChild(iframe);
    }

    visor.hidden = false;
  }

  // Icono genérico de documento para la vista previa de los PDF (markup fijo,
  // sin ningún dato de la publicación — seguro usarlo tal cual, no es texto
  // de usuario). Se ve mientras se genera la miniatura real, o si algo falla.
  var ICONO_PDF_SVG = '<svg viewBox="0 0 24 24" width="40" height="40" fill="none" '
    + 'stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
    + '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>'
    + '<path d="M14 2v6h6"></path></svg>';

  // Miniatura real de la primera página de un PDF, generada en el propio
  // navegador con PDF.js (no depende de que el hosting tenga Ghostscript ni
  // ninguna otra herramienta instalada). Se carga la librería una sola vez
  // y se reutiliza para todos los PDF de la página.
  var PDFJS_VERSION = '3.11.174';
  var PDFJS_BASE = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/' + PDFJS_VERSION + '/';
  var promesaPdfJs = null;

  function cargarPdfJs() {
    if (global.pdfjsLib) { return Promise.resolve(global.pdfjsLib); }
    if (promesaPdfJs) { return promesaPdfJs; }

    promesaPdfJs = new Promise(function (resolve, reject) {
      var script = document.createElement('script');
      script.src = PDFJS_BASE + 'pdf.min.js';
      script.onload = function () {
        if (global.pdfjsLib) {
          global.pdfjsLib.GlobalWorkerOptions.workerSrc = PDFJS_BASE + 'pdf.worker.min.js';
          resolve(global.pdfjsLib);
        } else {
          reject(new Error('pdfjsLib no disponible tras cargar pdf.js'));
        }
      };
      script.onerror = function () { reject(new Error('No se pudo cargar pdf.js')); };
      document.head.appendChild(script);
    });

    return promesaPdfJs;
  }

  // Si algo se queda colgado (p. ej. el worker de pdf.js no responde por lo
  // que sea en ese navegador/red), no dejamos la promesa pendiente para
  // siempre: a los 8s se rechaza igualmente y se queda el icono de repuesto.
  function conTiempoLimite(promesa, ms) {
    return new Promise(function (resolve, reject) {
      var id = setTimeout(function () { reject(new Error('tiempo de espera agotado')); }, ms);
      promesa.then(function (valor) { clearTimeout(id); resolve(valor); },
        function (error) { clearTimeout(id); reject(error); });
    });
  }

  // Pinta la página ya cargada en "canvas" al tamaño exacto que tenga la
  // caja EN ESE MOMENTO, recortando tipo "cover" — igual que object-fit:cover
  // en las miniaturas de imagen — para que ambos tipos de tarjeta se vean
  // igual de cuidados.
  function dibujarPaginaPdf(page, anchoCaja, altoCaja, canvas) {
    var dpr = global.devicePixelRatio || 1;
    var vistaBase = page.getViewport({ scale: 1 });
    var escala = Math.max(anchoCaja / vistaBase.width, altoCaja / vistaBase.height) * dpr;
    var vista = page.getViewport({ scale: escala });

    canvas.width = anchoCaja * dpr;
    canvas.height = altoCaja * dpr;
    canvas.style.width = anchoCaja + 'px';
    canvas.style.height = altoCaja + 'px';

    var contexto = canvas.getContext('2d');
    // Recorte centrado en horizontal, pero siempre desde ARRIBA en
    // vertical (como una miniatura de documento normal: Google Drive,
    // Dropbox...) — no por el centro, que corta el documento por la
    // mitad y deja el título fuera.
    contexto.setTransform(1, 0, 0, 1, 0, 0);
    contexto.translate((canvas.width - vista.width) / 2, 0);

    return page.render({ canvasContext: contexto, viewport: vista }).promise;
  }

  // Vista previa de la publicación: la imagen real si es una imagen, o la
  // miniatura real de la primera página si es un PDF.
  function crearVistaPrevia(publicacion) {
    if (publicacion.tipo === 'imagen') {
      var img = document.createElement('img');
      img.src = 'publicaciones/' + encodeURIComponent(publicacion.archivo);
      img.alt = publicacion.titulo;
      img.className = 'card-sector-img';
      img.loading = 'lazy';
      return img;
    }

    var previa = document.createElement('div');
    previa.className = 'card-sector-img vista-previa-pdf';
    previa.innerHTML = ICONO_PDF_SVG + '<span>PDF</span>';

    var canvas = document.createElement('canvas');
    canvas.className = 'vista-previa-pdf-canvas';
    canvas.hidden = true;
    previa.appendChild(canvas);

    var url = 'publicaciones/' + encodeURIComponent(publicacion.archivo);
    var promesaPagina = conTiempoLimite(
      cargarPdfJs().then(function (pdfjsLib) { return pdfjsLib.getDocument(url).promise; }),
      8000
    ).then(function (pdf) { return pdf.getPage(1); });

    // El tamaño de la caja puede cambiar después del primer pintado (p. ej.
    // en pantallas muy grandes, donde las columnas son mucho más anchas):
    // sin volver a redibujar a ese tamaño nuevo, el canvas se queda con la
    // resolución antigua, pequeña, en una esquina de una caja ahora enorme.
    // ResizeObserver detecta ese cambio y repinta al tamaño correcto.
    var ultimoAncho = 0;
    var ultimoAlto = 0;

    function repintar() {
      var caja = previa.getBoundingClientRect();
      if (caja.width < 1 || caja.height < 1) { return; }
      if (Math.abs(caja.width - ultimoAncho) < 1 && Math.abs(caja.height - ultimoAlto) < 1) { return; }
      ultimoAncho = caja.width;
      ultimoAlto = caja.height;

      promesaPagina
        .then(function (page) { return dibujarPaginaPdf(page, caja.width, caja.height, canvas); })
        .then(function () { canvas.hidden = false; })
        .catch(function () {
          // pdf.js no disponible (CDN bloqueado) o PDF no renderizable: se
          // queda el icono de repuesto, ya visible detrás del canvas.
        });
    }

    if (typeof ResizeObserver !== 'undefined') {
      new ResizeObserver(repintar).observe(previa);
    } else {
      // Navegador muy antiguo sin ResizeObserver: al menos un intento.
      requestAnimationFrame(repintar);
    }

    return previa;
  }

  function crearTarjeta(publicacion, compacto) {
    var sinTexto = !publicacion.resumen;

    // Misma estructura que las tarjetas de "Sectores": vista previa arriba
    // + cuerpo debajo. "tarjeta-publicacion" limita la altura de la vista
    // previa a algo modesto (la versión grande se ve en el visor al hacer
    // clic); sin resumen no hay texto que ocupe la tarjeta, así que la
    // vista previa crece para llenar el hueco en vez de dejarlo en blanco.
    // "compacto" es aún más pequeño — el aperitivo de 3 tarjetas en Inicio.
    var card = document.createElement('div');
    card.className = 'card-sector tarjeta-publicacion'
      + (sinTexto ? ' tarjeta-publicacion-sin-texto' : '')
      + (compacto ? ' tarjeta-publicacion-compacta' : '');

    card.appendChild(crearVistaPrevia(publicacion));

    var cuerpo = document.createElement('div');
    cuerpo.className = 'card-sector-body';
    card.appendChild(cuerpo);

    var fecha = document.createElement('span');
    fecha.className = 'servicio-numero';
    fecha.textContent = formatearFecha(publicacion.fecha);
    cuerpo.appendChild(fecha);

    var titulo = document.createElement('h3');
    titulo.textContent = publicacion.titulo;
    cuerpo.appendChild(titulo);

    if (publicacion.resumen) {
      cuerpo.appendChild(crearParrafoResumen(publicacion.resumen));
    }

    var boton = document.createElement('button');
    boton.type = 'button';
    // Sin "mt-2" de Bootstrap: esa utilidad usa "!important" y anulaba en
    // silencio el margin-top:auto que fija el botón abajo del todo (ver
    // regla ".tarjeta-publicacion .card-sector-body > .btn" en el CSS).
    boton.className = 'btn btn-capturam-outline';
    boton.textContent = publicacion.tipo === 'imagen' ? 'Ver imagen completa' : 'Ver PDF completo';
    boton.addEventListener('click', function () { abrirVisor(publicacion); });
    cuerpo.appendChild(boton);

    // Clic en cualquier parte de la tarjeta (menos enlaces, botones y el
    // propio "ver más") también abre el visor — no hace falta acertar en
    // el botón, como al hacer clic en una foto o un PDF de LinkedIn.
    card.classList.add('tarjeta-publicacion-clicable');
    card.addEventListener('click', function (evento) {
      if (evento.target.closest('a, button')) { return; }
      abrirVisor(publicacion);
    });

    return card;
  }

  global.CapturamPublicaciones = {
    /**
     * opciones.contenedor: elemento donde insertar las tarjetas (obligatorio).
     * opciones.vacio: elemento a mostrar si no hay publicaciones (opcional).
     * opciones.limite: máximo de publicaciones a mostrar (opcional).
     * opciones.compacto: tarjetas más pequeñas, para el aperitivo de Inicio (opcional).
     * opciones.alCargar: function(hayPublicaciones) (opcional).
     */
    cargar: function (opciones) {
      var contenedor = opciones.contenedor;
      var vacio = opciones.vacio;
      var limite = opciones.limite || Infinity;
      var compacto = !!opciones.compacto;

      fetch('publicaciones.php')
        .then(function (respuesta) { return respuesta.ok ? respuesta.json() : Promise.reject(); })
        .then(function (datos) {
          var publicaciones = ((datos && datos.publicaciones) || []).slice(0, limite);

          if (publicaciones.length === 0) {
            if (vacio) { vacio.hidden = false; }
            if (opciones.alCargar) { opciones.alCargar(false); }
            return;
          }

          publicaciones.forEach(function (publicacion) {
            var col = document.createElement('div');
            col.className = 'col-md-6 col-lg-4';
            col.appendChild(crearTarjeta(publicacion, compacto));
            contenedor.appendChild(col);
          });

          if (opciones.alCargar) { opciones.alCargar(true); }
        })
        .catch(function () {
          if (vacio) { vacio.hidden = false; }
          if (opciones.alCargar) { opciones.alCargar(false); }
        });
    }
  };
})(window);
