<?php
/** Pie del sitio público. */
$wa   = preg_replace('/\D/', '', (string) setting('whatsapp', ''));
$ig   = trim((string) setting('instagram', ''));
$igUrl = $ig ? 'https://instagram.com/' . ltrim($ig, '@') : '';
?>
</main>

<footer class="mt-24 bg-brand-dark text-gray-300">
    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-16 sm:px-6 md:grid-cols-4 lg:px-8">
        <div class="md:col-span-2">
            <?= brand_lockup('light', 'md') ?>
            <p class="mt-5 max-w-sm text-sm leading-relaxed text-gray-400">
                Vestidos de novia, vestidos de gala, trajes y accesorios para tu evento más especial.
                Elegancia, confianza y un servicio impecable.
            </p>
            <div class="mt-6 flex items-center gap-3">
                <?php if ($wa): ?><a href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener" class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-brand-red"><?= icon('whatsapp', 'w-5 h-5') ?></a><?php endif; ?>
                <?php if ($igUrl): ?><a href="<?= e($igUrl) ?>" target="_blank" rel="noopener" class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-brand-red"><?= icon('instagram', 'w-5 h-5') ?></a><?php endif; ?>
                <?php if (setting('email')): ?><a href="mailto:<?= e(setting('email')) ?>" class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-brand-red"><?= icon('mail', 'w-5 h-5') ?></a><?php endif; ?>
            </div>
        </div>

        <div>
            <h4 class="font-serif text-lg text-white">Explorar</h4>
            <ul class="mt-4 space-y-2.5 text-sm">
                <li><a href="<?= pub_url('index.php') ?>" class="text-gray-400 transition hover:text-brand-gold">Inicio</a></li>
                <li><a href="<?= pub_url('inventario.php') ?>" class="text-gray-400 transition hover:text-brand-gold">Inventario</a></li>
                <li><a href="<?= pub_url('contacto.php') ?>" class="text-gray-400 transition hover:text-brand-gold">Contacto</a></li>
                <li><a href="<?= admin_url('login.php') ?>" class="text-gray-400 transition hover:text-brand-gold">Acceso administrativo</a></li>
            </ul>
        </div>

        <div>
            <h4 class="font-serif text-lg text-white">Contacto</h4>
            <ul class="mt-4 space-y-3 text-sm text-gray-400">
                <?php if (setting('address')): ?><li class="flex gap-2"><span class="mt-0.5 text-brand-gold"><?= icon('pin', 'w-4 h-4') ?></span><?= e(setting('address')) ?></li><?php endif; ?>
                <?php if (setting('phone')): ?><li class="flex gap-2"><span class="mt-0.5 text-brand-gold"><?= icon('phone', 'w-4 h-4') ?></span><?= e(setting('phone')) ?></li><?php endif; ?>
                <?php if (setting('opening_hours')): ?><li class="flex gap-2"><span class="mt-0.5 text-brand-gold"><?= icon('clock', 'w-4 h-4') ?></span><?= e(setting('opening_hours')) ?></li><?php endif; ?>
            </ul>
        </div>
    </div>
    <div class="border-t border-white/10">
        <div class="mx-auto max-w-7xl px-4 py-6 text-center text-xs text-gray-500 sm:px-6 lg:px-8">
            &copy; <?= date('Y') ?> <?= e(setting('business_name', APP_NAME)) ?>. Todos los derechos reservados.
        </div>
    </div>
</footer>

<script src="<?= asset('js/public.js') ?>"></script>
</body>
</html>
