<?php
/**
 * Assets comunes del <head>: Tailwind (Play CDN) + config de marca,
 * fuentes y CSS propio. Incluir dentro de <head> tras el bootstrap.
 *
 * Para PRODUCCIÓN se recomienda compilar Tailwind; el CDN es ideal para
 * XAMPP/desarrollo y despliegues simples en cPanel.
 */
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<script>
  /* Estado del menú lateral aplicado ANTES de pintar, para que no haya salto. */
  try {
    if (localStorage.getItem('lcnSidebar') === 'collapsed') {
      document.documentElement.classList.add('lcn-collapsed');
    }
  } catch (e) {}
</script>
<!-- Icono: la cabina del logo oficial (legible a tamaño diminuto) -->
<link rel="icon" type="image/svg+xml" href="<?= asset('img/logo-cabina.svg') ?>">
<link rel="apple-touch-icon" href="<?= asset('img/logo-cabina.png') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;0,9..144,900;1,9..144,400;1,9..144,500;1,9..144,600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
  window.LCN_BASE = <?= json_encode(APP_URL) ?>;
  tailwind.config = {
    darkMode: 'class',
    theme: {
      extend: {
        colors: {
          brand: {
            red:   '<?= e(setting('primary_color', '#C8102E')) ?>',
            dark:  '#0B0B0C',
            ink:   '#1A1A1D',
            gold:  '#C9A86A',
            champ: '#EBDCC0',
            cream: '#F7F3EE',
          },
        },
        fontFamily: {
          sans:    ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
          serif:   ['Fraunces', 'ui-serif', 'Georgia', 'serif'],
          display: ['Fraunces', 'ui-serif', 'Georgia', 'serif'],
          script:  ['Fraunces', 'ui-serif', 'Georgia', 'serif'],
        },
        letterSpacing: { tightish: '-0.015em' },
        boxShadow: {
          soft: '0 1px 3px rgba(16,24,40,.06), 0 1px 2px rgba(16,24,40,.04)',
          card: '0 12px 40px -12px rgba(16,24,40,.18)',
        },
        borderRadius: { '2xl': '1rem', '3xl': '1.5rem' },
      },
    },
  };
</script>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
