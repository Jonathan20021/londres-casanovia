<?php
/**
 * Tallas por unidad — tarjeta reutilizable (crear/editar producto).
 * LONDRES Casa de Novias
 *
 * Del mismo traje (mismo color, mismo diseño) suele haber varias tallas.
 * Esta tarjeta pinta una fila por cada unidad de la "Cantidad en stock" y
 * se sincroniza en vivo con ese campo (`[data-live-quantity]`).
 *
 * Variables esperadas:
 *   $unitSizeInit  array  [nº de unidad => talla] ya guardadas (vacío al crear)
 *   $unitSizeBase  string código maestro para mostrar el de cada unidad (LCN000042)
 *
 * Envía  unit_sizes[<nº de unidad>]  y se guarda con product_units_apply_sizes().
 */
$unitSizeInit = $unitSizeInit ?? [];
$unitSizeBase = $unitSizeBase ?? '';
$sizeCatalog  = product_sizes_catalog();
?>
<div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
    <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-serif text-lg text-gray-900">Tallas por unidad</h2>
        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600" data-sizes-count>0 unidades</span>
    </div>
    <p class="mb-4 text-sm text-gray-500">
        Del mismo modelo y color puede haber varias tallas. Indique la de cada unidad:
        cada una conserva su propio código de barras y el catálogo mostrará todas las tallas disponibles.
    </p>

    <div class="mb-4 flex flex-wrap items-end gap-2 rounded-xl border border-gray-100 bg-gray-50/60 p-3">
        <div class="min-w-[160px] flex-1">
            <label class="lcn-label" for="sizeFillAll">Rellenar todas con</label>
            <input id="sizeFillAll" type="text" list="lcnSizes" placeholder="Ej. M" class="lcn-input text-sm">
        </div>
        <button type="button" data-fill-all
                class="rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
            Aplicar a todas
        </button>
        <button type="button" data-fill-clear
                class="rounded-xl px-3 py-2.5 text-sm font-medium text-gray-500 transition hover:text-brand-red">
            Vaciar
        </button>
    </div>

    <datalist id="lcnSizes">
        <?php foreach ($sizeCatalog as $s): ?>
            <option value="<?= e($s) ?>"></option>
        <?php endforeach; ?>
    </datalist>

    <div data-size-rows class="grid grid-cols-1 gap-3 sm:grid-cols-2"></div>

    <p data-size-empty class="hidden rounded-xl border border-dashed border-gray-200 bg-gray-50/60 px-4 py-6 text-center text-sm text-gray-400">
        Ponga una cantidad en stock de 1 o más para registrar las tallas.
    </p>

    <p class="mt-4 text-xs text-gray-400">
        La talla es opcional. Si la deja en blanco, la pieza se registra igual y podrá completarla más adelante.
    </p>
</div>

<script>
(function () {
    var qtyEl   = document.querySelector('[data-live-quantity]');
    var rowsEl  = document.querySelector('[data-size-rows]');
    var emptyEl = document.querySelector('[data-size-empty]');
    var countEl = document.querySelector('[data-sizes-count]');
    if (!rowsEl) return;

    var BASE  = <?= json_encode($unitSizeBase) ?>;
    var MAXU  = <?= (int) barcode_units_max() ?>;
    /* Tallas ya guardadas; se conservan al subir o bajar la cantidad. */
    var values = <?= json_encode(array_map('strval', $unitSizeInit), JSON_FORCE_OBJECT) ?>;

    function unitCode(n) {
        return BASE ? BASE + 'U' + (n < 10 ? '0' + n : String(n)) : '';
    }

    /* Guarda lo tecleado antes de repintar, para no perderlo. */
    function remember() {
        rowsEl.querySelectorAll('[data-size-input]').forEach(function (i) {
            values[i.getAttribute('data-unit')] = i.value;
        });
    }

    function render() {
        var n = parseInt((qtyEl && qtyEl.value) || '0', 10);
        if (isNaN(n) || n < 0) n = 0;
        if (n > MAXU) n = MAXU;

        rowsEl.innerHTML = '';
        for (var i = 1; i <= n; i++) {
            var row = document.createElement('label');
            row.className = 'flex items-center gap-3 rounded-xl border border-gray-100 bg-white px-3 py-2.5';

            var tag = document.createElement('span');
            tag.className = 'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-red/10 text-xs font-bold text-brand-red';
            tag.textContent = String(i);
            row.appendChild(tag);

            var box = document.createElement('span');
            box.className = 'min-w-0 flex-1';

            var code = unitCode(i);
            if (code) {
                var cd = document.createElement('span');
                cd.className = 'block font-mono text-[10px] tracking-wider text-gray-400';
                cd.textContent = code;
                box.appendChild(cd);
            }

            var input = document.createElement('input');
            input.type = 'text';
            input.name = 'unit_sizes[' + i + ']';
            input.className = 'lcn-input mt-0.5 py-1.5 text-sm';
            input.placeholder = 'Talla de la unidad ' + i;
            input.setAttribute('list', 'lcnSizes');
            input.setAttribute('data-size-input', '');
            input.setAttribute('data-unit', String(i));
            input.value = values[i] || '';
            box.appendChild(input);

            row.appendChild(box);
            rowsEl.appendChild(row);
        }

        if (emptyEl) emptyEl.classList.toggle('hidden', n > 0);
        if (countEl) countEl.textContent = n + (n === 1 ? ' unidad' : ' unidades');
    }

    if (qtyEl) {
        qtyEl.addEventListener('input',  function () { remember(); render(); });
        qtyEl.addEventListener('change', function () { remember(); render(); });
    }

    var fillEl = document.getElementById('sizeFillAll');
    var fillBt = document.querySelector('[data-fill-all]');
    if (fillBt) fillBt.addEventListener('click', function () {
        var v = (fillEl && fillEl.value.trim()) || '';
        if (v === '') { if (fillEl) fillEl.focus(); return; }
        rowsEl.querySelectorAll('[data-size-input]').forEach(function (i) { i.value = v; });
    });

    var clearBt = document.querySelector('[data-fill-clear]');
    if (clearBt) clearBt.addEventListener('click', function () {
        rowsEl.querySelectorAll('[data-size-input]').forEach(function (i) { i.value = ''; });
    });

    render();
})();
</script>
