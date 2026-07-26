<?php
/**
 * LONDRES Casa de Novias — Landing pública (página de inicio).
 * Premium, emocional y orientada a la conversión.
 *
 * Reusa por completo el núcleo: helpers db_*, ui (product_card, icon),
 * settings del negocio y el layout público. No define nada nuevo del núcleo.
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';   // public/*.php => N=1

/* ------------------------------------------------------------------ *
 *  Datos del negocio para CTAs (WhatsApp solo dígitos)
 * ------------------------------------------------------------------ */
$wa       = preg_replace('/\D/', '', (string) setting('whatsapp', ''));
$waMsg    = rawurlencode('Hola, me gustaría reservar una cita en LONDRES Casa de Novias.');
$waLink   = $wa ? 'https://wa.me/' . $wa . '?text=' . $waMsg : pub_url('contacto.php');

/* ------------------------------------------------------------------ *
 *  Categorías activas (tira de navegación visual)
 * ------------------------------------------------------------------ */
$categories = db_all(
    "SELECT c.id, c.name, c.slug, c.image, COUNT(p.id) AS total
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id AND p.status = 'active'
     WHERE c.status = 'active'
     GROUP BY c.id, c.name, c.slug, c.image
     ORDER BY c.name ASC"
);

/* Iconos por categoría (estético, no depende de datos) */
$categoryIcon = function (string $name): string {
    $n = mb_strtolower($name);
    if (str_contains($n, 'novia'))    return 'sparkles';
    if (str_contains($n, 'gala'))     return 'heart';
    if (str_contains($n, 'traje'))    return 'bag';
    if (str_contains($n, 'velo'))     return 'photo';
    if (str_contains($n, 'corona'))   return 'sparkles';
    return 'tag';
};

/* ------------------------------------------------------------------ *
 *  Destacados / Nuevas llegadas
 *  product_card() requiere p.* + category_name.
 * ------------------------------------------------------------------ */
$featured = db_all(
    "SELECT p.*, c.name AS category_name
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.status = 'active' AND p.featured = 1
     ORDER BY p.created_at DESC, p.id DESC
     LIMIT 8"
);

// Si no hay suficientes destacados, completar con las piezas más recientes.
if (count($featured) < 4) {
    $featured = db_all(
        "SELECT p.*, c.name AS category_name
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.status = 'active'
         ORDER BY p.featured DESC, p.created_at DESC, p.id DESC
         LIMIT 8"
    );
}

/* ------------------------------------------------------------------ *
 *  Más alquilados (piezas con mayor número de alquileres)
 * ------------------------------------------------------------------ */
/* Cuenta por rental_items: un alquiler puede llevar varias piezas y todas
   deben sumar, no solo la principal (rentals.product_id). */
$mostRented = db_all(
    "SELECT p.*, c.name AS category_name, COUNT(ri.id) AS rentals_count
     FROM products p
     INNER JOIN rental_items ri ON ri.product_id = p.id
     INNER JOIN rentals r ON r.id = ri.rental_id
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.status = 'active' AND r.rental_status <> 'cancelled'
     GROUP BY p.id
     ORDER BY rentals_count DESC, p.created_at DESC
     LIMIT 4"
);

/* Precio de alquiler más bajo (para el distintivo del hero) */
$minRental = (float) db_value("SELECT MIN(rental_price) FROM products WHERE status = 'active' AND rental_price > 0");

/* Piezas para el escaparate del hero (imágenes reales) */
$heroPrimary   = db_one("SELECT id, name, slug, main_image, rental_price FROM products WHERE main_image IS NOT NULL AND status='active' AND category_id = 1 ORDER BY featured DESC, id ASC LIMIT 1")
              ?: db_one("SELECT id, name, slug, main_image, rental_price FROM products WHERE main_image IS NOT NULL AND status='active' ORDER BY featured DESC, id ASC LIMIT 1");
$heroSecondary = db_one("SELECT id, name, slug, main_image FROM products WHERE main_image IS NOT NULL AND status='active' AND id <> :id ORDER BY featured DESC, id DESC LIMIT 1", ['id' => (int)($heroPrimary['id'] ?? 0)]);

$page_title    = 'Inicio';
$active_public = 'home';
require LCN_ROOT . '/app/views/layouts/public_header.php';
?>

<!-- ============================================================= -->
<!--  HERO                                                          -->
<!-- ============================================================= -->
<section class="relative isolate overflow-hidden bg-brand-dark">
    <!-- Fondo en capas -->
    <div class="absolute inset-0 -z-20 bg-gradient-to-br from-[#0B0B0C] via-[#15090c] to-[#3a0d16]"></div>
    <div class="pointer-events-none absolute -right-40 -top-24 -z-10 h-[42rem] w-[42rem] rounded-full bg-brand-red/25 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-48 -left-40 -z-10 h-[36rem] w-[36rem] rounded-full bg-brand-gold/15 blur-3xl"></div>
    <!-- Textura de líneas sutil -->
    <div class="pointer-events-none absolute inset-0 -z-10 opacity-[0.04]" style="background-image:radial-gradient(circle,#fff 1px,transparent 1px);background-size:26px 26px"></div>

    <div class="mx-auto grid max-w-7xl items-center gap-14 px-4 py-20 sm:px-6 lg:grid-cols-[1.05fr_0.95fr] lg:gap-8 lg:py-28 lg:px-8">
        <!-- Texto -->
        <div class="relative z-10 text-center lg:text-left">
            <p class="mb-6 inline-flex items-center gap-2 rounded-full border border-brand-gold/30 bg-white/5 px-4 py-1.5 text-xs font-medium uppercase tracking-[0.2em] text-brand-champ backdrop-blur">
                <?= icon('sparkles', 'w-4 h-4 text-brand-gold') ?>
                Casa de novias · est. Londres
            </p>
            <h1 class="hero-title font-display text-6xl font-medium leading-[0.98] text-white sm:text-7xl lg:text-[5.5rem]">
                Tu día perfecto
                <span class="mt-1 block">comienza <span class="font-script text-[0.92em] font-normal text-brand-gold">aquí</span></span>
            </h1>
            <p class="mx-auto mt-7 max-w-lg text-base leading-relaxed text-gray-300 lg:mx-0 lg:text-lg">
                Vestidos de novia, vestidos de gala, trajes y accesorios cuidadosamente
                seleccionados para que luzcas inolvidable en el día más importante de tu vida.
            </p>
            <div class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row lg:justify-start">
                <a href="<?= pub_url('inventario.php') ?>"
                   class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-brand-red px-7 py-3.5 text-sm font-semibold text-white shadow-card transition hover:bg-red-700 sm:w-auto">
                    <?= icon('squares', 'w-5 h-5') ?> Ver inventario
                </a>
                <a href="<?= e($waLink) ?>" target="_blank" rel="noopener"
                   class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-white/25 bg-white/5 px-7 py-3.5 text-sm font-semibold text-white backdrop-blur transition hover:bg-white/15 sm:w-auto">
                    <?= icon('whatsapp', 'w-5 h-5') ?> Reservar cita
                </a>
            </div>

            <!-- Prueba social -->
            <div class="mt-10 flex flex-wrap items-center justify-center gap-x-8 gap-y-3 border-t border-white/10 pt-7 text-sm text-gray-400 lg:justify-start">
                <span class="flex items-center gap-2"><span class="text-brand-gold"><?= icon('heart', 'w-4 h-4') ?></span> Piezas únicas</span>
                <span class="flex items-center gap-2"><span class="text-brand-gold"><?= icon('check', 'w-4 h-4') ?></span> Reserva con el 50%</span>
                <span class="flex items-center gap-2"><span class="text-brand-gold"><?= icon('user', 'w-4 h-4') ?></span> Asesoría personalizada</span>
            </div>
        </div>

        <!-- Escaparate de imágenes reales -->
        <div class="relative mx-auto w-full max-w-md">
            <!-- marco dorado -->
            <div class="pointer-events-none absolute -inset-3 -z-10 rounded-[2.8rem] rounded-t-[13rem] border border-brand-gold/30"></div>
            <?php if (!empty($heroPrimary['main_image'])): ?>
            <a href="<?= pub_url('producto.php?slug=' . e($heroPrimary['slug'])) ?>" class="group relative block overflow-hidden rounded-[2.5rem] rounded-t-[12rem] border border-white/10 shadow-2xl ring-1 ring-brand-gold/20">
                <img src="<?= e(upload_url($heroPrimary['main_image'])) ?>" alt="<?= e($heroPrimary['name']) ?>"
                     class="aspect-[3/4] w-full object-cover transition duration-700 group-hover:scale-[1.03]">
                <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/55 to-transparent p-5 text-left">
                    <span class="font-script text-xl text-brand-gold">Colección novias</span>
                    <span class="block font-serif text-lg text-white"><?= e($heroPrimary['name']) ?></span>
                </span>
            </a>
            <?php endif; ?>

            <!-- pieza secundaria flotante -->
            <?php if (!empty($heroSecondary['main_image'])): ?>
            <a href="<?= pub_url('producto.php?slug=' . e($heroSecondary['slug'])) ?>" class="absolute -bottom-8 -left-6 hidden w-36 overflow-hidden rounded-3xl border-4 border-brand-dark shadow-2xl transition hover:-translate-y-1 sm:block lg:-left-12 lg:w-40">
                <img src="<?= e(upload_url($heroSecondary['main_image'])) ?>" alt="<?= e($heroSecondary['name']) ?>" class="aspect-[3/4] w-full object-cover">
            </a>
            <?php endif; ?>

            <!-- distintivo de precio -->
            <?php if ($minRental > 0): ?>
            <div class="absolute -right-3 top-12 rounded-2xl bg-white/95 px-4 py-3 text-center shadow-xl backdrop-blur sm:-right-6">
                <p class="text-[10px] font-medium uppercase tracking-wide text-gray-400">Alquiler desde</p>
                <p class="font-serif text-lg font-semibold text-brand-dark"><?= e(money($minRental)) ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- transición inferior -->
    <div class="h-10 bg-gradient-to-b from-transparent to-white"></div>
</section>

<!-- ============================================================= -->
<!--  TIRA DE CATEGORÍAS                                            -->
<!-- ============================================================= -->
<?php if ($categories): ?>
<section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
    <div data-reveal class="mb-10 text-center">
        <p class="font-script text-2xl text-brand-red">Explora</p>
        <h2 class="mt-1 font-display text-4xl font-medium text-brand-dark sm:text-5xl">Nuestras colecciones</h2>
        <p class="mx-auto mt-3 max-w-xl text-gray-500">Encuentra la categoría perfecta para tu evento especial.</p>
    </div>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
        <?php foreach ($categories as $cat): ?>
            <a data-reveal href="<?= pub_url('inventario.php?category_id=' . (int) $cat['id']) ?>"
               class="group relative block aspect-[3/4] overflow-hidden rounded-3xl shadow-soft transition duration-300 hover:-translate-y-1 hover:shadow-card">
                <?php if (!empty($cat['image'])): ?>
                    <img src="<?= e(upload_url($cat['image'])) ?>" alt="<?= e($cat['name']) ?>" loading="lazy"
                         class="absolute inset-0 h-full w-full object-cover transition duration-700 ease-out group-hover:scale-105">
                    <span class="absolute inset-0 bg-gradient-to-t from-brand-dark/85 via-brand-dark/15 to-transparent"></span>
                <?php else: ?>
                    <span class="absolute inset-0 flex items-center justify-center bg-brand-cream text-brand-red"><?= icon($categoryIcon($cat['name']), 'w-10 h-10') ?></span>
                <?php endif; ?>
                <span class="absolute inset-x-0 bottom-0 p-4">
                    <span class="block font-serif text-base font-semibold leading-tight text-white drop-shadow"><?= e($cat['name']) ?></span>
                    <span class="mt-0.5 block text-xs text-white/75"><?= (int) $cat['total'] ?> pieza<?= (int) $cat['total'] === 1 ? '' : 's' ?></span>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ============================================================= -->
<!--  NUEVAS LLEGADAS / DESTACADOS                                 -->
<!-- ============================================================= -->
<?php if ($featured): ?>
<section class="bg-brand-cream/60 py-20">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mb-10 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="font-script text-2xl text-brand-red">Recién llegados</p>
                <h2 class="mt-1 font-display text-4xl font-medium text-brand-dark sm:text-5xl">Piezas destacadas</h2>
                <p class="mt-3 max-w-xl text-gray-500">Una selección de nuestras piezas más admiradas, listas para hacerte brillar.</p>
            </div>
            <a href="<?= pub_url('inventario.php?sort=destacados') ?>"
               class="inline-flex items-center gap-2 self-start rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 sm:self-auto">
                Ver todo el inventario <?= icon('chevron-right', 'w-4 h-4') ?>
            </a>
        </div>

        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <?php foreach ($featured as $p): ?>
                <?= product_card($p) ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================================================= -->
<!--  MÁS ALQUILADOS                                               -->
<!-- ============================================================= -->
<?php if ($mostRented): ?>
<section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
    <div data-reveal class="mb-10 text-center">
        <p class="font-script text-2xl text-brand-red">Favoritos</p>
        <h2 class="mt-1 font-display text-4xl font-medium text-brand-dark sm:text-5xl">Los más alquilados</h2>
        <p class="mx-auto mt-3 max-w-xl text-gray-500">Las piezas que más enamoran a nuestras novias e invitadas.</p>
    </div>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <?php foreach ($mostRented as $p): ?>
            <?= product_card($p) ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ============================================================= -->
<!--  LA EXPERIENCIA LONDRES (editorial asimétrico)                -->
<!-- ============================================================= -->
<section class="bg-white py-20 sm:py-24">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
            <!-- Imagen editorial -->
            <div data-reveal class="relative order-last lg:order-first">
                <div class="overflow-hidden rounded-[2.5rem] rounded-tr-[9rem] shadow-card ring-1 ring-brand-champ/40">
                    <img src="<?= e(upload_url($heroPrimary['main_image'] ?? null)) ?>" alt="Pieza de la colección LONDRES" class="aspect-[4/5] w-full object-cover">
                </div>
                <div class="absolute -bottom-5 -right-3 hidden rounded-2xl bg-brand-dark px-5 py-4 text-center shadow-2xl sm:block">
                    <p class="font-script text-2xl leading-none text-brand-gold">Hecho para ti</p>
                    <p class="mt-1 text-xs text-gray-300">Atención uno a uno</p>
                </div>
            </div>
            <!-- Lista de beneficios -->
            <div data-reveal>
                <p class="font-script text-2xl text-brand-red">La experiencia LONDRES</p>
                <h2 class="mt-1 font-display text-4xl font-medium leading-[1.05] text-brand-dark sm:text-5xl">Cuidamos cada detalle de tu día</h2>
                <p class="mt-4 max-w-md leading-relaxed text-gray-500">Más que alquilar una pieza, te acompañamos en una de las decisiones más especiales. Así trabajamos contigo:</p>
                <div class="mt-8 divide-y divide-gray-100">
                    <?php
                    $trust = [
                        ['sparkles',  'Piezas exclusivas y únicas', 'Selección curada de vestidos y trajes. Lucirás distinta a cualquier otra invitada.'],
                        ['banknotes', 'Reserva con solo el 50%',    'Aseguras tu pieza con el depósito inicial; el saldo lo pagas al retirarla.'],
                        ['heart',     'Asesoría personalizada',     'Te ayudamos a elegir estilo, talla y accesorios ideales para tu evento.'],
                        ['check',     'Calidad garantizada',        'Cada pieza se revisa y prepara con esmero antes de llegar a tus manos.'],
                    ];
                    foreach ($trust as [$ic, $title, $desc]): ?>
                        <div class="flex items-start gap-4 py-5">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand-cream text-brand-red ring-1 ring-brand-champ/60"><?= icon($ic, 'w-5 h-5') ?></span>
                            <div>
                                <h3 class="font-serif text-lg font-semibold text-brand-dark"><?= e($title) ?></h3>
                                <p class="mt-1 text-sm leading-relaxed text-gray-500"><?= e($desc) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================= -->
<!--  CÓMO FUNCIONA                                                -->
<!-- ============================================================= -->
<section class="bg-brand-cream/50 py-20">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div data-reveal class="mb-12 text-center">
            <p class="font-script text-2xl text-brand-red">Sencillo y transparente</p>
            <h2 class="mt-1 font-display text-4xl font-medium text-brand-dark sm:text-5xl">Cómo funciona el alquiler</h2>
            <p class="mx-auto mt-3 max-w-xl text-gray-500">Tres pasos para lucir impecable, sin complicaciones.</p>
        </div>
        <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
            <?php
            $steps = [
                ['01', 'tag',       'Elige tu pieza',        'Explora el inventario, filtra por categoría, talla o color y verifica la disponibilidad para la fecha de tu evento.'],
                ['02', 'banknotes', 'Reserva con el 50%',    'Confirma tu reserva abonando solo el 50% del valor. El saldo restante lo pagas al retirar la pieza.'],
                ['03', 'sparkles',  'Disfruta y devuelve',   'Recoge tu pieza lista para brillar y devuélvela en la fecha acordada. Nosotros nos encargamos del resto.'],
            ];
            foreach ($steps as $i => [$num, $ic, $title, $desc]): ?>
                <div data-reveal class="relative">
                    <div class="flex items-center gap-4">
                        <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white text-brand-red shadow-soft ring-1 ring-brand-champ"><?= icon($ic, 'w-7 h-7') ?></span>
                        <span class="font-display text-5xl font-medium text-brand-champ"><?= $num ?></span>
                    </div>
                    <h3 class="mt-5 font-serif text-xl font-semibold text-brand-dark"><?= e($title) ?></h3>
                    <p class="mt-2 text-sm leading-relaxed text-gray-500"><?= e($desc) ?></p>
                    <?php if ($i < 2): ?><span class="absolute -right-4 top-7 hidden text-brand-champ md:block"><?= icon('chevron-right', 'w-7 h-7') ?></span><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================================================= -->
<!--  CTA FINAL                                                    -->
<!-- ============================================================= -->
<section class="mx-auto max-w-7xl px-4 pb-4 sm:px-6 lg:px-8">
    <div class="relative isolate overflow-hidden rounded-[2.5rem] bg-gradient-to-br from-brand-dark via-brand-ink to-brand-red px-6 py-16 text-center shadow-card sm:px-12 sm:py-20">
        <div class="pointer-events-none absolute -right-20 -top-20 -z-10 h-72 w-72 rounded-full bg-brand-gold/20 blur-3xl"></div>
        <p class="font-script text-3xl text-brand-gold">Te esperamos</p>
        <h2 class="mt-2 font-display text-4xl font-medium text-white sm:text-5xl">¿Lista para encontrar tu pieza soñada?</h2>
        <p class="mx-auto mt-4 max-w-xl text-gray-200">
            Reserva tu cita y déjate asesorar por nuestro equipo. Haremos de tu evento un momento perfecto.
        </p>
        <div class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
            <a href="<?= e($waLink) ?>" target="_blank" rel="noopener"
               class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-6 py-3.5 text-sm font-semibold text-brand-dark shadow-sm transition hover:bg-brand-cream">
                <?= icon('whatsapp', 'w-5 h-5 text-brand-red') ?> Reservar cita
            </a>
            <a href="<?= pub_url('inventario.php') ?>"
               class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/30 px-6 py-3.5 text-sm font-semibold text-white transition hover:bg-white/10">
                <?= icon('squares', 'w-5 h-5') ?> Explorar inventario
            </a>
        </div>
    </div>
</section>

<?php require LCN_ROOT . '/app/views/layouts/public_footer.php'; ?>
