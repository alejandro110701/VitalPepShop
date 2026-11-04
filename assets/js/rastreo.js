/* Rastreo de pedidos: envía el formulario por fetch y pinta el HTML que
 * devuelve /wp-json/vp/v1/rastreo. Sin JS el formulario sigue funcionando
 * (GET a la misma página, que renderiza el resultado en el servidor). */
(function () {
  'use strict';

  var cfg = window.VP_RASTREO || {};

  function copiar(btn) {
    var txt = btn.getAttribute('data-copy') || '';
    if (!txt || !navigator.clipboard) return;
    navigator.clipboard.writeText(txt).then(function () {
      btn.classList.add('is-copied');
      setTimeout(function () { btn.classList.remove('is-copied'); }, 1600);
    });
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.vp-copy');
    if (btn) { e.preventDefault(); copiar(btn); }
  });

  var form = document.querySelector('.vp-rastreo__form');
  if (!form || !cfg.url || !window.fetch) return;

  var result = document.querySelector('.vp-rastreo__result');
  var btn = form.querySelector('button[type="submit"]');
  var pedido = form.querySelector('[name="pedido"]');
  var correo = form.querySelector('[name="correo"]');

  function setLoading(on) {
    form.classList.toggle('is-loading', on);
    if (btn) btn.disabled = on;
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var p = (pedido.value || '').replace(/\D+/g, '');
    var c = (correo.value || '').trim();
    if (!p || !c) {
      result.innerHTML = '<div class="vp-alert vp-alert--warn" role="alert"><div><p>Escribe el número de pedido y el correo con el que compraste.</p></div></div>';
      (p ? correo : pedido).focus();
      return;
    }
    setLoading(true);
    result.innerHTML = '<div class="vp-skeleton" aria-busy="true"><span></span><span></span><span></span></div>';

    var headers = { 'Content-Type': 'application/json' };
    if (cfg.nonce) headers['X-WP-Nonce'] = cfg.nonce;

    fetch(cfg.url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: headers,
      body: JSON.stringify({ pedido: p, correo: c })
    })
      .then(function (r) { return r.json().catch(function () { return null; }); })
      .then(function (j) {
        if (j && j.html) {
          result.innerHTML = j.html;
        } else {
          result.innerHTML = '<div class="vp-alert vp-alert--warn" role="alert"><div><p>No pudimos consultar el pedido en este momento. Intenta de nuevo en un minuto.</p></div></div>';
        }
        try {
          var url = new URL(window.location.href);
          url.searchParams.set('pedido', p);
          url.searchParams.set('correo', c);
          window.history.replaceState(null, '', url.toString());
        } catch (err) { /* sin historial, no pasa nada */ }
        result.scrollIntoView({ behavior: 'smooth', block: 'start' });
      })
      .catch(function () {
        result.innerHTML = '<div class="vp-alert vp-alert--warn" role="alert"><div><p>Sin conexión. Revisa tu red e intenta de nuevo.</p></div></div>';
      })
      .then(function () { setLoading(false); });
  });
})();
