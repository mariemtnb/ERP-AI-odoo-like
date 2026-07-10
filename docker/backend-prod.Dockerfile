# Production image for the Laravel API — FrankenPHP (Caddy-based, multi-threaded).
FROM composer:2 AS deps
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --optimize-autoloader --no-autoloader
COPY backend/ .
RUN composer dump-autoload --optimize --no-dev

FROM dunglas/frankenphp:1-php8.4
RUN install-php-extensions pdo_pgsql pgsql zip opcache

WORKDIR /app
COPY --from=deps /app /app
RUN chown -R www-data:www-data storage bootstrap/cache

ENV APP_ENV=production \
    APP_DEBUG=false

EXPOSE 8000
# Cache config/routes at boot (env is only available then), migrate, serve.
CMD ["sh", "-c", "php artisan config:cache && php artisan route:cache && php artisan migrate --force && frankenphp php-server --root public --listen :8000"]
