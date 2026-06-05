/* ============================================================
   LONDRES Casa de Novias — JS del panel administrativo
   Dependencias: ninguna (vanilla). Hooks por data-attributes.
   ============================================================ */
(function () {
  'use strict';

  /* ---------- Modo oscuro ---------- */
  const root = document.documentElement;
  if (localStorage.getItem('lcn-theme') === 'dark') root.classList.add('dark');
  document.addEventListener('click', function (e) {
    const t = e.target.closest('[data-theme-toggle]');
    if (!t) return;
    root.classList.toggle('dark');
    localStorage.setItem('lcn-theme', root.classList.contains('dark') ? 'dark' : 'light');
  });

  /* ---------- Sidebar móvil ---------- */
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-sidebar-open]'))  { toggleSidebar(true);  }
    if (e.target.closest('[data-sidebar-close]')) { toggleSidebar(false); }
  });
  function toggleSidebar(open) {
    const sb = document.getElementById('lcn-sidebar');
    const ov = document.getElementById('lcn-sidebar-overlay');
    if (!sb) return;
    sb.classList.toggle('-translate-x-full', !open);
    if (ov) ov.classList.toggle('hidden', !open);
  }

  /* ---------- Dropdowns (toggle por id) ---------- */
  document.addEventListener('click', function (e) {
    const trig = e.target.closest('[data-dropdown-toggle]');
    if (trig) {
      const id = trig.getAttribute('data-dropdown-toggle');
      const menu = document.getElementById(id);
      if (menu) {
        document.querySelectorAll('[data-dropdown]').forEach(m => { if (m !== menu) m.classList.add('hidden'); });
        menu.classList.toggle('hidden');
        e.stopPropagation();
        return;
      }
    }
    // Cerrar abiertos al hacer clic fuera
    if (!e.target.closest('[data-dropdown]')) {
      document.querySelectorAll('[data-dropdown]').forEach(m => m.classList.add('hidden'));
    }
  });

  /* ---------- Modales ---------- */
  window.lcnOpenModal = function (id) {
    const m = document.getElementById(id);
    if (m) { m.classList.remove('hidden'); m.classList.add('flex'); document.body.style.overflow = 'hidden'; }
  };
  window.lcnCloseModal = function (id) {
    const m = document.getElementById(id);
    if (m) { m.classList.add('hidden'); m.classList.remove('flex'); document.body.style.overflow = ''; }
  };
  document.addEventListener('click', function (e) {
    const open = e.target.closest('[data-modal-open]');
    if (open) { window.lcnOpenModal(open.getAttribute('data-modal-open')); return; }
    const close = e.target.closest('[data-modal-close]');
    if (close) {
      const modal = close.closest('[data-modal]') || document.getElementById(close.getAttribute('data-modal-close'));
      if (modal) window.lcnCloseModal(modal.id);
      return;
    }
    // clic en backdrop
    const backdrop = e.target.closest('[data-modal]');
    if (backdrop && e.target === backdrop) window.lcnCloseModal(backdrop.id);
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') document.querySelectorAll('[data-modal]:not(.hidden)').forEach(m => window.lcnCloseModal(m.id));
  });

  /* ---------- Confirmación en formularios/enlaces ---------- */
  document.addEventListener('submit', function (e) {
    const form = e.target.closest('[data-confirm]');
    if (form && !confirm(form.getAttribute('data-confirm'))) e.preventDefault();
  });
  document.addEventListener('click', function (e) {
    const link = e.target.closest('a[data-confirm]');
    if (link && !confirm(link.getAttribute('data-confirm'))) e.preventDefault();
  });

  /* ---------- Vista previa de imagen única ---------- */
  document.querySelectorAll('[data-image-preview]').forEach(function (input) {
    input.addEventListener('change', function () {
      const target = document.querySelector(input.getAttribute('data-image-preview'));
      if (target && input.files && input.files[0]) {
        target.src = URL.createObjectURL(input.files[0]);
        target.classList.remove('hidden');
      }
    });
  });

  /* ---------- Dropzone multi-imagen con vista previa ---------- */
  document.querySelectorAll('[data-dropzone]').forEach(function (zone) {
    const input = zone.querySelector('input[type=file]');
    const preview = document.querySelector(zone.getAttribute('data-dropzone-preview') || '');
    if (!input) return;
    ['dragenter', 'dragover'].forEach(ev => zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.add('dragover'); }));
    ['dragleave', 'drop'].forEach(ev => zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.remove('dragover'); }));
    zone.addEventListener('drop', e => { input.files = e.dataTransfer.files; renderPreview(); });
    zone.addEventListener('click', e => { if (!e.target.closest('button,a')) input.click(); });
    input.addEventListener('change', renderPreview);
    function renderPreview() {
      if (!preview) return;
      preview.innerHTML = '';
      Array.from(input.files).forEach(function (file) {
        if (!file.type.startsWith('image/')) return;
        const img = document.createElement('img');
        img.src = URL.createObjectURL(file);
        img.className = 'h-20 w-20 rounded-xl object-cover ring-1 ring-gray-200';
        preview.appendChild(img);
      });
    }
  });

  /* ---------- Verificador de disponibilidad (admin) ---------- *
   * Marca un botón con: data-check-availability
   *   data-product="#selectProducto"  data-delivery="#inputEntrega"
   *   data-return="#inputDevolucion"   data-exclude="ID|''"
   *   data-result="#contenedorResultado"
   */
  document.querySelectorAll('[data-check-availability]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const q = sel => document.querySelector(btn.getAttribute(sel));
      const product = q('data-product')?.value;
      const delivery = q('data-delivery')?.value;
      const ret = q('data-return')?.value;
      const exclude = btn.getAttribute('data-exclude') || '';
      const box = q('data-result');
      if (!product || !delivery || !ret) { if (box) box.innerHTML = msg('warning', 'Seleccione producto y fechas.'); return; }
      if (box) box.innerHTML = '<div class="text-sm text-gray-400">Verificando…</div>';
      const url = window.LCN_BASE + '/public/api/check-availability.php?product_id=' + encodeURIComponent(product) +
        '&delivery=' + encodeURIComponent(delivery) + '&return=' + encodeURIComponent(ret) +
        (exclude ? '&exclude=' + encodeURIComponent(exclude) : '');
      fetch(url).then(r => r.json()).then(function (d) {
        if (!box) return;
        if (d.available) {
          box.innerHTML = msg('ok', '✓ Disponible para las fechas seleccionadas.');
        } else if (d.conflict) {
          box.innerHTML = msg('error', 'No disponible. Conflicto con ' + (d.conflict.rental_number || 'otro alquiler') +
            ' (' + d.conflict.delivery_date + ' → ' + d.conflict.return_date + ').');
        } else {
          box.innerHTML = msg('error', d.error || 'No disponible para esas fechas.');
        }
      }).catch(() => { if (box) box.innerHTML = msg('error', 'Error al verificar disponibilidad.'); });
    });
  });
  function msg(kind, text) {
    const c = kind === 'ok' ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
      : kind === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-700'
        : 'border-rose-200 bg-rose-50 text-rose-700';
    return '<div class="rounded-xl border ' + c + ' px-3 py-2 text-sm">' + text + '</div>';
  }

  /* ---------- Cálculo en vivo del 50/50 ---------- *
   * input data-payment-total  -> recalcula data-payment-initial / data-payment-remaining
   */
  document.querySelectorAll('[data-payment-total]').forEach(function (input) {
    const pct = parseFloat(input.getAttribute('data-payment-percentage') || '50');
    const ini = document.querySelector(input.getAttribute('data-payment-initial') || '');
    const rem = document.querySelector(input.getAttribute('data-payment-remaining') || '');
    function recalc() {
      const total = parseFloat(input.value || '0');
      const initial = Math.round(total * pct) / 100;
      if (ini) ini.value = initial.toFixed(2);
      if (rem) rem.value = (total - initial).toFixed(2);
    }
    input.addEventListener('input', recalc); recalc();
  });

  /* ---------- Menús de acción por fila (3 puntos) ---------- */
  function closeRowMenus() {
    document.querySelectorAll('[data-row-menu]').forEach(function (m) { m.classList.add('hidden'); });
  }
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-menu-button]');
    if (btn) {
      e.stopPropagation();
      var menu = document.getElementById(btn.getAttribute('data-menu-button'));
      if (!menu) return;
      var willOpen = menu.classList.contains('hidden');
      closeRowMenus();
      if (willOpen) {
        menu.classList.remove('hidden');
        var r = btn.getBoundingClientRect();
        var mw = menu.offsetWidth || 192;
        var mh = menu.offsetHeight || 10;
        var top = r.bottom + 6;
        // si no cabe abajo, abrir hacia arriba
        if (top + mh > window.innerHeight - 8) top = Math.max(8, r.top - mh - 6);
        menu.style.top = top + 'px';
        menu.style.left = Math.max(8, r.right - mw) + 'px';
      }
      return;
    }
    if (!e.target.closest('[data-row-menu]')) closeRowMenus();
  });
  window.addEventListener('scroll', closeRowMenus, true);
  window.addEventListener('resize', closeRowMenus);

  /* ---------- Auto-dismiss flash ---------- */
  setTimeout(() => document.querySelectorAll('[data-flash]').forEach(f => {
    f.style.transition = 'opacity .4s'; f.style.opacity = '0'; setTimeout(() => f.remove(), 400);
  }), 6000);

})();
