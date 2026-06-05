<?php
/**
 * Página pública de consulta de disponibilidad.
 * El visitante elige un producto (sólo alquilables: type rental/both y status active)
 * y la fecha de su evento. Mediante el hook data-public-availability (public.js) se
 * consulta el endpoint /public/api/check-availability.php y se muestra el resultado.
 * Si está disponible, se ofrece el enlace a solicitud-alquiler.php?product=ID.
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';  // public/*.php => N=1

/* ------------------------------------------------------------------ *
 *  Productos disponibles para alquiler
 * ------------------------------------------------------------------ */
$products = db_all(
    "SELECT p.id, p.name, p.slug, p.rental_price, p.main_image,
            c.name AS category_name
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.type IN ('rental','both')
       AND p.status = 'active'
     ORDER BY p.name ASC"
);

// Preselección si llega ?product=ID desde otra página.
$preselect = (int) get_param('product', '0');

$page_title    = 'Consultar disponibilidad';
$active_public = 'inventario';
require LCN_ROOT . '/app/views/layouts/public_header.php';
?>

<!-- ====================== HERO ====================== -->
<section class="relative overflow-hidden bg-brand-dark">
    <div class="pointer-events-none absolute inset-0 opacity-30"
         style="background:radial-gradient(55% 55% at 15% 0%, rgba(201,168,106,.25), transparent), radial-gradient(50% 50% at 95% 100%, rgba(200,16,46,.22), transparent)"></div>
    <div class="relative mx-auto max-w-3xl px-4 py-20 text-center sm:px-6 lg:py-24">
        <span class="font-script text-3xl text-brand-gold">Tu fecha, tu vestido</span>
        <h1 class="mt-2 font-serif text-4xl font-semibold text-white sm:text-5xl">Consulta disponibilidad</h1>
        <p class="mx-auto mt-5 max-w-xl text-base leading-relaxed text-gray-300">
            Selecciona la pieza que te encantó y la fecha de tu evento. Te diremos al instante
            si está libre para ti.
        </p>
    </div>
</section>

<!-- ====================== CONSULTA ====================== -->
<section class="bg-brand-cream">
    <div class="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:py-20">
        <?php if (!$products): ?>
            <?= empty_state(
                'No hay piezas disponibles para alquiler',
                'Por el momento no contamos con productos publicados para alquiler. Vuelve pronto.',
                'box',
                '<a href="' . pub_url('inventario.php') . '" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">Ver inventario</a>'
            ) ?>
        <?php else: ?>
            <div class="rounded-3xl border border-gray-100 bg-white p-6 shadow-soft sm:p-8">
                <div class="flex items-center gap-3">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-red-50 text-brand-red"><?= icon('calendar', 'w-6 h-6') ?></span>
                    <div>
                        <h2 class="font-serif text-2xl text-gray-900">Verifica tu fecha</h2>
                        <p class="text-sm text-gray-500">Resultado inmediato, sin compromiso.</p>
                    </div>
                </div>

                <div class="mt-6 grid gap-5 sm:grid-cols-2">
                    <!-- Selector de producto -->
                    <div class="sm:col-span-2">
                        <label class="lcn-label" for="selProducto">Pieza de tu interés</label>
                        <select id="selProducto" class="lcn-input">
                            <option value="">Selecciona un vestido o traje…</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= (int) $p['id'] ?>" <?= $preselect === (int) $p['id'] ? 'selected' : '' ?>>
                                    <?= e($p['name']) ?><?= !empty($p['category_name']) ? ' · ' . e($p['category_name']) : '' ?> — <?= e(money($p['rental_price'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Fecha del evento -->
                    <div class="sm:col-span-2">
                        <label class="lcn-label" for="fEvento">Fecha de tu evento</label>
                        <input type="date" id="fEvento" class="lcn-input" min="<?= date('Y-m-d') ?>">
                        <p class="mt-1.5 text-xs text-gray-400">Reservaremos la pieza un día antes y la recibirás de vuelta dos días después del evento.</p>
                    </div>
                </div>

                <!-- Fechas calculadas (las usa el hook data-public-availability) -->
                <input type="hidden" id="fEntrega">
                <input type="hidden" id="fDevolucion">

                <button type="button"
                        data-public-availability
                        data-product=""
                        data-event="#fEvento"
                        data-delivery="#fEntrega"
                        data-return="#fDevolucion"
                        data-result="#boxDisp"
                        id="btnConsultar"
                        class="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-red px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700 sm:w-auto">
                    <?= icon('search', 'w-4 h-4') ?> Consultar disponibilidad
                </button>

                <!-- Caja de resultado -->
                <div id="boxDisp" class="mt-5"></div>

                <!-- Enlace a solicitud (se muestra al confirmar disponibilidad) -->
                <div id="boxSolicitar" class="mt-4 hidden">
                    <a id="lnkSolicitar" href="#"
                       class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-800 transition hover:border-brand-red hover:text-brand-red sm:w-auto">
                        <?= icon('heart', 'w-4 h-4') ?> Solicitar este alquiler
                    </a>
                </div>
            </div>

            <p class="mt-6 text-center text-sm text-gray-500">
                ¿Aún no eliges? Explora todo nuestro
                <a href="<?= pub_url('inventario.php') ?>" class="font-semibold text-brand-red hover:text-red-700">inventario</a>.
            </p>
        <?php endif; ?>
    </div>
</section>

<?php if ($products): ?>
<script>
/* ------------------------------------------------------------------ *
 *  Sincroniza el producto seleccionado con el botón de disponibilidad
 *  y muestra/oculta el enlace a la solicitud según el resultado.
 *  Reutiliza el hook data-public-availability ya implementado en public.js.
 * ------------------------------------------------------------------ */
(function () {
  'use strict';
  var sel  = document.getElementById('selProducto');
  var btn  = document.getElementById('btnConsultar');
  var box  = document.getElementById('boxDisp');
  var boxSol = document.getElementById('boxSolicitar');
  var lnk  = document.getElementById('lnkSolicitar');
  var base = window.LCN_BASE || '';

  // Mantén actualizado data-product con la selección del usuario.
  function syncProduct() {
    btn.setAttribute('data-product', sel.value || '');
    boxSol.classList.add('hidden');     // oculta el botón de solicitar al cambiar de pieza
  }
  sel.addEventListener('change', syncProduct);
  syncProduct();

  // Tras hacer clic, public.js pinta #boxDisp; observamos cambios para
  // mostrar el enlace a la solicitud sólo cuando aparezca el mensaje de éxito.
  var observer = new MutationObserver(function () {
    var txt = box.textContent || '';
    if (sel.value && txt.indexOf('Disponible') !== -1) {
      lnk.setAttribute('href', base + '/public/solicitud-alquiler.php?product=' + encodeURIComponent(sel.value));
      boxSol.classList.remove('hidden');
    } else {
      boxSol.classList.add('hidden');
    }
  });
  observer.observe(box, { childList: true, subtree: true });
})();
</script>
<?php endif; ?>

<?php require LCN_ROOT . '/app/views/layouts/public_footer.php'; ?>
