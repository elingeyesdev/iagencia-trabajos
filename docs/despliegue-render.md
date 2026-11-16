# REDWORK — Despliegue en Render

Guía para desplegar REDWORK (Laravel 13, PHP 8.3, MySQL) en Render a partir del repositorio de GitHub.
**No contiene secretos.** Todos los valores sensibles se escriben solo en el panel de Render.

Laragon no cambia: sigue usando su `.env` local, HTTP, `MAIL_MAILER=log` y su MySQL local.

---

## 1. Qué se construye

La imagen la define el `Dockerfile`, con Apache + mod_php y PHP 8.3. La construcción sigue estos pasos:

1. Extensiones PHP: `pdo_mysql`, `mbstring`, `exif`, `pcntl`, `bcmath` y `gd`. El resto ya viene en la imagen oficial.
2. `composer install --no-dev` desde `composer.lock`.
3. **Fuentes de TCPDF.** Se generan con `vendor/tecnickcom/tc-lib-pdf-font/util/build_fonts.php`.
   - La construcción **falla** si no aparece `target/fonts/core/helvetica.json`, ni sus variantes negrita e itálica.
   - Al terminar se borra la descarga temporal de fuentes de origen.
   - El generador descarga las fuentes de origen desde GitHub, así que la construcción necesita salida a Internet (Render la tiene).
4. `npm ci` desde `package-lock.json` y luego `npm run build`. La construcción falla si no existe `public/build/manifest.json`; después se borra `node_modules`.
5. Permisos de escritura para `www-data` en `storage/` y `bootstrap/cache/`.

`.dockerignore` deja fuera de la imagen `.env`, claves, `vendor`, `node_modules`, `public/build`, dumps SQL, bases SQLite, logs, pruebas y `.git`.

Al arrancar, `docker/start.sh` hace lo siguiente:

- Comprueba que exista `APP_KEY` y se detiene con un mensaje claro si falta.
- Ejecuta `config:cache`, `route:cache` y `view:cache` con las variables reales de Render.
- Inicia Apache en `0.0.0.0:$PORT` (Render entrega `PORT=10000`), con el document root en `public/`.
- **No** ejecuta migraciones, seeders ni `key:generate`.

Comprobación de salud: `GET /up` (responde 200 sin tocar la base de datos).

---

## 2. Servicio que hay que crear en Render

| Campo | Valor |
|---|---|
| Tipo | **Web Service** |
| Origen | Repositorio de GitHub de REDWORK, rama que se vaya a publicar |
| Runtime / Language | **Docker** (usa el `Dockerfile` de la raíz) |
| Health Check Path | `/up` |
| Auto-Deploy | Opcional. Se recomienda desactivarlo durante la defensa. |

Render **no ofrece MySQL gestionado**. Hay que usar un MySQL externo accesible desde Internet (ver §4).
No hay que pasarse a PostgreSQL ni a SQLite.

---

## 3. Variables de entorno en Render

Se configuran en *Environment*. Los valores marcados como **secreto** no se escriben en ningún archivo del repositorio.

### Aplicación

| Variable | Valor | Nota |
|---|---|---|
| `APP_NAME` | `RedWork` | |
| `APP_ENV` | `production` | Activa HTTPS en las URL y oculta errores |
| `APP_DEBUG` | `false` | Nunca `true` en producción |
| `APP_KEY` | **secreto** | Ver más abajo |
| `APP_URL` | `https://<tu-servicio>.onrender.com` | Con `https://` y sin barra final |
| `APP_LOCALE` | `es` | Opcional |
| `TRUSTED_PROXIES` | `*` | Confía en el proxy de Render, que termina TLS |
| `HAS_SSL` | `true` | Opcional: en `production` ya se fuerza HTTPS |
| `LOG_CHANNEL` | `stderr` | Los logs aparecen en el panel de Render; el disco del contenedor es efímero |
| `LOG_LEVEL` | `warning` | |

**Cómo generar `APP_KEY`:**

- Se genera **una sola vez** en el equipo local con `php artisan key:generate --show`. La orden imprime la clave y **no** modifica `.env`.
- Se pega en Render y no se vuelve a cambiar entre despliegues.
- Si cambiara, se cerrarían todas las sesiones y dejarían de servir los enlaces firmados pendientes.
- No reutilices la clave del `.env` local.

### Base de datos MySQL

| Variable | Valor |
|---|---|
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` | **secreto** (host del proveedor) |
| `DB_PORT` | `3306`, o el que indique el proveedor |
| `DB_DATABASE` | **secreto** |
| `DB_USERNAME` | **secreto** |
| `DB_PASSWORD` | **secreto** |
| `MYSQL_ATTR_SSL_CA` | `/etc/ssl/certs/ca-certificates.crt`. Solo si el proveedor exige TLS (Aiven, TiDB Cloud, etc.) |

### Sesiones, caché y colas

Estas tablas persisten en MySQL entre reinicios del contenedor.

| Variable | Valor | Motivo |
|---|---|---|
| `SESSION_DRIVER` | `database` | Tabla `sessions`. Con archivos, las sesiones se perderían en cada despliegue. |
| `SESSION_SECURE_COOKIE` | `true` | La cookie solo viaja por HTTPS |
| `SESSION_LIFETIME` | `120` | |
| `CACHE_STORE` | `database` | Tablas `cache` y `cache_locks` |
| `QUEUE_CONNECTION` | `sync` | No hay worker; los correos de verificación y recuperación son síncronos |
| `FILESYSTEM_DISK` | `local` | REDWORK no guarda archivos subidos por usuarios |

No definas `SESSION_DOMAIN` ni `SANCTUM_STATEFUL_DOMAINS`. Los dashboards usan tokens Bearer en la misma página y no necesitan sesión de Sanctum entre dominios.

### Correo (verificación de email y recuperación de contraseña)

Los servicios **gratuitos** de Render bloquean el SMTP saliente por los puertos **25, 465 y 587**. REDWORK usa el transporte SMTP de Laravel, que ya está incluido y no requiere instalar nada, por el puerto **2525**. Ese puerto lo ofrecen Brevo, Mailgun, Resend, SendGrid y otros.

| Variable | Valor |
|---|---|
| `MAIL_MAILER` | `smtp`. **No** dejar `log` en producción. |
| `MAIL_HOST` | Host SMTP del proveedor elegido |
| `MAIL_PORT` | `2525` |
| `MAIL_USERNAME` | **secreto** |
| `MAIL_PASSWORD` | **secreto** (clave SMTP o API key del proveedor) |
| `MAIL_SCHEME` | Sin definir (STARTTLS automático) |
| `MAIL_TIMEOUT` | `10`, para que un bloqueo de red no deje esperando al usuario |
| `MAIL_FROM_ADDRESS` | Dirección remitente **verificada** en el proveedor |
| `MAIL_FROM_NAME` | `RedWork` |

Pasos para el correo:

1. Crear una cuenta en un proveedor con SMTP en el puerto 2525 y verificar el remitente o el dominio.
2. Copiar las credenciales SMTP en Render.
3. Desplegar y registrar un usuario de prueba con un correo real **tuyo**. Comprobar que llega el correo y que el enlace empieza por `https://<tu-servicio>.onrender.com/verify-email/…`.
4. Si el correo no sale, REDWORK no devuelve un 500: muestra un aviso y registra el fallo (sin datos personales) en los logs de Render.

Alternativa si el puerto 2525 también estuviera bloqueado: un proveedor por API HTTPS. Requiere instalar su paquete (por ejemplo `composer require resend/resend-php`, y luego `MAIL_MAILER=resend` y `RESEND_API_KEY`), lo que necesita **autorización previa**. Otra opción es usar un plan de pago de Render, que no bloquea SMTP.

---

## 4. Conectar MySQL

1. Crear una base MySQL 8 en un proveedor externo accesible desde Internet (Aiven, TiDB Cloud, Railway, un VPS, etc.). La base de Laragon **no** es accesible desde Render.
2. Permitir conexiones desde Render: abrir el acceso público con TLS, o añadir a la lista blanca las IP de salida que muestra Render en *Settings → Outbound IPs*.
3. Cargar las variables `DB_*` (§3) en Render.
4. **Migraciones.** Nunca se ejecutan automáticamente. Antes de hacerlo hace falta:
   - un **respaldo** de la base real;
   - la **autorización expresa** del propietario.

   Después, desde la *Shell* del servicio en Render: `php artisan migrate:status` (solo lectura) y, si procede, `php artisan migrate --force`.

   **Prohibido** en la base real: `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe`, `db:seed` (incluido `DemoSeeder`, que además se niega a ejecutarse en `production`), `TRUNCATE`, `DROP`.

### Migraciones nuevas que debe recibir una base existente

Las tres son aditivas.

| Migración | Efecto |
|---|---|
| `2026_11_06_000000_add_email_verified_at_to_usuarios_table` | Añade `usuarios.email_verified_at` (nullable). No hace nada si la columna ya existe. |
| `2026_11_07_000001_create_parametros_table` | Crea `parametros` e inserta la comisión inicial del 0% (`insertOrIgnore`, idempotente) |
| `2026_11_07_000002_create_pagos_table` | Crea `pagos`, para los pagos **simulados** |

> **Atención: verificación de correo en usuarios existentes.** Tras añadir `email_verified_at`, los usuarios que ya existían quedan **sin verificar**. El middleware `verified` les pedirá verificar su correo antes de entrar a los dashboards, el administrador incluido. Antes de migrar hay que decidir entre:
> - (a) tener el correo real funcionando para que cada usuario verifique;
> - (b) marcar como verificados a los usuarios existentes con una actualización controlada. Es una modificación de datos reales, así que requiere respaldo y autorización.
>
> No se ha hecho ninguna de las dos.

Tablas de infraestructura que deben existir porque las crean las migraciones base: `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `password_reset_tokens` y `personal_access_tokens`.

---

## 5. HTTPS detrás del proxy de Render

- Render termina TLS y reenvía la petición al contenedor por HTTP, con `X-Forwarded-Proto: https`.
- `TRUSTED_PROXIES=*` (en `config/trustedproxy.php`) hace que Laravel acepte esa cabecera. El efecto es que `request()->secure()` es verdadero, las cookies salen con `Secure` y los enlaces firmados de verificación se validan sobre `https://`.
- Solo se aceptan `X-Forwarded-For` y `X-Forwarded-Proto` (`bootstrap/app.php`). El host y el puerto salen de la cabecera `Host`, así que un `X-Forwarded-Host` falsificado no altera los enlaces de los correos.
- `AppServiceProvider` fuerza `https` en las URL generadas fuera del entorno `local`, leyendo `config('app.force_https')` (variable `HAS_SSL`).
- En Laragon (`APP_ENV=local`, sin `TRUSTED_PROXIES`) todo sigue por HTTP y se ignoran las cabeceras `X-Forwarded-*`.

---

## 6. Primer despliegue

1. Subir la rama a GitHub. Requiere autorización del propietario y no se hace desde el asistente.
2. En Render: *New → Web Service → Build and deploy from a Git repository →* elegir el repositorio y la rama, con runtime **Docker**.
3. Cargar todas las variables de §3 antes del primer despliegue y poner *Health Check Path* = `/up`.
4. *Create Web Service*. En los logs de construcción deben aparecer, en este orden:
   - la generación de fuentes (`>>> Downloading the font sources` y la conversión);
   - `npm ci`;
   - `vite build` y `✓ built in …`.
5. En los logs de arranque: `Configuration cached`, `Routes cached`, `Blade templates cached` y `[redwork] Iniciando Apache en 0.0.0.0:10000`.
6. Migraciones: solo si la base es nueva o le faltan las de §4, y con respaldo y autorización.
7. Crear la cuenta de administrador **a mano** (no hay seeders de producción) o usar la que ya exista en la base real.

## 7. Verificación tras desplegar

**Infraestructura**

- [ ] `https://<servicio>/up` responde 200.
- [ ] `https://<servicio>/.env`, `/composer.json`, `/vendor/autoload.php` y `/storage/logs/laravel.log` responden 404.
- [ ] En el navegador (F12 → Red): ningún 404 ni recurso `http://`, `localhost` o `:5173`. Los CSS y JS salen de `/build/assets/…`.
- [ ] La cookie `laravel-session` tiene `Secure`, `HttpOnly` y `SameSite=Lax`.

**Usuario público**

- [ ] Página de inicio.
- [ ] Registro: edad mínima de 18 años y contraseña de 12+ caracteres con mayúscula, número y símbolo.
- [ ] Llega el correo de verificación y el enlace `https://` verifica la cuenta.
- [ ] Inicio y cierre de sesión. El bloqueo tras el 6.º intento fallido dura 15 minutos.
- [ ] Recuperación de contraseña: llega el correo y el enlace permite cambiarla.

**Administrador**

- [ ] Dashboard.
- [ ] Usuarios y roles.
- [ ] Categorías.
- [ ] Publicaciones.
- [ ] Confirmar y anular pagos **simulados**.
- [ ] Calificaciones (Parametrización).
- [ ] Reportes: descarga de los 5 PDF (usuarios, publicaciones, categorías, actividad y pagos simulados), con acentos legibles.
- [ ] Business Intelligence: el Dashboard de KPIs carga sus gráficos.

**Contratador**

- [ ] Publicaciones.
- [ ] Postulaciones recibidas (aceptar o rechazar).
- [ ] Contrataciones.
- [ ] Registrar un pago simulado.
- [ ] Calificaciones.
- [ ] Mis Indicadores.
- [ ] Sin acceso a `/admin/kpis` ni `/reportes` (403).

**Trabajador**

- [ ] Buscar trabajos (pestañas Todos, Recurrentes, Temporales, Ciudad y Mejor pagados).
- [ ] Postular.
- [ ] Mi Seguimiento.
- [ ] Mis pagos, en solo lectura.
- [ ] Calificaciones.
- [ ] Perfil.

**Interfaz**

- [ ] Interruptor ☀️/🌙 en los 4 dashboards.
- [ ] La preferencia se conserva al recargar y al cambiar entre administrador y BI.
- [ ] Menú móvil a 375 px.
- [ ] Modales y formularios en ambos temas.

> Las pruebas que modifican datos (registros, pagos, calificaciones) se hacen con **cuentas de prueba**, nunca con datos reales sin autorización.

## 8. Revertir un despliegue fallido

1. Render → servicio → *Events* / *Deploys* → elegir el último despliegue correcto → **Rollback**. Se reutiliza esa imagen sin reconstruir.
2. Si el fallo vino de una variable de entorno, corregirla y pulsar *Manual Deploy → Deploy latest commit*.
3. Las migraciones **no** se revierten solas. Si se ejecutó alguna, se restaura el respaldo previo de la base solo con autorización. No se usa `migrate:rollback` sobre datos reales sin analizarlo antes.
4. `APP_KEY` no se cambia durante un rollback.

## 9. Comprobar TCPDF

- Durante la construcción, si faltan las fuentes el despliegue **falla** con `test -s …/helvetica.json` y no llega a publicarse una imagen sin ellas.
- En producción hay dos comprobaciones:
  - entrar como administrador, ir a Reportes y descargar cada PDF. Debe abrir un archivo `RedWork_Reporte_<Tipo>_<AAAAMMDD>.pdf` que empieza por `%PDF-`;
  - desde la *Shell* de Render: `test -s vendor/tecnickcom/tc-lib-pdf-font/target/fonts/core/helvetica.json && echo OK`.

## 10. Riesgos y limitaciones conocidas

- **Disco efímero.** Todo lo que se escriba en `storage/` se pierde al redesplegar. REDWORK no guarda archivos de usuario; sesiones y caché van a MySQL y los logs a `stderr`.
- **Tokens del dashboard de administrador.** Se crea un token Sanctum sin expiración en cada carga. Es un defecto conocido pendiente de autorización. Los de KPIs caducan a los 120 minutos y los de reportes a los 60.
- **Plan gratuito de Render.** El servicio se suspende tras 15 minutos sin tráfico y el primer acceso tarda en despertar. Conviene abrirlo unos minutos antes de la defensa.
- **Hora de los reportes.** La zona horaria de la aplicación es UTC (`config/app.php`), igual que en local.
