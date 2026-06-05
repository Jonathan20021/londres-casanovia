<?php
/**
 * Gestión de categorías de productos.
 * LONDRES Casa de Novias — Panel administrativo
 *
 * Acciones POST: create | update | delete
 * Listado con conteo de productos (LEFT JOIN COUNT). Crear/editar via modal.
 * Soporta ?edit=ID para precargar y reabrir el modal automáticamente.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';   // admin/categorias/ => N=2
require_permission('categories.manage');

/* ------------------------------------------------------------------ *
 *  Manejo de POST (siempre antes de imprimir HTML)
 * ------------------------------------------------------------------ */
if (is_post()) {
    require_csrf();

    $action = (string) post('action', '');

    /* ---------- Eliminar ---------- */
    if ($action === 'delete') {
        $id = (int) post('id', 0);
        $cat = $id ? db_one('SELECT * FROM categories WHERE id = :id', ['id' => $id]) : null;

        if (!$cat) {
            flash('error', 'La categoría que intenta eliminar no existe.');
            redirect(admin_url('categorias/index.php'));
        }

        // No permitir eliminar si tiene productos asociados (integridad de inventario).
        $productCount = (int) db_value('SELECT COUNT(*) FROM products WHERE category_id = :cid', ['cid' => $id]);
        if ($productCount > 0) {
            flash('error', 'No se puede eliminar «' . $cat['name'] . '» porque tiene ' . $productCount . ' producto(s) asociado(s). Reasigne o elimine esos productos primero.');
            redirect(admin_url('categorias/index.php'));
        }

        db_delete('categories', 'id = :id', ['id' => $id]);
        delete_upload($cat['image'] ?? null);
        log_activity('delete', 'category', $id, 'Eliminó la categoría ' . $cat['name']);
        flash('success', 'Categoría «' . $cat['name'] . '» eliminada correctamente.');
        redirect(admin_url('categorias/index.php'));
    }

    /* ---------- Crear / Actualizar ---------- */
    if ($action === 'create' || $action === 'update') {
        $id          = (int) post('id', 0);
        $name        = trim((string) post('name', ''));
        $description = trim((string) post('description', ''));
        $status      = post('status') === 'inactive' ? 'inactive' : 'active';

        // Validación básica
        $errors = [];
        if ($name === '') {
            $errors[] = 'El nombre de la categoría es obligatorio.';
        } elseif (mb_strlen($name) > 100) {
            $errors[] = 'El nombre no puede superar los 100 caracteres.';
        }

        // En edición, verificar que la categoría exista
        $existing = null;
        if ($action === 'update') {
            $existing = $id ? db_one('SELECT * FROM categories WHERE id = :id', ['id' => $id]) : null;
            if (!$existing) {
                $errors[] = 'La categoría que intenta editar no existe.';
            }
        }

        // Subida de imagen opcional
        $imagePath = $existing['image'] ?? null;
        if (!$errors && isset($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $up = upload_image($_FILES['image'], 'categories');
            if (!$up['ok']) {
                $errors[] = $up['error'] ?? 'No se pudo subir la imagen.';
            } else {
                // Borrar la anterior al reemplazar
                if (!empty($existing['image'])) {
                    delete_upload($existing['image']);
                }
                $imagePath = $up['path'];
            }
        }

        if ($errors) {
            flash('error', implode(' ', $errors));
            // Reabrir el modal correspondiente conservando contexto
            redirect(admin_url('categorias/index.php' . ($action === 'update' && $id ? '?edit=' . $id : '?new=1')));
        }

        if ($action === 'create') {
            $newId = db_insert('categories', [
                'name'        => $name,
                'slug'        => unique_slug('categories', $name),
                'description' => $description !== '' ? $description : null,
                'image'       => $imagePath,
                'status'      => $status,
            ]);
            log_activity('create', 'category', $newId, 'Creó la categoría ' . $name);
            flash('success', 'Categoría «' . $name . '» creada correctamente.');
        } else {
            db_update('categories', [
                'name'        => $name,
                'slug'        => unique_slug('categories', $name, $id),
                'description' => $description !== '' ? $description : null,
                'image'       => $imagePath,
                'status'      => $status,
            ], 'id = :id', ['id' => $id]);
            log_activity('update', 'category', $id, 'Actualizó la categoría ' . $name);
            flash('success', 'Categoría «' . $name . '» actualizada correctamente.');
        }

        redirect(admin_url('categorias/index.php'));
    }

    // Acción no reconocida
    flash('error', 'Acción no válida.');
    redirect(admin_url('categorias/index.php'));
}

/* ------------------------------------------------------------------ *
 *  Consultas para mostrar
 * ------------------------------------------------------------------ */
$categories = db_all(
    'SELECT c.*, COUNT(p.id) AS product_count
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id
     GROUP BY c.id
     ORDER BY c.name ASC'
);

// Categoría a editar (precarga del modal) si viene ?edit=ID
$editId  = (int) get_param('edit', '0');
$editCat = $editId ? db_one('SELECT * FROM categories WHERE id = :id', ['id' => $editId]) : null;
$openNew = get_param('new') === '1';   // reabrir modal de creación tras error de validación

/* ------------------------------------------------------------------ *
 *  Encabezado de la página
 * ------------------------------------------------------------------ */
$page_title    = 'Categorías';
$page_subtitle = 'Organiza tu inventario de vestidos, trajes y accesorios.';
$active        = 'categorias';
$header_actions = '<button type="button" data-modal-open="modal-categoria" data-cat-new'
    . ' class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">'
    . icon('plus', 'w-4 h-4') . ' Nueva categoría</button>';

require LCN_ROOT . '/app/views/layouts/admin_header.php';
?>

<?php if (!$categories): ?>
    <!-- Estado vacío -->
    <?= empty_state(
        'Aún no hay categorías',
        'Crea tu primera categoría para clasificar los productos de tu catálogo y facilitar las búsquedas.',
        'squares',
        '<button type="button" data-modal-open="modal-categoria" data-cat-new class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">'
            . icon('plus', 'w-4 h-4') . ' Nueva categoría</button>'
    ) ?>
<?php else: ?>

    <!-- Grid de categorías -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <?php foreach ($categories as $c):
            $cid   = (int) $c['id'];
            $count = (int) $c['product_count'];
            // JSON seguro para precargar el formulario vía JS (datos escapados al pintar atributo)
            $payload = e(json_encode([
                'id'          => $cid,
                'name'        => $c['name'],
                'description' => $c['description'] ?? '',
                'status'      => $c['status'],
                'image'       => $c['image'] ? upload_url($c['image']) : '',
            ], JSON_UNESCAPED_UNICODE));
        ?>
            <article class="group flex flex-col overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-soft transition hover:shadow-card">
                <!-- Imagen / placeholder -->
                <div class="relative aspect-[16/10] overflow-hidden bg-gradient-to-br from-brand-cream to-rose-50">
                    <?php if (!empty($c['image'])): ?>
                        <img src="<?= e(upload_url($c['image'])) ?>" alt="<?= e($c['name']) ?>" loading="lazy"
                             class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                    <?php else: ?>
                        <div class="flex h-full w-full items-center justify-center text-brand-red/30">
                            <?= icon('squares', 'w-12 h-12') ?>
                        </div>
                    <?php endif; ?>
                    <span class="absolute right-3 top-3"><?= status_badge($c['status'], 'user') ?></span>
                </div>

                <!-- Contenido -->
                <div class="flex flex-1 flex-col p-5">
                    <h3 class="font-serif text-lg leading-tight text-gray-900"><?= e($c['name']) ?></h3>
                    <?php if (!empty($c['description'])): ?>
                        <p class="mt-1.5 line-clamp-2 text-sm text-gray-500"><?= e($c['description']) ?></p>
                    <?php else: ?>
                        <p class="mt-1.5 text-sm italic text-gray-300">Sin descripción</p>
                    <?php endif; ?>

                    <div class="mt-4 flex items-center gap-2 text-sm text-gray-600">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">
                            <?= icon('tag', 'w-3.5 h-3.5') ?>
                            <?= $count ?> <?= $count === 1 ? 'producto' : 'productos' ?>
                        </span>
                    </div>

                    <!-- Acciones -->
                    <div class="mt-5 flex items-center gap-2 border-t border-gray-50 pt-4">
                        <a href="<?= e(admin_url('productos/index.php?category=' . $cid)) ?>"
                           class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                           title="Ver productos de esta categoría">
                            <?= icon('eye', 'w-4 h-4') ?> Productos
                        </a>

                        <button type="button"
                                data-cat-edit
                                data-cat='<?= $payload ?>'
                                data-modal-open="modal-categoria"
                                class="ml-auto inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white p-2 text-gray-500 transition hover:bg-gray-50 hover:text-brand-red"
                                title="Editar categoría">
                            <?= icon('pencil', 'w-4 h-4') ?>
                        </button>

                        <form method="post" action="<?= e(admin_url('categorias/index.php')) ?>"
                              data-confirm="¿Eliminar la categoría «<?= e($c['name']) ?>»? Esta acción no se puede deshacer."
                              class="inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $cid ?>">
                            <button type="submit"
                                    class="inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white p-2 text-gray-500 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600"
                                    title="Eliminar categoría">
                                <?= icon('trash', 'w-4 h-4') ?>
                            </button>
                        </form>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

<?php endif; ?>

<!-- ============================================================ -->
<!--  MODAL: Crear / Editar categoría                             -->
<!-- ============================================================ -->
<div id="modal-categoria" data-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-brand-dark/50 p-4 backdrop-blur-sm">
    <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-card animate-scale-in">
        <!-- Cabecera del modal -->
        <div class="mb-5 flex items-start justify-between">
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-red-50 text-brand-red"><?= icon('squares', 'w-6 h-6') ?></span>
                <div>
                    <h2 id="modal-categoria-title" class="font-serif text-xl font-bold text-gray-900">Nueva categoría</h2>
                    <p class="text-sm text-gray-500">Completa los datos de la categoría.</p>
                </div>
            </div>
            <button type="button" data-modal-close class="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600"><?= icon('x', 'w-5 h-5') ?></button>
        </div>

        <form method="post" action="<?= e(admin_url('categorias/index.php')) ?>" enctype="multipart/form-data" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" id="cat-action" value="create">
            <input type="hidden" name="id" id="cat-id" value="">

            <!-- Nombre -->
            <div>
                <label for="cat-name" class="lcn-label">Nombre <span class="text-brand-red">*</span></label>
                <input type="text" name="name" id="cat-name" required maxlength="100"
                       class="lcn-input" placeholder="Ej. Vestidos de novia">
            </div>

            <!-- Descripción -->
            <div>
                <label for="cat-description" class="lcn-label">Descripción</label>
                <textarea name="description" id="cat-description" rows="3" maxlength="255"
                          class="lcn-input" placeholder="Breve descripción de la categoría (opcional)"></textarea>
            </div>

            <!-- Estado -->
            <div>
                <label for="cat-status" class="lcn-label">Estado</label>
                <select name="status" id="cat-status" class="lcn-input">
                    <option value="active">Activa</option>
                    <option value="inactive">Inactiva</option>
                </select>
            </div>

            <!-- Imagen -->
            <div>
                <label for="cat-image" class="lcn-label">Imagen <span class="font-normal text-gray-400">(opcional)</span></label>
                <div class="mt-1 flex items-center gap-4">
                    <img id="cat-image-preview" src="" alt="" class="hidden h-16 w-24 rounded-xl border border-gray-100 object-cover shadow-soft">
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                        <?= icon('upload', 'w-4 h-4') ?>
                        <span>Subir imagen</span>
                        <input type="file" name="image" id="cat-image" accept="image/*" data-image-preview="#cat-image-preview" class="hidden">
                    </label>
                </div>
                <p class="mt-1.5 text-xs text-gray-400">JPG, PNG o WEBP · máximo 5 MB.</p>
            </div>

            <!-- Acciones del modal -->
            <div class="flex items-center justify-end gap-2 border-t border-gray-100 pt-4">
                <button type="button" data-modal-close
                        class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                    Cancelar
                </button>
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                    <?= icon('check', 'w-4 h-4') ?> <span id="cat-submit-label">Guardar categoría</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var modalTitle  = document.getElementById('modal-categoria-title');
    var submitLabel = document.getElementById('cat-submit-label');
    var fAction     = document.getElementById('cat-action');
    var fId         = document.getElementById('cat-id');
    var fName       = document.getElementById('cat-name');
    var fDesc       = document.getElementById('cat-description');
    var fStatus     = document.getElementById('cat-status');
    var fImage      = document.getElementById('cat-image');
    var imgPreview  = document.getElementById('cat-image-preview');

    // Restablece el formulario al modo "crear".
    function resetToCreate() {
        fAction.value = 'create';
        fId.value     = '';
        fName.value   = '';
        fDesc.value   = '';
        fStatus.value = 'active';
        if (fImage) fImage.value = '';
        if (imgPreview) { imgPreview.src = ''; imgPreview.classList.add('hidden'); }
        modalTitle.textContent  = 'Nueva categoría';
        submitLabel.textContent = 'Guardar categoría';
    }

    // Precarga el formulario con los datos de una categoría existente.
    function fillForm(data) {
        fAction.value = 'update';
        fId.value     = data.id || '';
        fName.value   = data.name || '';
        fDesc.value   = data.description || '';
        fStatus.value = data.status || 'active';
        if (fImage) fImage.value = '';
        if (imgPreview) {
            if (data.image) { imgPreview.src = data.image; imgPreview.classList.remove('hidden'); }
            else { imgPreview.src = ''; imgPreview.classList.add('hidden'); }
        }
        modalTitle.textContent  = 'Editar categoría';
        submitLabel.textContent = 'Actualizar categoría';
    }

    // Botón "Nueva categoría"
    document.querySelectorAll('[data-cat-new]').forEach(function (btn) {
        btn.addEventListener('click', resetToCreate);
    });

    // Botones "Editar" de cada tarjeta
    document.querySelectorAll('[data-cat-edit]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            try { fillForm(JSON.parse(btn.getAttribute('data-cat'))); }
            catch (err) { resetToCreate(); }
        });
    });

    // Apertura automática del modal según parámetros del servidor (?edit / ?new)
<?php if ($editCat): ?>
    fillForm(<?= json_encode([
        'id'          => (int) $editCat['id'],
        'name'        => $editCat['name'],
        'description' => $editCat['description'] ?? '',
        'status'      => $editCat['status'],
        'image'       => $editCat['image'] ? upload_url($editCat['image']) : '',
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);
    if (window.lcnOpenModal) window.lcnOpenModal('modal-categoria');
<?php elseif ($openNew): ?>
    resetToCreate();
    if (window.lcnOpenModal) window.lcnOpenModal('modal-categoria');
<?php endif; ?>
})();
</script>

<?php require LCN_ROOT . '/app/views/layouts/admin_footer.php'; ?>
