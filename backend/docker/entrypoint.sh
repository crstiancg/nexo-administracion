#!/bin/sh
# Arranque del backend en cada deploy: deja la base y los cachés al día y
# después levanta el servidor (CMD).
set -e

cd /app

if [ -z "$APP_KEY" ]; then
  echo "Falta APP_KEY (generala con: php artisan key:generate --show)" >&2
  exit 1
fi

# storage/ es un volumen persistente: en el primer arranque viene vacío.
mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

# Llaves de Passport: por variable de entorno o, si no hay, archivos en el
# volumen (se generan una sola vez; regenerarlas invalida todas las sesiones).
if [ -z "$PASSPORT_PRIVATE_KEY" ] && [ ! -f storage/oauth-private.key ]; then
  php artisan passport:keys --no-interaction
fi

chown -R www-data:www-data storage bootstrap/cache

php artisan storage:link --force --no-interaction >/dev/null 2>&1 || true

# Espera a la base (MySQL puede tardar en aceptar conexiones tras un reinicio).
intentos=0
until php artisan db:show --no-interaction >/dev/null 2>&1; do
  intentos=$((intentos + 1))
  if [ "$intentos" -ge 30 ]; then
    echo "No se pudo conectar a la base de datos" >&2
    exit 1
  fi
  echo "Esperando la base de datos ($intentos)..."
  sleep 2
done

php artisan migrate --force --no-interaction
# Los permisos salen de las rutas: una ruta nueva necesita su permiso.
php artisan permisos:sync --no-interaction

# El build no corre los scripts de composer: el manifiesto de paquetes se
# arma acá (Passport, Spatie…).
php artisan package:discover --ansi

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
