document.addEventListener("DOMContentLoaded", function () {
  var elementos = document.querySelectorAll(".revelar");

  if (!("IntersectionObserver" in window)) {
    elementos.forEach(function (el) {
      el.classList.add("visible");
    });
    return;
  }

  var observer = new IntersectionObserver(
    function (entradas) {
      entradas.forEach(function (entrada) {
        if (entrada.isIntersecting) {
          entrada.target.classList.add("visible");
          observer.unobserve(entrada.target);
        }
      });
    },
    { threshold: 0.15, rootMargin: "0px 0px -40px 0px" }
  );

  elementos.forEach(function (el) {
    observer.observe(el);
  });
});
