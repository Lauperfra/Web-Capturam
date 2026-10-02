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

  // Resumen corto en la tarjeta; "ver más" no despliega en el sitio (eso
  // hacía que la tarjeta se recortase rara o se saliera del pie de página
  // cuando la publicación estaba al final del listado) — abre el texto
  // completo en un visor centrado propio, independiente del de PDF/imagen,
  // para que se puedan tener abiertos los dos a la vez sin que uno pise el
  // contenido del otro.
  function crearParrafoResumen(publicacion) {
    var texto = publicacion.resumen;
    var p = document.createElement('p');
    var plano = textoPlanoParaPreview(texto);

    if (plano.length <= LIMITE_RESUMEN) {
      p.appendChild(crearContenidoFormateado(texto));
      return p;
    }

    var contenedorTexto = document.createElement('span');
    contenedorTexto.textContent = plano.slice(0, LIMITE_RESUMEN).trim() + '… ';

    var boton = document.createElement('button');
    boton.type = 'button';
    boton.className = 'enlace-ver-mas';
    boton.textContent = 'ver más';
    boton.addEventListener('click', function (evento) {
      evento.stopPropagation(); // no abrir el visor de PDF/imagen al pulsar este
      abrirVisorTexto(publicacion);
    });

    p.appendChild(contenedorTexto);
    p.appendChild(document.createTextNode(' '));
    p.appendChild(boton);
    return p;
  }

  // Pila de visores abiertos (texto y/o PDF/imagen: una misma publicación
  // puede tener los dos abiertos a la vez, uno encima del otro). Sirve para
  // dos cosas: pintar por delante al que se acaba de abrir/reabrir (moverlo
  // al final de <body>, mismo z-index que el resto, así que el último en
  // el DOM es el que se ve), y que Escape cierre solo el de encima en vez
  // de cerrar los dos de golpe.
  var pilaVisores = [];

  function traerAlFrente(visor) {
    document.body.appendChild(visor);
    pilaVisores = pilaVisores.filter(function (v) { return v !== visor; });
    pilaVisores.push(visor);
  }

  function olvidarVisor(visor) {
    pilaVisores = pilaVisores.filter(function (v) { return v !== visor; });
  }

  document.addEventListener('keydown', function (evento) {
    if (evento.key !== 'Escape' || pilaVisores.length === 0) { return; }
    pilaVisores[pilaVisores.length - 1]._cerrar();
  });

  // Visor de texto completo: mismo lenguaje visual que el de PDF/imagen
  // (fondo oscurecido, tarjeta centrada, "X" para cerrar), pero en su propio
  // overlay — así una publicación con texto largo Y PDF/imagen puede abrir
  // los dos a la vez: desde aquí se puede abrir también el PDF/imagen (se
  // apila encima) sin perder este texto, que sigue esperando debajo.
  function asegurarVisorTexto() {
    var visor = document.getElementById('capturam-visor-texto');
    if (visor) { return visor; }

    visor = document.createElement('div');
    visor.id = 'capturam-visor-texto';
    visor.className = 'visor-overlay visor-overlay-texto';
    visor.hidden = true;

    var cerrar = document.createElement('button');
    cerrar.type = 'button';
    cerrar.className = 'visor-cerrar';
    cerrar.setAttribute('aria-label', 'Cerrar');
    cerrar.textContent = '×';

    var contenido = document.createElement('div');
    contenido.className = 'visor-texto-contenido';

    visor.appendChild(cerrar);
    visor.appendChild(contenido);
    document.body.appendChild(visor);

    function cerrarVisorTexto() {
      visor.hidden = true;
      contenido.innerHTML = '';
      olvidarVisor(visor);
    }

    cerrar.addEventListener('click', cerrarVisorTexto);
    visor.addEventListener('click', function (evento) {
      if (evento.target === visor) { cerrarVisorTexto(); }
    });

    visor._contenido = contenido;
    visor._cerrar = cerrarVisorTexto;
    return visor;
  }

  function abrirVisorTexto(publicacion) {
    var visor = asegurarVisorTexto();
    var contenido = visor._contenido;
    contenido.innerHTML = '';

    var fecha = document.createElement('span');
    fecha.className = 'servicio-numero';
    fecha.textContent = formatearFecha(publicacion.fecha);
    contenido.appendChild(fecha);

    var titulo = document.createElement('h3');
    titulo.textContent = publicacion.titulo;
    contenido.appendChild(titulo);

    var cuerpo = document.createElement('div');
    cuerpo.className = 'visor-texto-cuerpo';
    cuerpo.appendChild(crearContenidoFormateado(publicacion.resumen));
    contenido.appendChild(cuerpo);

    // También se puede ver el PDF/imagen de esta publicación sin cerrar el
    // texto: se abre por encima, apilado.
    var botonMedia = document.createElement('button');
    botonMedia.type = 'button';
    botonMedia.className = 'btn btn-capturam-outline visor-texto-boton-media';
    botonMedia.textContent = publicacion.tipo === 'imagen' ? 'Ver imagen completa' : 'Ver PDF completo';
    botonMedia.addEventListener('click', function () { abrirVisor(publicacion); });
    contenido.appendChild(botonMedia);

    visor.hidden = false;
    traerAlFrente(visor);
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
      olvidarVisor(visor);
    }

    cerrar.addEventListener('click', cerrarVisor);
    // Clic en cualquier parte del fondo oscuro cierra el visor — el propio
    // "contenido" (flex a pantalla completa) es quien realmente recibe el
    // clic en la zona vacía alrededor del PDF/imagen, no el overlay.
    visor.addEventListener('click', function (evento) {
      if (evento.target === visor || evento.target === contenido) { cerrarVisor(); }
    });

    visor._contenido = contenido;
    visor._cerrar = cerrarVisor;
    return visor;
  }

  // iPadOS 13+ hace pasar el user-agent por uno de Mac normal — el único
  // rasgo fiable que le queda para distinguirlo es tener pantalla táctil,
  // algo que un Mac de verdad no tiene.
  function esIOS() {
    return /iPad|iPhone|iPod/.test(navigator.userAgent)
      || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  }

  function abrirVisor(publicacion) {
    var url = 'publicaciones-datos/' + encodeURIComponent(publicacion.archivo);

    // Un PDF embebido en <iframe> dentro de un overlay position:fixed se ve
    // mal en Safari de iOS: aparece recortado/ampliado y el pinch-to-zoom
    // no funciona con normalidad — es una limitación vieja y conocida de
    // iOS con PDFs en iframes, no algo que se pueda arreglar por CSS. Mejor
    // dejar que lo abra el propio visor nativo de PDF de iOS (con zoom,
    // buscador y botón de compartir de verdad) en vez de nuestro visor.
    if (publicacion.tipo !== 'imagen' && esIOS()) {
      global.open(url, '_blank');
      return;
    }

    var visor = asegurarVisor();
    var contenido = visor._contenido;
    contenido.innerHTML = '';

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

    // También se puede leer el texto completo de esta publicación sin
    // cerrar el PDF/imagen: se abre por encima, apilado. El botón es fijo
    // (hijo directo del visor, no de "contenido", que se vacía en cada
    // apertura) y se reutiliza entre publicaciones.
    var botonTexto = visor.querySelector('.visor-boton-texto');
    if (publicacion.resumen) {
      if (!botonTexto) {
        botonTexto = document.createElement('button');
        botonTexto.type = 'button';
        botonTexto.className = 'visor-boton-texto';
        visor.appendChild(botonTexto);
      }
      botonTexto.hidden = false;
      botonTexto.textContent = 'Ver texto completo';
      botonTexto.onclick = function () { abrirVisorTexto(publicacion); };
    } else if (botonTexto) {
      botonTexto.hidden = true;
    }

    visor.hidden = false;
    traerAlFrente(visor);
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
  var PDFJS_BASE = '/static/vendor/pdfjs/';
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
  // caja EN ESE MOMENTO: página entera sin recortar ("contain") encima de
  // un fondo de la misma página recortada y difuminada ("cover" + blur) —
  // así una página muy vertical u horizontal respecto a la caja no deja
  // barras en blanco ni se recorta, igual que la portada de un álbum en
  // Spotify. Dos pasadas de render porque cada una usa un "viewport"
  // (escala) distinto.
  function dibujarPaginaPdf(page, anchoCaja, altoCaja, canvas) {
    var dpr = global.devicePixelRatio || 1;
    var vistaBase = page.getViewport({ scale: 1 });

    canvas.width = anchoCaja * dpr;
    canvas.height = altoCaja * dpr;
    canvas.style.width = anchoCaja + 'px';
    canvas.style.height = altoCaja + 'px';

    var escalaFondo = Math.max(canvas.width / vistaBase.width, canvas.height / vistaBase.height);
    var vistaFondo = page.getViewport({ scale: escalaFondo });
    var fondo = document.createElement('canvas');
    fondo.width = vistaFondo.width;
    fondo.height = vistaFondo.height;

    var escalaPrimerPlano = Math.min(canvas.width / vistaBase.width, canvas.height / vistaBase.height);
    var vistaPrimerPlano = page.getViewport({ scale: escalaPrimerPlano });
    var primerPlano = document.createElement('canvas');
    primerPlano.width = vistaPrimerPlano.width;
    primerPlano.height = vistaPrimerPlano.height;

    // Cada capa se renderiza en su propio canvas "limpio", al tamaño exacto
    // que necesita — nunca directamente sobre "canvas" con una transformación
    // ya aplicada: pdf.js fija su propia transformación internamente al
    // llamar a render(), y no combina bien con un translate() previo (se
    // veía descentrado hacia una esquina, con el difuminado asomando solo
    // por un lado). Centrar con drawImage() después es lo único fiable.
    return page.render({ canvasContext: fondo.getContext('2d'), viewport: vistaFondo }).promise
      .then(function () {
        return page.render({ canvasContext: primerPlano.getContext('2d'), viewport: vistaPrimerPlano }).promise;
      })
      .then(function () {
        var contexto = canvas.getContext('2d');
        contexto.setTransform(1, 0, 0, 1, 0, 0);
        contexto.clearRect(0, 0, canvas.width, canvas.height);

        if (esIOS()) {
          // Safari no soporta "filter" en canvas (lo ignora en silencio,
          // sin avisar) — así que aquí, en vez de depender de eso, se
          // reduce la imagen a un tamaño minúsculo y se amplía después: el
          // propio escalado ya la deja borrosa, sin filtros. Se ve algo
          // más pixelado que el "filter" real, pero es lo que hay sin él.
          var mini = document.createElement('canvas');
          mini.width = Math.max(1, Math.round(canvas.width * 0.06));
          mini.height = Math.max(1, Math.round(canvas.height * 0.06));
          mini.getContext('2d').drawImage(
            fondo,
            (fondo.width - canvas.width) / 2, (fondo.height - canvas.height) / 2, canvas.width, canvas.height,
            0, 0, mini.width, mini.height
          );
          contexto.drawImage(mini, 0, 0, canvas.width, canvas.height);

          // Oscurecido (antes lo hacía "brightness(0.85)" del filter): una
          // capa negra semitransparente encima, universalmente soportada.
          contexto.fillStyle = 'rgba(0, 0, 0, 0.15)';
          contexto.fillRect(0, 0, canvas.width, canvas.height);
        } else {
          // Fuera de iOS, "filter" en canvas sí funciona bien (Chrome,
          // Firefox, Edge...) y se ve más suave que el truco de arriba.
          contexto.save();
          contexto.filter = 'blur(18px) brightness(0.85)';
          contexto.drawImage(
            fondo,
            (fondo.width - canvas.width) / 2, (fondo.height - canvas.height) / 2, canvas.width, canvas.height,
            0, 0, canvas.width, canvas.height
          );
          contexto.restore();
        }

        contexto.drawImage(primerPlano, (canvas.width - primerPlano.width) / 2, (canvas.height - primerPlano.height) / 2);
      });
  }

  // Vista previa de la publicación: la imagen real si es una imagen, o la
  // miniatura real de la primera página si es un PDF.
  function crearVistaPrevia(publicacion) {
    if (publicacion.tipo === 'imagen') {
      var url = 'publicaciones-datos/' + encodeURIComponent(publicacion.archivo);

      // Igual que con el PDF: la foto entera sin recortar ("contain")
      // encima de un fondo de la misma foto recortada y difuminada
      // ("cover" + blur) — así una foto vertical no deja barras en blanco.
      var contenedor = document.createElement('div');
      contenedor.className = 'card-sector-img vista-previa-imagen';

      var fondo = document.createElement('img');
      fondo.src = url;
      fondo.alt = '';
      fondo.setAttribute('aria-hidden', 'true');
      fondo.className = 'vista-previa-imagen-fondo';
      fondo.loading = 'lazy';
      contenedor.appendChild(fondo);

      var img = document.createElement('img');
      img.src = url;
      img.alt = publicacion.titulo;
      img.className = 'vista-previa-imagen-principal';
      img.loading = 'lazy';
      contenedor.appendChild(img);

      return contenedor;
    }

    var previa = document.createElement('div');
    previa.className = 'card-sector-img vista-previa-pdf';
    previa.innerHTML = ICONO_PDF_SVG + '<span>PDF</span>';

    var canvas = document.createElement('canvas');
    canvas.className = 'vista-previa-pdf-canvas';
    canvas.hidden = true;
    previa.appendChild(canvas);

    var url = 'publicaciones-datos/' + encodeURIComponent(publicacion.archivo);
    var promesaPagina = conTiempoLimite(
      // "isEvalSupported: false" desactiva en pdf.js la vía de ejecutar
      // JavaScript al procesar ciertas fuentes de un PDF (CVE-2024-4367,
      // corregido de fondo en pdf.js >= 4.2.67 — la versión vendorizada
      // aquí, 3.11.174, sigue afectada). Sin esto, un PDF manipulado a
      // propósito podría ejecutar código en el navegador de cualquier
      // visitante que viera la miniatura, aunque quien lo suba sea de
      // confianza (puede venir de un tercero — CNMC, IRENA... — no todo
      // lo genera el propio CEO). No afecta a cómo se ve la miniatura.
      cargarPdfJs().then(function (pdfjsLib) { return pdfjsLib.getDocument({ url: url, isEvalSupported: false }).promise; }),
      8000
    ).then(function (pdf) { return pdf.getPage(1); });

    // El tamaño de la caja puede cambiar después del primer pintado (p. ej.
    // en pantallas muy grandes, donde las columnas son mucho más anchas):
    // sin volver a redibujar a ese tamaño nuevo, el canvas se queda con la
    // resolución antigua, pequeña, en una esquina de una caja ahora enorme.
    // ResizeObserver detecta ese cambio y repinta al tamaño correcto.
    var ultimoAncho = 0;
    var ultimoAlto = 0;

    // El dibujado ahora son dos pasadas de render encadenadas (fondo
    // difuminado + página nítida encima, ver dibujarPaginaPdf) sobre el
    // MISMO canvas: si "repintar" se solapase consigo mismo (el
    // ResizeObserver puede disparar varias veces seguidas mientras la
    // página todavía está acomodando fuentes/imágenes) una llamada pisaría
    // a medias el dibujo de la otra. Por eso nunca hay más de una en
    // marcha — si llega otra mientras tanto, se guarda y se relanza al
    // terminar la que está en curso, ya con el tamaño más reciente.
    var pintando = false;
    var repintarPendiente = false;

    function repintar() {
      var caja = previa.getBoundingClientRect();
      if (caja.width < 1 || caja.height < 1) { return; }
      if (Math.abs(caja.width - ultimoAncho) < 1 && Math.abs(caja.height - ultimoAlto) < 1) { return; }

      if (pintando) { repintarPendiente = true; return; }
      pintando = true;
      ultimoAncho = caja.width;
      ultimoAlto = caja.height;

      promesaPagina
        .then(function (page) { return dibujarPaginaPdf(page, caja.width, caja.height, canvas); })
        .then(function () { canvas.hidden = false; })
        .catch(function () {
          // pdf.js no disponible (CDN bloqueado) o PDF no renderizable: se
          // queda el icono de repuesto, ya visible detrás del canvas.
        })
        .then(function () {
          pintando = false;
          if (repintarPendiente) { repintarPendiente = false; repintar(); }
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
      cuerpo.appendChild(crearParrafoResumen(publicacion));
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
