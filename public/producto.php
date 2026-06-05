<?php
/**
 * Detalle público de producto — LONDRES Casa de Novias.
 *
 * Lee ?slug= (preferido) o ?id=. Muestra galería, ficha técnica, precios,
 * verificador de disponibilidad y formulario de solicitud de alquiler.
 * NO expone notas internas ni datos de clientes anteriores.
 */
require_once dirname(__DIR__) . '/app/bootstrap.php'; // public/*.php => N=1

/* ------------------------------------------------------------------ *
 *  Localizar el producto (por slug o por id), solo activos
 * ------------------------------------------------------------------ */
$slug = get_param('slug');
$id   = (int) get_param('id');

if ($slug !== '') {
    $product = db_one(
        "SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.slug = :slug AND p.status = 'active'
         LIMIT 1",
        ['slug' => $slug]
    );
} elseif ($id > 0) {
    $product = db_one(
        "SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.id = :id AND p.status = 'active'
         LIMIT 1",
        ['id' => $id]
    );
} else {
    $product = null;
}

/* --- Producto inexistente: 404 elegante dentro del layout público --- */
if (!$product) {
    http_response_code(404);
    $page_title    = 'Producto no encontrado';
    $active_public = 'inventario';
    require LCN_ROOT . '/app/views/layouts/public_header.php';
    ?>
    <section class="mx-auto max-w-3xl px-4 py-24 text-center sm:px-6 lg:px-8">
        <span class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-3xl bg-red-50 text-brand-red">
            <?= icon('warning', 'w-10 h-10') ?>
        </span>
        <h1 class="font-serif text-3xl text-brand-dark">No encontramos esta pieza</h1>
        <p class="mx-auto mt-3 max-w-md text-gray-500">
            El producto que buscas ya no está disponible o el enlace es incorrecto.
            Explora el resto de nuestra colección.
        </p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a href="<?= e(pub_url('inventario.php')) ?>" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                <?= icon('squares', 'w-4 h-4') ?> Ver inventario
            </a>
            <a href="<?= e(pub_url('index.php')) ?>" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                <?= icon('home', 'w-4 h-4') ?> Volver al inicio
            </a>
        </div>
    </section>
    <?php
    require LCN_ROOT . '/app/views/layouts/public_footer.php';
    exit;
}

$pid = (int) $product['id'];

/* ------------------------------------------------------------------ *
 *  Galería: imágenes del producto (principal + adicionales)
 * ------------------------------------------------------------------ */
$images = db_all(
    "SELECT image_path, is_main FROM product_images
     WHERE product_id = :pid
     ORDER BY is_main DESC, sort_order ASC, id ASC",
    ['pid' => $pid]
);

// Reunir rutas únicas, garantizando que la principal vaya primero.
$gallery = [];
if (!empty($product['main_image'])) {
    $gallery[] = $product['main_image'];
}
foreach ($images as $img) {
    if (!empty($img['image_path']) && !in_array($img['image_path'], $gallery, true)) {
        $gallery[] = $img['image_path'];
    }
}
if (empty($gallery)) {
    $gallery[] = null; // upload_url() devolverá el placeholder
}
$mainImage = $gallery[0];

/* ------------------------------------------------------------------ *
 *  Precios y disponibilidad comercial
 * ------------------------------------------------------------------ */
$rentalPrice  = (float) $product['rental_price'];
$initial50    = round($rentalPrice * 0.5, 2);
$salePrice    = $product['sale_price'] !== null ? (float) $product['sale_price'] : null;
$type         = $product['type']; // rental | sale | both
$status       = $product['commercial_status'];

$canRent = in_array($type, ['rental', 'both'], true) && $rentalPrice > 0;
$canSell = in_array($type, ['sale', 'both'], true) && $salePrice !== null && $salePrice > 0;

// El bloque de solicitud solo aplica si la pieza puede alquilarse y no está
// fuera de circulación.
$rentBlocked   = in_array($status, ['rented', 'sold', 'unavailable', 'maintenance'], true);
$showRentBlock = $canRent && !$rentBlocked;

// Ficha técnica (etiquetas legibles para condición)
$conditionLabels = [
    'new' => 'Nuevo', 'excellent' => 'Excelente', 'good' => 'Bueno',
    'repair' => 'En reparación', 'out_of_service' => 'Fuera de servicio',
];
$conditionLabel = $conditionLabels[$product['condition_status']] ?? $product['condition_status'];

/* ------------------------------------------------------------------ *
 *  Productos similares (misma categoría, activos, distintos)
 * ------------------------------------------------------------------ */
$similar = [];
if (!empty($product['category_id'])) {
    $similar = db_all(
        "SELECT p.id, p.name, p.slug, p.main_image, p.rental_price, p.sale_price,
                p.commercial_status, p.featured, c.name AS category_name
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.category_id = :cat AND p.id <> :pid AND p.status = 'active'
         ORDER BY p.featured DESC, p.created_at DESC
         LIMIT 4",
        ['cat' => (int) $product['category_id'], 'pid' => $pid]
    );
}

/* --- Fechas por defecto para el verificador (evento sugerido en 14 días) --- */
$defaultEvent = date('Y-m-d', strtotime('+14 days'));

$page_title    = $product['name'];
$active_public = 'inventario';
require LCN_ROOT . '/app/views/layouts/public_header.php';
?>

<!-- Migas de pan -->
<nav class="mx-auto max-w-7xl px-4 pt-8 text-sm sm:px-6 lg:px-8" aria-label="Migas de pan">
    <ol class="flex flex-wrap items-center gap-1.5 text-gray-400">
        <li><a href="<?= e(pub_url('index.php')) ?>" class="transition hover:text-brand-red">Inicio</a></li>
        <li><?= icon('chevron-right', 'w-3.5 h-3.5') ?></li>
        <li><a href="<?= e(pub_url('inventario.php')) ?>" class="transition hover:text-brand-red">Inventario</a></li>
        <?php if (!empty($product['category_name'])): ?>
            <li><?= icon('chevron-right', 'w-3.5 h-3.5') ?></li>
            <li><a href="<?= e(pub_url('inventario.php?categoria=' . urlencode((string) $product['category_slug']))) ?>" class="transition hover:text-brand-red"><?= e($product['category_name']) ?></a></li>
        <?php endif; ?>
        <li><?= icon('chevron-right', 'w-3.5 h-3.5') ?></li>
        <li class="font-medium text-gray-700"><?= e($product['name']) ?></li>
    </ol>
</nav>

<section class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
    <?php
    // Mensajes flash (p.ej. error de disponibilidad al volver de la solicitud).
    $flashHtml = render_flash();
    if ($flashHtml !== ''): ?>
        <div class="mb-8"><?= $flashHtml ?></div>
    <?php endif; ?>
    <div class="grid gap-10 lg:grid-cols-2 lg:items-start">

        <!-- ============ GALERÍA ============ -->
        <div class="lg:sticky lg:top-24">
            <div class="group relative overflow-hidden rounded-3xl border border-gray-100 bg-gray-50 shadow-card">
                <img id="mainImage"
                     src="<?= e(upload_url($mainImage)) ?>"
                     alt="<?= e($product['name']) ?>"
                     class="aspect-[3/4] w-full cursor-zoom-in object-cover transition duration-700 ease-out group-hover:scale-[1.04]">
                <span class="pointer-events-none absolute bottom-3 right-3 flex h-9 w-9 items-center justify-center rounded-full bg-brand-dark/55 text-white opacity-0 backdrop-blur transition group-hover:opacity-100"><?= icon('search', 'w-4 h-4') ?></span>
            </div>

            <?php if (count($gallery) > 1): ?>
            <div class="mt-4 grid grid-cols-4 gap-3 sm:grid-cols-5">
                <?php foreach ($gallery as $idx => $imgPath): ?>
                    <button type="button"
                            data-thumb
                            data-index="<?= $idx ?>"
                            data-full="<?= e(upload_url($imgPath)) ?>"
                            class="group overflow-hidden rounded-2xl border-2 <?= $idx === 0 ? 'border-brand-red' : 'border-transparent' ?> bg-gray-50 transition hover:border-brand-red/60"
                            aria-label="Ver imagen <?= $idx + 1 ?>">
                        <img src="<?= e(upload_url($imgPath)) ?>"
                             alt="<?= e($product['name']) ?> — vista <?= $idx + 1 ?>"
                             class="aspect-square w-full object-cover">
                    </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- ============ INFORMACIÓN ============ -->
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <?php if (!empty($product['category_name'])): ?>
                    <span class="text-xs font-semibold uppercase tracking-[0.18em] text-brand-red"><?= e($product['category_name']) ?></span>
                <?php endif; ?>
                <?= status_badge($status, 'commercial') ?>
                <?php if (!empty($product['featured'])): ?>
                    <span class="inline-flex items-center gap-1 rounded-full bg-brand-dark/90 px-2.5 py-1 text-xs font-medium text-brand-gold">
                        <?= icon('sparkles', 'w-3.5 h-3.5') ?> Destacado
                    </span>
                <?php endif; ?>
            </div>

            <h1 class="mt-3 font-serif text-3xl leading-tight text-brand-dark sm:text-4xl"><?= e($product['name']) ?></h1>

            <?php if (!empty($product['description'])): ?>
                <p class="mt-4 text-base leading-relaxed text-gray-600"><?= nl2br(e($product['description'])) ?></p>
            <?php endif; ?>

            <!-- Precios -->
            <div class="mt-6 rounded-3xl border border-gray-100 bg-brand-cream/60 p-6 shadow-soft">
                <?php if ($canRent): ?>
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Alquiler</p>
                            <p class="mt-1 font-serif text-3xl text-brand-dark"><?= e(money($rentalPrice)) ?></p>
                        </div>
                        <div class="rounded-2xl bg-white px-4 py-2.5 text-right shadow-soft">
                            <p class="text-xs font-medium text-gray-400">Depósito inicial 50%</p>
                            <p class="mt-0.5 text-lg font-semibold text-brand-red"><?= e(money($initial50)) ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($canSell): ?>
                    <div class="<?= $canRent ? 'mt-4 border-t border-gray-200 pt-4' : '' ?> flex items-end justify-between gap-4">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Precio de venta</p>
                            <p class="mt-1 font-serif text-2xl text-brand-dark"><?= e(money($salePrice)) ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!$canRent && !$canSell): ?>
                    <p class="text-sm text-gray-500">Consulta el precio de esta pieza con nuestro equipo.</p>
                <?php endif; ?>
            </div>

            <!-- Señales de confianza -->
            <div class="mt-4 flex flex-wrap gap-2">
                <?php foreach ([['sparkles', 'Pieza exclusiva'], ['banknotes', 'Reserva con el 50%'], ['heart', 'Asesoría personalizada']] as [$tIc, $tTxt]): ?>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-gray-600 ring-1 ring-gray-100">
                        <span class="text-brand-gold"><?= icon($tIc, 'w-3.5 h-3.5') ?></span> <?= e($tTxt) ?>
                    </span>
                <?php endforeach; ?>
            </div>

            <!-- Ficha técnica -->
            <dl class="mt-6 grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-3">
                <?php
                $specs = [
                    ['Talla',    $product['size']],
                    ['Color',    $product['color']],
                    ['Material', $product['material']],
                    ['Condición', $conditionLabel],
                ];
                foreach ($specs as [$label, $value]):
                    if ($value === null || $value === '') continue;
                ?>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400"><?= e($label) ?></dt>
                        <dd class="mt-1 text-sm font-medium text-gray-800"><?= e($value) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>

            <!-- ============ VERIFICAR DISPONIBILIDAD + SOLICITUD ============ -->
            <?php if ($showRentBlock): ?>
            <div class="mt-8 rounded-3xl border border-gray-100 bg-white p-6 shadow-soft">
                <h2 class="flex items-center gap-2 font-serif text-xl text-brand-dark">
                    <?= icon('calendar', 'w-5 h-5') ?> Verifica la disponibilidad
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Indica la fecha de tu evento; sugeriremos las fechas de entrega y devolución.
                </p>

                <div class="mt-5 grid gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-1">
                        <label for="fEvento" class="lcn-label">Fecha de tu evento</label>
                        <input type="date" id="fEvento" min="<?= e(date('Y-m-d')) ?>" value="<?= e($defaultEvent) ?>" class="lcn-input">
                    </div>
                    <div>
                        <label for="fEntrega" class="lcn-label">Entrega</label>
                        <input type="date" id="fEntrega" min="<?= e(date('Y-m-d')) ?>" class="lcn-input">
                    </div>
                    <div>
                        <label for="fDevolucion" class="lcn-label">Devolución</label>
                        <input type="date" id="fDevolucion" min="<?= e(date('Y-m-d')) ?>" class="lcn-input">
                    </div>
                </div>

                <button type="button"
                        data-public-availability
                        data-product="<?= $pid ?>"
                        data-event="#fEvento"
                        data-delivery="#fEntrega"
                        data-return="#fDevolucion"
                        data-result="#boxDisp"
                        data-form="#formSolic"
                        class="mt-5 inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                    <?= icon('check', 'w-4 h-4') ?> Verificar disponibilidad
                </button>

                <!-- Resultado de la verificación (lo pinta public.js) -->
                <div id="boxDisp" class="mt-4"></div>

                <!-- Formulario de solicitud (oculto hasta verificar disponible) -->
                <form id="formSolic" method="post" action="<?= e(pub_url('solicitud-alquiler.php')) ?>" class="mt-6 hidden border-t border-gray-100 pt-6">
                    <?= csrf_field() ?>
                    <input type="hidden" name="product_id" value="<?= $pid ?>">
                    <!-- Las fechas se sincronizan vía JS al verificar; también se reenvían tal cual -->
                    <input type="hidden" name="event_date"    id="hEvento"     value="">
                    <input type="hidden" name="delivery_date" id="hEntrega"    value="">
                    <input type="hidden" name="return_date"   id="hDevolucion" value="">

                    <h3 class="font-serif text-lg text-brand-dark">Solicita esta pieza</h3>
                    <p class="mt-1 text-sm text-gray-500">Déjanos tus datos y te contactaremos para confirmar la reserva.</p>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="fNombre" class="lcn-label">Nombre completo *</label>
                            <input type="text" id="fNombre" name="full_name" required maxlength="150" class="lcn-input" placeholder="Tu nombre y apellido">
                        </div>
                        <div>
                            <label for="fTelefono" class="lcn-label">Teléfono / WhatsApp *</label>
                            <input type="tel" id="fTelefono" name="phone" required maxlength="40" class="lcn-input" placeholder="+1 809 000 0000">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="fEmail" class="lcn-label">Correo electrónico</label>
                            <input type="email" id="fEmail" name="email" maxlength="150" class="lcn-input" placeholder="tucorreo@email.com">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="fMensaje" class="lcn-label">Mensaje (opcional)</label>
                            <textarea id="fMensaje" name="message" rows="3" class="lcn-input" placeholder="Cuéntanos sobre tu evento…"></textarea>
                        </div>
                    </div>

                    <button type="submit" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-red px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700 sm:w-auto">
                        <?= icon('heart', 'w-4 h-4') ?> Enviar solicitud
                    </button>
                </form>
            </div>
            <?php else: ?>
            <!-- Pieza no disponible para alquiler en este momento -->
            <div class="mt-8 rounded-3xl border border-gray-100 bg-gray-50 p-6 text-center shadow-soft">
                <p class="text-sm font-medium text-gray-600">
                    <?php if (!$canRent && $canSell): ?>
                        Esta pieza está disponible solo para venta. Contáctanos para más información.
                    <?php else: ?>
                        Esta pieza no está disponible para alquiler en este momento.
                    <?php endif; ?>
                </p>
                <?php $waBiz = preg_replace('/\D/', '', (string) setting('whatsapp', '')); ?>
                <?php if ($waBiz): ?>
                    <a href="https://wa.me/<?= e($waBiz) ?>?text=<?= rawurlencode('Hola, me interesa la pieza "' . $product['name'] . '". ¿Pueden darme más información?') ?>"
                       target="_blank" rel="noopener"
                       class="mt-4 inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                        <?= icon('whatsapp', 'w-4 h-4') ?> Consultar por WhatsApp
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (setting('rental_policy')): ?>
            <details class="mt-6 rounded-2xl border border-gray-100 bg-white p-5 shadow-soft">
                <summary class="cursor-pointer list-none font-medium text-gray-800">
                    <span class="inline-flex items-center gap-2"><?= icon('document', 'w-4 h-4 text-brand-red') ?> Política de alquiler</span>
                </summary>
                <p class="mt-3 text-sm leading-relaxed text-gray-500"><?= nl2br(e(setting('rental_policy'))) ?></p>
            </details>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ============ PRODUCTOS SIMILARES ============ -->
<?php if (!empty($similar)): ?>
<section class="mx-auto max-w-7xl px-4 pb-8 sm:px-6 lg:px-8">
    <div class="mb-8 flex items-end justify-between">
        <div>
            <p class="font-script text-2xl text-brand-red">También te puede gustar</p>
            <h2 class="font-serif text-2xl text-brand-dark sm:text-3xl">Piezas similares</h2>
        </div>
        <a href="<?= e(pub_url('inventario.php' . (!empty($product['category_slug']) ? '?categoria=' . urlencode((string) $product['category_slug']) : ''))) ?>"
           class="hidden items-center gap-1 text-sm font-medium text-brand-red transition hover:text-red-700 sm:inline-flex">
            Ver toda la categoría <?= icon('chevron-right', 'w-4 h-4') ?>
        </a>
    </div>
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <?php foreach ($similar as $sp): ?>
            <?= product_card($sp) ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- Lightbox para ver la imagen en grande -->
<div id="lightbox" class="fixed inset-0 z-[60] hidden items-center justify-center bg-brand-dark/90 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-label="Vista ampliada">
    <button type="button" data-lightbox-close class="absolute right-4 top-4 z-10 flex h-11 w-11 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20" aria-label="Cerrar"><?= icon('x', 'w-6 h-6') ?></button>
    <?php if (count($gallery) > 1): ?>
        <button type="button" data-lb-prev class="absolute left-3 top-1/2 z-10 flex h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20 sm:left-6" aria-label="Imagen anterior"><?= icon('chevron-left', 'w-6 h-6') ?></button>
        <button type="button" data-lb-next class="absolute right-3 top-1/2 z-10 flex h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20 sm:right-6" aria-label="Imagen siguiente"><?= icon('chevron-right', 'w-6 h-6') ?></button>
        <span id="lbCounter" class="absolute bottom-5 left-1/2 -translate-x-1/2 rounded-full bg-white/10 px-3 py-1 text-sm font-medium text-white backdrop-blur"></span>
    <?php endif; ?>
    <img id="lightboxImg" src="" alt="<?= e($product['name']) ?>" class="max-h-[90vh] max-w-[92vw] rounded-2xl object-contain shadow-2xl">
</div>

<!-- JS inline mínimo: cambio de imagen principal + sincronía de fechas hacia el form -->
<script>
(function () {
  'use strict';
  var mainImg = document.getElementById('mainImage');
  var IMAGES = <?= json_encode(array_map(fn($g) => upload_url($g), $gallery), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  var thumbs = document.querySelectorAll('[data-thumb]');
  var current = 0;

  // Selecciona la imagen activa (miniaturas + principal + índice del lightbox)
  function setMain(i) {
    if (!IMAGES.length) return;
    current = (i % IMAGES.length + IMAGES.length) % IMAGES.length;
    if (mainImg) mainImg.src = IMAGES[current];
    thumbs.forEach(function (b) {
      var on = parseInt(b.getAttribute('data-index'), 10) === current;
      b.classList.toggle('border-brand-red', on);
      b.classList.toggle('border-transparent', !on);
    });
  }
  thumbs.forEach(function (btn) {
    btn.addEventListener('click', function () { setMain(parseInt(btn.getAttribute('data-index'), 10) || 0); });
  });

  // Sincronizar las fechas hacia los hidden del formulario de solicitud.
  function sync(srcId, dstId) {
    var src = document.getElementById(srcId), dst = document.getElementById(dstId);
    if (!src || !dst) return;
    var copy = function () { dst.value = src.value; };
    src.addEventListener('change', copy); copy();
  }
  sync('fEvento', 'hEvento');
  sync('fEntrega', 'hEntrega');
  sync('fDevolucion', 'hDevolucion');

  var btnCheck = document.querySelector('[data-public-availability]');
  if (btnCheck) {
    btnCheck.addEventListener('click', function () {
      setTimeout(function () {
        var e = document.getElementById('fEvento'), d = document.getElementById('fEntrega'), r = document.getElementById('fDevolucion');
        if (e) document.getElementById('hEvento').value = e.value;
        if (d) document.getElementById('hEntrega').value = d.value;
        if (r) document.getElementById('hDevolucion').value = r.value;
      }, 50);
    });
  }

  // ---------- Lightbox con navegación de galería ----------
  var lb = document.getElementById('lightbox');
  var lbImg = document.getElementById('lightboxImg');
  var lbCounter = document.getElementById('lbCounter');
  function renderLb() {
    if (lbImg) lbImg.src = IMAGES[current];
    if (lbCounter) lbCounter.textContent = (current + 1) + ' / ' + IMAGES.length;
  }
  function openLb() { if (!lb || !IMAGES.length) return; renderLb(); lb.classList.remove('hidden'); lb.classList.add('flex'); document.body.style.overflow = 'hidden'; }
  function closeLb() { if (!lb) return; lb.classList.add('hidden'); lb.classList.remove('flex'); document.body.style.overflow = ''; }
  function navLb(step) { setMain(current + step); renderLb(); }

  if (mainImg) mainImg.addEventListener('click', openLb);
  if (lb) lb.addEventListener('click', function (ev) {
    if (ev.target.closest('[data-lb-prev]')) { navLb(-1); return; }
    if (ev.target.closest('[data-lb-next]')) { navLb(1); return; }
    if (ev.target === lb || ev.target.closest('[data-lightbox-close]')) closeLb();
  });
  document.addEventListener('keydown', function (ev) {
    if (!lb || lb.classList.contains('hidden')) return;
    if (ev.key === 'ArrowLeft') navLb(-1);
    else if (ev.key === 'ArrowRight') navLb(1);
    else if (ev.key === 'Escape') closeLb();
  });
})();
</script>

<?php require LCN_ROOT . '/app/views/layouts/public_footer.php'; ?>
