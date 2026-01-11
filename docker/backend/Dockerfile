FROM composer:2 AS vendor_prod
WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1

COPY backend/ ./
RUN mkdir -p bootstrap/cache \
  && chmod -R 777 bootstrap/cache storage
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader


FROM composer:2 AS vendor_dev
WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1

COPY backend/ ./
RUN mkdir -p bootstrap/cache \
  && chmod -R 777 bootstrap/cache storage
# Dev image needs dev deps (phpunit, collision, etc.)
RUN composer install --prefer-dist --no-interaction --no-progress


FROM php:8.3-cli-alpine AS base
WORKDIR /var/www/html

RUN apk add --no-cache \
    bash \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    postgresql-dev \
  && docker-php-ext-install \
    intl \
    mbstring \
    opcache \
    pdo \
    pdo_pgsql \
    zip

FROM base AS app-dev
COPY --from=vendor_dev /app /var/www/html
COPY docker/backend/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
# Some tooling (tests) may try to read .env directly; keep an empty file in dev images.
RUN [ -f .env ] || touch .env
RUN mkdir -p \
    bootstrap/cache \
    storage/app \
    storage/app/public \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
  && chmod -R 777 bootstrap/cache storage

ENV LOG_CHANNEL=stderr

EXPOSE 8000

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--no-reload"]


FROM base AS app
COPY --from=vendor_prod /app /var/www/html
COPY docker/backend/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
RUN mkdir -p \
    bootstrap/cache \
    storage/app \
    storage/app/public \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
  && chmod -R 777 bootstrap/cache storage

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

EXPOSE 8000

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--no-reload"]

