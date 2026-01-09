FROM composer:2 AS vendor
WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1

COPY backend/ ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader


FROM php:8.3-cli-alpine AS app
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

COPY --from=vendor /app /var/www/html
COPY docker/backend/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

EXPOSE 8000

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

