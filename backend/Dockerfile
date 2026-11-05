# Backend (Laravel 12 + PHP 8.4) para Dokploy.
#
# FrankenPHP: PHP y servidor web (Caddy) en una sola imagen. Escucha HTTP en
# el puerto 80; el HTTPS lo pone Traefik (Dokploy) por delante.

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative --no-scripts

FROM dunglas/frankenphp:1-php8.4-bookworm

# gd con WebP y AVIF (fotos de productos y sus miniaturas), MySQL, soap
# (Greenter envía los comprobantes a SUNAT por SOAP), etc.
RUN install-php-extensions gd pdo_mysql intl zip bcmath exif opcache pcntl soap \
    # Si GD quedó sin AVIF, las fotos AVIF se quedarían sin miniatura; sin
    # SoapClient no sale ningún comprobante: que falle el build y no producción.
    && php -r 'exit(function_exists("imageavif") && function_exists("imagewebp") && class_exists("SoapClient") ? 0 : 1);'

# mysqldump para los backups de la base (spatie/laravel-backup).
RUN apt-get update \
    && apt-get install -y --no-install-recommends default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-nexo.ini
COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/nexo-entrypoint
RUN chmod +x /usr/local/bin/nexo-entrypoint

WORKDIR /app
COPY --from=vendor /app /app

RUN mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

ENV SERVER_NAME=:80 \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1/up") === false ? 1 : 0);'

ENTRYPOINT ["nexo-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
