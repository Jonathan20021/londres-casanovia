# Design

Sistema visual de **LONDRES Casa de Novias**. Tailwind (Play CDN con config de marca en `app/views/layouts/_head_assets.php`) + CSS propio en `public/assets/css/app.css`. Cambiar tokens ahí propaga a panel + web.

## Theme
Claro por defecto en el panel (fondo `#F6F6F4`, superficies blancas) con modo oscuro opcional. La web pública alterna secciones claras (catálogo, confianza) con secciones "drenched" oscuras (hero, CTA) para dar contraste de lujo.

## Color palette
- **brand.red** `#C8102E` — color de marca / acción primaria / cifras clave / estado activo. No decorativo.
- **brand.dark** `#0B0B0C` / **brand.ink** `#1A1A1D` — fondos drenched, texto fuerte.
- **brand.gold** `#C9A86A` / **brand.champ** `#EBDCC0` — acento de lujo (filetes, detalles, eyebrow), nunca para texto de cuerpo.
- **brand.cream** `#F7F3EE` — superficie cálida suave de secciones.
- Neutros Tailwind (gray-50…900) para UI; segundo nivel: sidebar/topbar sobre `#F6F6F4`.
- Estados (badges): emerald=ok/disponible/pagado, sky=reservado, indigo=confirmado, amber=alquilado/parcial, rose=pendiente devolución/peligro, violet=vendido, gray=neutro.
- Charts (ApexCharts): serie principal `#C8102E`; paletas `['#C8102E','#C9A86A','#1A1A1D','#E0303F','#7C8089','#2E9C76']`. Texto de ejes `#64748b`, rejilla `#eef0f3`.

## Typography
- **Display/serif: Fraunces** (variable, opsz) — títulos de página y de sección, números grandes de reporte. Solo encabezados, nunca en labels/datos del panel.
- **Sans/UI: Plus Jakarta Sans** — todo lo funcional: botones, labels, tablas, cuerpo.
- **Acento: `.font-script`** = Fraunces itálica (reemplaza la antigua manuscrita) para eyebrows de la web.
- Panel: escala rem fija, ratio ~1.2; web: `clamp()` fluido en hero/secciones.
- (Fraunces/Plus Jakarta están en la lista reflex-reject del skill, pero son **identidad ya comprometida** del proyecto → se preservan.)

## Components
- **Botones**: primario `rounded-full/xl bg-brand-red text-white hover:bg-red-700`; secundario `border border-gray-200 bg-white`. Estados hover/focus/disabled consistentes.
- **Tarjeta**: `rounded-2xl border border-gray-100 bg-white shadow-soft`; nunca tarjetas anidadas.
- **Tablas**: header `bg-gray-50` uppercase xs; filas `divide-y` + hover; acciones vía menú de 3 puntos (`row_menu`).
- **Badges de estado**: `status_badge($estado,$grupo)`.
- **Métricas**: `metric_card()` (label + icono tintado + número grande).
- **Paginación**: `render_pagination()` = "Página X de Y" + flechas circulares.
- **Sidebar**: acordeón `<details>` con grupos; topbar con buscador redondeado + iconos circulares + pill de usuario.
- **Gráficos**: ApexCharts (área/donut/barra/radial) cargados con `$use_charts=true`, init en `$page_scripts`.
- **Iconos**: set SVG inline propio vía `icon()`.

## Layout
- Panel: sidebar fija 18rem (`lg:pl-72`), contenido `max-w-7xl`, rejillas que colapsan por breakpoint (no tipografía fluida).
- Web: hero full-bleed con imagen, secciones con `clamp()` de espaciado, grids `repeat(auto-fit,minmax(...))`.
- z-index semántico; dropdowns que escapan el overflow con posición fija.

## Motion
- Panel: 150–250ms en transiciones; el movimiento comunica estado (hover, abrir menú, cambio de tab), no decora; sin secuencias de carga.
- Web: un reveal de entrada bien orquestado por sección (IntersectionObserver), con alternativa `prefers-reduced-motion`.
