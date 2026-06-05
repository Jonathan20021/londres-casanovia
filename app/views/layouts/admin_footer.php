<?php
/**
 * Pie del layout administrativo. Cierra <main> y el documento.
 */
?>
        </div><!-- /max-w -->
    </main>

    <footer class="px-4 pb-8 pt-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl border-t border-gray-100 pt-5 text-center text-xs text-gray-400 dark:border-gray-800">
            &copy; <?= date('Y') ?> <?= e(setting('business_name', APP_NAME)) ?> · Panel administrativo
        </div>
    </footer>
</div><!-- /pl-72 -->

<?php if (!empty($use_charts)): ?>
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.49.1/dist/apexcharts.min.js"></script>
<?php endif; ?>
<script src="<?= asset('js/app.js') ?>"></script>
<?= $page_scripts ?? '' ?>
</body>
</html>
