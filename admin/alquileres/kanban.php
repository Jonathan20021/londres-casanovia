<?php
/**
 * Tablero Kanban / Pipeline de alquileres — LONDRES Casa de Novias
 * Arrastra las tarjetas entre columnas para cambiar el estado del alquiler.
 *
 * admin/alquileres/kanban.php (N=2) · Permiso: rentals.manage
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('rentals.manage');

/* Columnas del pipeline */
$columns = [
    'pending'        => 'Solicitud',
    'reserved'       => 'Reservado',
    'confirmed'      => 'Confirmado',
    'delivered'      => 'Entregado',
    'pending_return' => 'Pend. devolución',
    'returned'       => 'Devuelto',
];
$colDot = [
    'pending' => 'bg-gray-400', 'reserved' => 'bg-sky-500', 'confirmed' => 'bg-indigo-500',
    'delivered' => 'bg-amber-500', 'pending_return' => 'bg-rose-500', 'returned' => 'bg-emerald-500',
];

/* Alquileres activos (excluye cancelados; limita devueltos recientes) */
$rentals = db_all(
    "SELECT r.id, r.rental_number, r.event_date, r.delivery_date, r.delivery_time, r.return_date,
            r.rental_status, r.total_amount, r.remaining_balance,
            c.full_name AS customer_name, p.name AS product_name, p.main_image,
            (SELECT COUNT(*) FROM rental_items ric WHERE ric.rental_id = r.id) AS product_count,
            (SELECT COUNT(*) FROM rental_items ria
              WHERE ria.rental_id = r.id AND ria.needs_alteration = 1
                AND ria.alteration_status = 'pending') AS alterations_pending
     FROM rentals r
     JOIN customers c ON c.id = r.customer_id
     JOIN products  p ON p.id = r.product_id
     WHERE r.rental_status <> 'cancelled'
       AND (r.rental_status <> 'returned' OR r.return_date >= DATE_SUB(CURDATE(), INTERVAL 45 DAY))
     ORDER BY r.delivery_date ASC"
);

/* Bucketing por estado (overdue cae en 'pend. devolución') */
$board = array_fill_keys(array_keys($columns), []);
foreach ($rentals as $r) {
    $st = $r['rental_status'] === 'overdue' ? 'pending_return' : $r['rental_status'];
    if (isset($board[$st])) $board[$st][] = $r;
}

/* Piezas pendientes de modificar (ruedo, cintura…) — columna propia del tablero */
$alterations = alteration_items('pending');

$page_title    = 'Tablero de alquileres';
$page_subtitle = 'Pipeline · arrastra las tarjetas para cambiar el estado';
$active        = 'kanban';
$header_actions =
    '<a href="' . admin_url('alquileres/index.php') . '" class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">' . icon('menu', 'w-4 h-4') . ' Vista lista</a>'
    . '<a href="' . admin_url('alquileres/crear.php') . '" class="inline-flex items-center gap-2 rounded-full bg-brand-red px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">' . icon('plus', 'w-4 h-4') . ' Nuevo alquiler</a>';

require LCN_ROOT . '/app/views/layouts/admin_header.php';

/** Render de una tarjeta de alquiler. */
function kanban_card(array $r): string
{
    $overdue = in_array($r['rental_status'], ['delivered', 'pending_return'], true)
        && strtotime($r['return_date']) < strtotime(date('Y-m-d'));
    $balance = (float) $r['remaining_balance'];
    ob_start(); ?>
    <div class="kanban-card cursor-grab rounded-xl border border-gray-100 bg-white p-3.5 shadow-soft transition hover:shadow-card active:cursor-grabbing" data-id="<?= (int) $r['id'] ?>">
        <div class="flex items-center justify-between">
            <a href="<?= admin_url('alquileres/ver.php?id=' . (int) $r['id']) ?>" class="text-xs font-semibold text-brand-red hover:underline"><?= e($r['rental_number']) ?></a>
            <div class="flex items-center gap-1">
                <?php if ((int) $r['alterations_pending'] > 0): ?>
                    <span title="<?= (int) $r['alterations_pending'] ?> pieza(s) por modificar"
                          class="inline-flex items-center gap-0.5 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700">
                        <?= icon('scissors', 'w-3 h-3') ?> <?= (int) $r['alterations_pending'] ?>
                    </span>
                <?php endif; ?>
                <?php if ($overdue): ?><span class="rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-semibold text-rose-600">Vencido</span><?php endif; ?>
            </div>
        </div>
        <div class="mt-2 flex items-center gap-2.5">
            <img src="<?= e(upload_url($r['main_image'])) ?>" alt="" class="h-10 w-10 shrink-0 rounded-lg object-cover ring-1 ring-gray-100">
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-gray-900"><?= e($r['customer_name']) ?></p>
                <p class="truncate text-xs text-gray-500"><?= e($r['product_name']) ?><?= (int) $r['product_count'] > 1 ? ' +' . ((int) $r['product_count'] - 1) : '' ?></p>
            </div>
        </div>
        <div class="mt-3 flex items-center justify-between border-t border-gray-50 pt-2.5 text-[11px] text-gray-400">
            <span class="inline-flex items-center gap-1"><?= icon('truck', 'w-3.5 h-3.5') ?>
                <?= e(format_date($r['delivery_date'], 'd/m')) ?><?= format_time($r['delivery_time'], 'g:i A') !== '' ? ' ' . e(format_time($r['delivery_time'], 'g:i A')) : '' ?>
            </span>
            <span class="inline-flex items-center gap-1"><?= icon('return', 'w-3.5 h-3.5') ?> <?= e(format_date($r['return_date'], 'd/m')) ?></span>
            <?php if ($balance > 0.009): ?>
                <span class="font-semibold text-brand-red"><?= e(money($balance)) ?></span>
            <?php else: ?>
                <span class="font-semibold text-emerald-600">Pagado</span>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/** Render de una pieza pendiente de modificar. */
function alteration_card(array $a): string
{
    $urgent = strtotime((string) $a['delivery_date']) <= strtotime('+3 days');
    ob_start(); ?>
    <div class="rounded-xl border border-amber-200 bg-white p-3.5 shadow-soft transition hover:shadow-card"
         data-alteration-card data-item="<?= (int) $a['rental_item_id'] ?>">
        <div class="flex items-center justify-between">
            <a href="<?= admin_url('alquileres/ver.php?id=' . (int) $a['rental_id']) ?>" class="text-xs font-semibold text-brand-red hover:underline"><?= e($a['rental_number']) ?></a>
            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold <?= $urgent ? 'bg-rose-50 text-rose-600' : 'bg-amber-50 text-amber-700' ?>">
                Entrega <?= e(format_date($a['delivery_date'], 'd/m')) ?><?= format_time($a['delivery_time']) !== '' ? ' · ' . e(format_time($a['delivery_time'])) : '' ?>
            </span>
        </div>
        <div class="mt-2 flex items-center gap-2.5">
            <img src="<?= e(upload_url($a['main_image'])) ?>" alt="" class="h-10 w-10 shrink-0 rounded-lg object-cover ring-1 ring-gray-100">
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-gray-900"><?= e($a['product_name']) ?></p>
                <p class="truncate text-xs text-gray-500"><?= e($a['customer_name']) ?><?= $a['size'] ? ' · Talla ' . e($a['size']) : '' ?></p>
            </div>
        </div>
        <?php if (!empty($a['alteration_notes'])): ?>
            <p class="mt-2.5 rounded-lg bg-amber-50 px-2.5 py-2 text-[11px] leading-snug text-amber-900"><?= e($a['alteration_notes']) ?></p>
        <?php else: ?>
            <p class="mt-2.5 text-[11px] italic text-gray-400">Sin nota de taller.</p>
        <?php endif; ?>
        <button type="button" data-alteration-done
                class="mt-3 inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700">
            <?= icon('check', 'w-3.5 h-3.5') ?> Marcar como lista
        </button>
    </div>
    <?php
    return ob_get_clean();
}
?>

<div class="flex gap-4 overflow-x-auto pb-4">
    <!-- Columna de taller: piezas pendientes de modificar (no es un estado del alquiler) -->
    <section class="flex w-72 shrink-0 flex-col">
        <header class="mb-3 flex items-center justify-between rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-2.5 shadow-soft">
            <div class="flex items-center gap-2 text-amber-800">
                <?= icon('scissors', 'w-4 h-4') ?>
                <span class="text-sm font-semibold">Por modificar</span>
            </div>
            <span data-alteration-count class="inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-brand-gold px-2 text-xs font-semibold text-white"><?= count($alterations) ?></span>
        </header>
        <div id="alterationCol" class="flex-1 space-y-3 rounded-2xl bg-amber-50/50 p-2.5 ring-1 ring-amber-100" style="min-height:120px">
            <?php foreach ($alterations as $a) echo alteration_card($a); ?>
            <p id="alterationEmpty" class="px-2 py-6 text-center text-xs text-gray-400 <?= $alterations ? 'hidden' : '' ?>">
                Ninguna pieza pendiente de modificar.
            </p>
        </div>
    </section>

    <?php foreach ($columns as $key => $label):
        $items = $board[$key]; ?>
        <section class="flex w-72 shrink-0 flex-col">
            <header class="mb-3 flex items-center justify-between rounded-xl bg-white px-3.5 py-2.5 shadow-soft">
                <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full <?= $colDot[$key] ?>"></span>
                    <span class="text-sm font-semibold text-gray-800"><?= e($label) ?></span>
                </div>
                <span data-count class="inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-gray-100 px-2 text-xs font-semibold text-gray-600"><?= count($items) ?></span>
            </header>
            <div class="kanban-col flex-1 space-y-3 rounded-2xl bg-gray-100/60 p-2.5" data-status="<?= e($key) ?>" style="min-height:120px">
                <?php foreach ($items as $r) echo kanban_card($r); ?>
                <?php if (!$items): ?><p class="px-2 py-6 text-center text-xs text-gray-400">Sin alquileres</p><?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>

<p class="mt-2 text-xs text-gray-400">Consejo: arrastra una tarjeta a otra columna para actualizar su estado. No podrás marcar “Entregado” si hay saldo pendiente sin autorización.</p>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<?php
ob_start(); ?>
<script>
(function () {
  if (typeof Sortable === 'undefined') return;
  var CSRF = <?= json_encode(csrf_token()) ?>;

  function recount() {
    document.querySelectorAll('.kanban-col').forEach(function (col) {
      var n = col.querySelectorAll('.kanban-card').length;
      var badge = col.closest('section').querySelector('[data-count]');
      if (badge) badge.textContent = n;
      var empty = col.querySelector('p');
      if (empty && n > 0) empty.remove();
    });
  }

  document.querySelectorAll('.kanban-col').forEach(function (col) {
    new Sortable(col, {
      group: 'rentals', animation: 160, ghostClass: 'opacity-40',
      onEnd: function (evt) {
        if (evt.to === evt.from) return;
        var id = evt.item.getAttribute('data-id');
        var status = evt.to.getAttribute('data-status');
        var fromCol = evt.from, oldIndex = evt.oldIndex;
        var body = new URLSearchParams({ rental_id: id, status: status });
        fetch(window.LCN_BASE + '/admin/alquileres/cambiar-estado.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': CSRF },
          body: body.toString()
        }).then(function (r) { return r.json(); }).then(function (d) {
          if (d.ok) { recount(); }
          else {
            // revertir
            var ref = fromCol.children[oldIndex] || null;
            fromCol.insertBefore(evt.item, ref);
            recount();
            alert(d.error || 'No se pudo cambiar el estado.');
          }
        }).catch(function () {
          var ref = fromCol.children[oldIndex] || null;
          fromCol.insertBefore(evt.item, ref);
          recount();
          alert('Error de conexión al cambiar el estado.');
        });
      }
    });
  });

  /* ---------- Piezas por modificar: marcar como listas ---------- */
  var alterCol   = document.getElementById('alterationCol');
  var alterCount = document.querySelector('[data-alteration-count]');
  var alterEmpty = document.getElementById('alterationEmpty');

  function refreshAlterationCount(pending) {
    var n = (typeof pending === 'number')
      ? pending
      : alterCol.querySelectorAll('[data-alteration-card]').length;
    if (alterCount) alterCount.textContent = n;
    if (alterEmpty) alterEmpty.classList.toggle('hidden', n > 0);
  }

  if (alterCol) {
    alterCol.addEventListener('click', function (event) {
      var button = event.target.closest('[data-alteration-done]');
      if (!button) return;
      var card = button.closest('[data-alteration-card]');
      button.disabled = true;
      button.textContent = 'Guardando…';

      fetch(window.LCN_BASE + '/admin/alquileres/modificacion.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': CSRF },
        body: new URLSearchParams({ item_id: card.getAttribute('data-item'), action: 'done' }).toString()
      }).then(function (r) { return r.json(); }).then(function (d) {
        if (d.ok) {
          card.remove();
          refreshAlterationCount(d.pending_count);
        } else {
          button.disabled = false;
          button.textContent = 'Marcar como lista';
          alert(d.message || 'No se pudo marcar la modificación.');
        }
      }).catch(function () {
        button.disabled = false;
        button.textContent = 'Marcar como lista';
        alert('Error de conexión al marcar la modificación.');
      });
    });
  }
})();
</script>
<?php
$page_scripts = ob_get_clean();
require LCN_ROOT . '/app/views/layouts/admin_footer.php';
?>
