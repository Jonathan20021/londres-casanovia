# Despliegue a producción — LONDRES Casa de Novias

La base de datos de producción **ya quedó lista** (esquema + datos importados). Esta guía cubre subir
los archivos y dejar el sitio en línea.

## 1. Estado actual

- **Servidor BD:** `129.121.81.172` (acceso remoto) · en el hosting la app conecta por `localhost`.
- **Base de datos:** `neetjbte_londrescasadenovia` · **Usuario:** `neetjbte_londres`
- **Importado:** 18 tablas, roles/permisos, configuración, 6 categorías (con foto), 8 productos con
  fotos reales, y datos demo (clientes, alquileres, pagos, facturas). Acentos correctos (UTF-8).
- **No necesitas** ejecutar `install.php` (ya está todo cargado).

## 2. Cómo funciona la configuración (local vs producción)

- En **desarrollo (XAMPP)** existe `app/config/local.php`, que fuerza la base local y `APP_ENV=local`.
- En **producción** ese archivo NO se sube; la app usa los valores de `app/config/database.php`
  (credenciales reales, host `localhost`) y `app/config/app.php` (`APP_ENV=production`, errores ocultos).

> **IMPORTANTE: NO subas `app/config/local.php` al servidor.** Ya está en `.gitignore`.

## 3. Subir los archivos

1. Sube **todo el proyecto** a la carpeta pública del hosting (normalmente `public_html`),
   **excepto** `app/config/local.php`.
   - Incluye sí o sí `public/assets/img/products/` y `public/assets/img/categories/`
     (las fotos reales) y la carpeta `public/assets/`.
2. Si subiste el proyecto a una **subcarpeta** (ej. `public_html/londres`), edita
   [`app/config/app.php`](app/config/app.php) y pon `define('APP_URL', '/londres');`.
   Si va en la **raíz del dominio**, déjalo en `''` (ya configurado).
3. Verifica que el host de la BD sea correcto: por defecto `localhost` (estándar cPanel). Si tu app
   está en otro servidor distinto al de la base, cambia `DB_HOST` a `129.121.81.172` en
   [`app/config/database.php`](app/config/database.php).
4. Permisos de escritura (CHMOD 755/775) para: `storage/` y `public/assets/uploads/`.
5. **Borra `install.php`** del servidor por seguridad.
6. (Opcional, para PDF en servidor) ejecuta `composer install` en el servidor para tener `vendor/`
   (Dompdf). Sin esto, el botón "Descargar PDF" abre la versión imprimible del navegador.
7. **Migración de códigos de barra** (sólo si la base de datos ya existía antes de esta versión):
   importa una única vez `database/migrations/2026_07_22_barcodes.sql` desde phpMyAdmin o con
   `mysql -h HOST -u USER -p -D BD --default-character-set=utf8mb4 < database/migrations/2026_07_22_barcodes.sql`.
   Añade `products.barcode` y `business_settings.barcode_prefix`; los productos existentes reciben
   su código automáticamente al abrir *Inventario → Códigos de barra*.
   Las etiquetas usan la extensión **GD** de PHP (activa por defecto en cPanel); si no estuviera,
   el PDF dibuja las barras igualmente con un método alterno.
8. ⚠️ **ACTUALIZACIÓN 2026‑07‑26 — OBLIGATORIA.** Ejecute
   [`database/migrations/2026_07_26_TODAS_actualizar_produccion.sql`](database/migrations/2026_07_26_TODAS_actualizar_produccion.sql).
   Es **un solo archivo** que reúne los cambios de esta versión, y es **idempotente**: puede
   ejecutarse varias veces sin romper nada y también repara una actualización aplicada a medias.

   > **El código de esta versión NO funciona sin esta migración.** Si sube los archivos sin
   > ejecutarla, el panel devuelve **error 500** al guardar un producto, al crear un alquiler y
   > al abrir el tablero, el detalle de alquiler, el contrato o la factura. El motivo es que esas
   > pantallas consultan columnas que aún no existen en la base.

   Formas de ejecutarla:
   - **phpMyAdmin** (lo más simple): base → pestaña **SQL** → pegar el archivo → *Continuar*.
   - Consola:
     `mysql -h HOST -u USER -p -D BD --default-character-set=utf8mb4 < database/migrations/2026_07_26_TODAS_actualizar_produccion.sql`

   Al terminar imprime una tabla de verificación: **las 11 columnas deben decir `OK`**.
   Añade los complementos, la mora fija por día laborable, el descuento en %, la fecha real de
   devolución, la hora de entrega y el control de piezas por modificar.

   Después, revise *Configuración → Facturación* para ajustar el monto de la mora (RD$500 por
   defecto) y si el sábado cuenta como día laborable.

   *(Los archivos `2026_07_26_ajustes_cliente.sql` y `2026_07_26_hora_entrega_modificaciones.sql`
   quedan solo como referencia histórica: su contenido ya está incluido en el archivo de arriba.
   No hace falta ejecutarlos por separado.)*
9. ⚠️ **ACTUALIZACIÓN 2026‑08‑03 — un código de barras por UNIDAD.** Ejecute
   [`database/migrations/2026_08_03_product_units.sql`](database/migrations/2026_08_03_product_units.sql).
   Crea la tabla `product_units`: si un producto tiene *Cantidad en stock* = 10, el sistema genera
   **10 códigos distintos** (`LCN000042U01` … `LCN000042U10`) para pegarle su etiqueta a cada pieza.
   Es idempotente (`CREATE TABLE IF NOT EXISTS`) y no toca ninguna tabla existente.

   > Sin esta migración el panel **sigue funcionando** con el código único por producto (la app
   > detecta que la tabla no existe), pero no podrá imprimir una etiqueta por unidad.

   Al terminar, abra *Inventario → Códigos de barra*: las unidades que falten se crean solas según
   la cantidad en stock de cada producto. Las etiquetas ya impresas (código maestro) siguen siendo
   válidas: el escáner las reconoce igual.

## 4. Acceso

| URL | Descripción |
|-----|-------------|
| `https://tudominio.com/` | Tienda pública (sin `/public/` ni `.php`) |
| `https://tudominio.com/inventario` · `/producto/<slug>` · `/contacto` | Páginas públicas |
| `https://tudominio.com/admin/login` | Panel administrativo |

> **URLs limpias:** la app usa `.htaccess` (mod_rewrite) para servir todo sin `.php` ni `/public/`.
> Tu hosting ya tiene mod_rewrite activo (verificado). Si lo cambiaras de servidor, asegúrate de que
> `AllowOverride All` y `mod_rewrite` estén habilitados, o las URLs limpias darán 404.

**Acceso admin:** `admin@londresnovias.com` · `Admin12345`

## 5. Checklist de seguridad (hazlo al entrar)

- [ ] **Cambia la contraseña** del usuario admin (Usuarios → Editar) y de los demás usuarios demo.
- [ ] Borra `install.php` del servidor.
- [ ] Revisa la **Configuración del negocio** (teléfono, WhatsApp, dirección, Instagram, políticas).
- [ ] Si no quieres los **datos demo**, elimina desde el panel los clientes/alquileres/productos de ejemplo
      (o pídeme un script para limpiarlos dejando solo categorías y configuración).
- [ ] Sube tus **fotos reales** de inventario (Productos → Editar → arrastrar/ordenar/elegir principal).

## 6. Solución de problemas

**500 con "Access denied for user 'root'@... (using password: NO)"**
El servidor está usando la configuración vieja (XAMPP: root sin clave). Causas y arreglo:
1. Existe `app/config/local.php` en el servidor (fuerza modo local + root). **Bórralo del servidor.**
2. El `app/config/database.php` del servidor es el viejo y el `git pull` se aborta por conflicto. Arréglalo:
   ```bash
   # En la Terminal de cPanel, dentro de la carpeta del proyecto:
   rm -f app/config/local.php
   git checkout -- app/config/database.php   # descarta la versión vieja del servidor
   git pull                                   # ahora trae la config correcta
   ```
   Sin Terminal: en el Administrador de archivos, borra `app/config/local.php` y edita
   `app/config/database.php` con `DB_HOST=129.121.81.172`, `DB_NAME=neetjbte_londrescasadenovia`,
   `DB_USER=neetjbte_londres`, `DB_PASS=Miguel#2026#`.

**404 "Sorry, this page doesn't exist" (página del hosting)**
Estás usando una URL con una subcarpeta que no existe (ej. `/londres-casanovia/...`). El proyecto está
en la **raíz del subdominio**: entra a `https://tudominio.com/` (redirige a la tienda) o
`https://tudominio.com/public/index.php`. Por eso `APP_URL` debe ser `''` (ya configurado).

## 7. Notas

- Tailwind, ApexCharts, SortableJS y las fuentes se cargan por CDN: el navegador del visitante necesita
  internet (no el servidor). Para máximo rendimiento se pueden auto-alojar más adelante.
- Las credenciales de la BD viven en `app/config/database.php`; la carpeta `app/` está bloqueada al
  acceso web por `.htaccess`.
- Producción tiene `APP_DEBUG=false`: los errores no se muestran y se registran en `storage/logs/`.
