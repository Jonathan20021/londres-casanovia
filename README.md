# LONDRES Casa de Novias

Plataforma web completa para la administración de una casa de novias: **alquiler y venta** de
vestidos de novia, vestidos de gala, trajes y accesorios. Incluye panel administrativo premium
(estilo SaaS) y un sitio público elegante para que las clientas exploren el inventario y soliciten
alquileres en línea.

Construida con **PHP puro (sin frameworks), MySQL/MariaDB, Tailwind CSS y JavaScript vanilla**.

---

## ✨ Características

- **Autenticación segura** con `password_hash`, sesiones endurecidas y protección CSRF.
- **Roles y permisos** (Super Admin, Administrador, Cajera, Vendedora, Inventario) controlados por base de datos.
- **Dashboard** con métricas reales, gráficos en CSS y tabla de próximos alquileres.
- **Inventario/Productos** con imagen principal + galería, SKU, tipo (alquiler/venta/ambos), estados físico y comercial, piezas únicas, destacados.
- **Disponibilidad por fecha** con detección real de conflictos de solapamiento y bloqueo automático de reservas duplicadas.
- **Alquileres** con flujo **50% inicial / 50% al retirar**, estados completos, contrato, evidencias de entrega/devolución.
- **Facturación, pagos y ventas** con numeración automática, recibos y comprobantes imprimibles.
- **Clientes** con historial completo (alquileres, ventas, pagos, facturas, deudas).
- **Calendario** mensual de ocupación con código de colores.
- **Reportes** financieros/operativos con exportación a CSV.
- **Sitio público**: landing emocional, catálogo con filtros, detalle de producto, verificación de disponibilidad y solicitud de alquiler en línea.
- **Configuración del negocio** (logo, contacto, políticas, % inicial, moneda, impuestos, color de marca).

---

## 🧰 Requisitos

- PHP **8.1+** (probado en 8.2) con extensiones `pdo_mysql`, `fileinfo`, `mbstring`, `gd`/`exif` recomendadas.
- MySQL **5.7+** o MariaDB **10.4+**.
- Servidor Apache (XAMPP, cPanel) o `php -S` para desarrollo.
- Conexión a internet para Tailwind Play CDN y Google Fonts (o compílalos para producción).

---

## 🚀 Instalación

### Opción A — XAMPP (línea de comandos)

```bash
# 1. Copiar el proyecto en htdocs
#    c:\xampp\htdocs\londres-casanovia

# 2. Iniciar Apache y MySQL desde el panel de XAMPP

# 3. Importar la base de datos
c:\xampp\mysql\bin\mysql.exe -u root < database/schema.sql
c:\xampp\mysql\bin\mysql.exe -u root londres_casa_novias < database/seed.sql
```

### Opción B — Instalador web (cPanel u hosting)

1. Sube el proyecto a tu hosting.
2. Edita las credenciales en [`app/config/database.php`](app/config/database.php).
3. Visita `https://tudominio.com/londres-casanovia/install.php` y pulsa **Instalar ahora**.
4. **Elimina `install.php`** al terminar.

### Acceso

| URL | Descripción |
|-----|-------------|
| `/londres-casanovia/public/index.php` | Sitio público |
| `/londres-casanovia/admin/login.php`  | Panel administrativo |

**Usuario demo:** `admin@londresnovias.com` · **Contraseña:** `Admin12345`
(todos los usuarios de prueba comparten esa contraseña).

---

## ⚙️ Configuración

Edita `app/config/`:

- **`database.php`** — credenciales MySQL (por defecto XAMPP: `root` sin contraseña, base `londres_casa_novias`).
- **`app.php`** — `APP_ENV` (`local`/`production`), `APP_URL` (carpeta bajo el document root, p. ej. `/londres-casanovia`; usa `''` si va en la raíz del dominio), zona horaria.
- **`security.php`** — sesión y CSRF (no suele requerir cambios).

> Para **producción** cambia `APP_ENV` a `production` (oculta errores) y considera compilar Tailwind en lugar del CDN.

---

## 📁 Estructura del proyecto

```
londres-casanovia/
├── index.php                 # Redirige al sitio público
├── install.php               # Instalador web (eliminar tras usar)
├── .htaccess                 # Seguridad + cabeceras
├── admin/                    # Panel administrativo
│   ├── login.php  logout.php  dashboard.php
│   ├── productos/  categorias/  alquileres/  clientes/
│   ├── facturas/  pagos/  ventas/  reportes/
│   ├── usuarios/  configuracion/  solicitudes/
├── public/                   # Sitio público
│   ├── index.php  inventario.php  producto.php
│   ├── solicitud-alquiler.php  confirmacion.php  contacto.php  disponibilidad.php
│   ├── api/check-availability.php
│   └── assets/ (css, js, img, uploads)
├── app/
│   ├── config/   (app, database, security)
│   ├── helpers/  (db, functions, auth, availability, upload, ui)
│   ├── bootstrap.php
│   └── views/ (layouts, components, templates)
├── database/  (schema.sql, seed.sql)
└── storage/   (invoices, contracts, backups, logs)
```

---

## 🔐 Lógica de disponibilidad

Función reutilizable en `app/helpers/availability.php`:

```php
checkProductAvailability($productId, $deliveryDate, $returnDate, $excludeRentalId = null)
// => ['available' => bool, 'conflict' => array|null]
```

Un producto **no está disponible** si existe un alquiler con estado
`reserved`, `confirmed`, `delivered` o `pending_return` cuyo rango de fechas se solapa:

```
new.delivery_date <= existing.return_date  AND  new.return_date >= existing.delivery_date
```

Pagos 50/50:

```php
calculateRentalPayments($total, $percentage = 50) // => ['total','percentage','initial','remaining']
```

---

## 🧩 Módulos incluidos

Autenticación · Dashboard · Productos/Inventario · Imágenes · Categorías · Clientes · Alquileres ·
Disponibilidad · Calendario · Solicitudes públicas · Facturas · Pagos (50/50) · Ventas ·
Entregas/Devoluciones · Reportes (CSV) · Usuarios y roles · Configuración · Notificaciones.

---

## 🛡️ Notas de seguridad

- Contraseñas con `password_hash()` (bcrypt) y verificación con `password_verify()`.
- **PDO + prepared statements** en todo acceso a datos (helpers `db_*`).
- **CSRF** en todos los formularios POST (`csrf_field()` / `require_csrf()`).
- Salida escapada con `e()` (`htmlspecialchars`).
- Subidas validadas (extensión, MIME real, tamaño, renombrado aleatorio) y sin ejecución de PHP.
- Carpetas `app/`, `storage/`, `database/` bloqueadas vía `.htaccess`.
- Control de permisos por rol con `require_permission()`.
- Registro de actividad (`activity_logs`).
- En producción los errores no se muestran (`APP_ENV='production'`).

---

## 🖨️ Facturas, recibos y contratos

Cada documento se puede **ver/imprimir** en pantalla (HTML con estilos `@media print`) y
**descargar como PDF** con el botón *Descargar PDF*.

- La descarga usa **Dompdf** (servidor). Ya viene declarado en `composer.json`; en un despliegue
  nuevo ejecuta `composer install` para tener la carpeta `vendor/`.
- Si Dompdf no está instalado, el botón **degrada con elegancia**: abre el documento con
  auto‑impresión para *Guardar como PDF* desde el navegador (mismo diseño, CSS en línea).
- Plantillas PDF autocontenidas en `app/views/templates/pdf/` (factura, recibo, contrato),
  con tipografía que conserva los acentos.

---

## 📝 Licencia

Proyecto entregado para uso comercial de **LONDRES Casa de Novias**.
