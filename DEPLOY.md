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

## 4. Acceso

| URL | Descripción |
|-----|-------------|
| `https://tudominio.com/` | Redirige a la tienda pública |
| `https://tudominio.com/public/index.php` | Sitio público |
| `https://tudominio.com/admin/login.php` | Panel administrativo |

**Acceso admin:** `admin@londresnovias.com` · `Admin12345`

## 5. Checklist de seguridad (hazlo al entrar)

- [ ] **Cambia la contraseña** del usuario admin (Usuarios → Editar) y de los demás usuarios demo.
- [ ] Borra `install.php` del servidor.
- [ ] Revisa la **Configuración del negocio** (teléfono, WhatsApp, dirección, Instagram, políticas).
- [ ] Si no quieres los **datos demo**, elimina desde el panel los clientes/alquileres/productos de ejemplo
      (o pídeme un script para limpiarlos dejando solo categorías y configuración).
- [ ] Sube tus **fotos reales** de inventario (Productos → Editar → arrastrar/ordenar/elegir principal).

## 6. Notas

- Tailwind, ApexCharts, SortableJS y las fuentes se cargan por CDN: el navegador del visitante necesita
  internet (no el servidor). Para máximo rendimiento se pueden auto-alojar más adelante.
- Las credenciales de la BD viven en `app/config/database.php`; la carpeta `app/` está bloqueada al
  acceso web por `.htaccess`.
- Producción tiene `APP_DEBUG=false`: los errores no se muestran y se registran en `storage/logs/`.
