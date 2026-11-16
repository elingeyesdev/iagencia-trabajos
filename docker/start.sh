#!/bin/sh
# REDWORK — arranque del contenedor en Render.
# - Usa las variables de entorno reales del servicio (no hay .env dentro de la imagen).
# - Genera las cachés de Laravel en el arranque, cuando esas variables ya existen.
# - NO ejecuta migraciones, seeders ni key:generate: esas operaciones son manuales y requieren autorización.
set -eu

cd /var/www/html

if [ -z "${APP_KEY:-}" ]; then
    echo "[redwork] ERROR: falta la variable APP_KEY. Configúrala en Render (ver docs/despliegue-render.md)." >&2
    exit 1
fi

# Apache escucha en el puerto que entrega Render (10000 por defecto).
export PORT="${PORT:-10000}"

# Cachés con la configuración real; si alguna falla, se informa y se arranca sin ella (nunca con datos obsoletos).
php artisan config:cache || { echo "[redwork] Aviso: config:cache falló; se continúa sin caché de configuración." >&2; php artisan config:clear; }
php artisan route:cache  || { echo "[redwork] Aviso: route:cache falló; se continúa sin caché de rutas." >&2; php artisan route:clear; }
php artisan view:cache   || { echo "[redwork] Aviso: view:cache falló; se continúa sin caché de vistas." >&2; php artisan view:clear; }

# Los archivos generados por root deben poder leerse y regenerarse por Apache (www-data).
chown -R www-data:www-data storage bootstrap/cache

echo "[redwork] Iniciando Apache en 0.0.0.0:${PORT}"
exec apache2-foreground
