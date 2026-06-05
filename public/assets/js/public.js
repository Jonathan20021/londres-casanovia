/* ============================================================
   LONDRES Casa de Novias — JS del sitio público
   ============================================================ */
(function () {
  'use strict';

  /* ---------- Menú móvil ---------- */
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-mobile-menu-toggle]')) {
      document.getElementById('mobile-menu')?.classList.toggle('hidden');
    }
  });

  /* ---------- Header con sombra al hacer scroll ---------- */
  const header = document.querySelector('[data-public-header]');
  if (header) {
    const onScroll = () => header.classList.toggle('shadow-lg', window.scrollY > 20);
    window.addEventListener('scroll', onScroll, { passive: true }); onScroll();
  }

  /* ---------- Verificación de disponibilidad pública ---------- *
   * Botón data-public-availability con:
   *   data-product="123"  data-delivery="#fldEntrega" data-return="#fldDevolucion"
   *   data-event="#fldEvento"  data-result="#resultado"  data-form="#formSolicitud"
   */
  document.querySelectorAll('[data-public-availability]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const pid = btn.getAttribute('data-product');
      const get = s => document.querySelector(btn.getAttribute(s));
      const eventEl = get('data-event');
      const delivery = get('data-delivery');
      const ret = get('data-return');
      const box = get('data-result');
      const form = get('data-form');

      // Si solo hay fecha de evento, sugerir entrega (día antes) y devolución (2 días después)
      if (eventEl && eventEl.value && delivery && ret && (!delivery.value || !ret.value)) {
        const ev = new Date(eventEl.value + 'T00:00:00');
        const d = new Date(ev); d.setDate(d.getDate() - 1);
        const r = new Date(ev); r.setDate(r.getDate() + 2);
        delivery.value = d.toISOString().slice(0, 10);
        ret.value = r.toISOString().slice(0, 10);
      }
      if (!pid || !delivery?.value || !ret?.value) {
        if (box) box.innerHTML = banner('warn', 'Seleccione la fecha de su evento para continuar.');
        return;
      }
      if (box) box.innerHTML = '<div class="text-center text-sm text-gray-400 py-2">Verificando disponibilidad…</div>';
      const url = window.LCN_BASE + '/public/api/check-availability.php?product_id=' + encodeURIComponent(pid) +
        '&delivery=' + encodeURIComponent(delivery.value) + '&return=' + encodeURIComponent(ret.value);
      fetch(url).then(r => r.json()).then(function (d) {
        if (!box) return;
        if (d.available) {
          box.innerHTML = banner('ok', '✓ ¡Disponible! Complete sus datos para enviar la solicitud.');
          if (form) form.classList.remove('hidden');
        } else {
          box.innerHTML = banner('err',
            'Este vestido/traje ya está reservado para la fecha seleccionada. Por favor seleccione otra fecha.');
          if (form) form.classList.add('hidden');
        }
      }).catch(() => { if (box) box.innerHTML = banner('err', 'No se pudo verificar la disponibilidad.'); });
    });
  });

  /* ---------- Revelado al hacer scroll ---------- */
  (function () {
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var items = document.querySelectorAll('[data-reveal]');
    if (reduce || !('IntersectionObserver' in window) || !items.length) return;
    document.documentElement.classList.add('js-reveal');
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add('is-visible'); io.unobserve(en.target); }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });
    items.forEach(function (el) {
      // Stagger natural entre hermanos que también revelan
      try {
        var sibs = el.parentElement ? el.parentElement.querySelectorAll(':scope > [data-reveal]') : [el];
        var idx = Array.prototype.indexOf.call(sibs, el);
        if (idx > 0) el.style.transitionDelay = Math.min(idx, 6) * 65 + 'ms';
      } catch (e) {}
      io.observe(el);
    });
  })();

  function banner(kind, text) {
    const map = {
      ok: ['border-emerald-200 bg-emerald-50 text-emerald-800', '✓'],
      warn: ['border-amber-200 bg-amber-50 text-amber-800', '!'],
      err: ['border-rose-200 bg-rose-50 text-rose-800', '⚠']
    };
    const [cls] = map[kind] || map.warn;
    return '<div class="rounded-2xl border ' + cls + ' px-4 py-3 text-sm font-medium animate-fade-in">' + text + '</div>';
  }
})();
