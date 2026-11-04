/* Cabecera: menú móvil. Nada más. */
(function () {
  'use strict';
  var header = document.querySelector('.vp-header');
  if (!header) return;
  var burger = header.querySelector('.vp-burger');
  var nav = header.querySelector('.vp-nav');
  if (!burger || !nav) return;

  function setOpen(open) {
    header.classList.toggle('is-open', open);
    burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    document.documentElement.classList.toggle('vp-nav-open', open);
  }

  burger.addEventListener('click', function () {
    setOpen(!header.classList.contains('is-open'));
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') setOpen(false);
  });
  document.addEventListener('click', function (e) {
    if (header.classList.contains('is-open') && !header.contains(e.target)) setOpen(false);
  });
})();
