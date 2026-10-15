FROM composer:2 AS dependencies

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --prefer-dist

FROM php:8.4-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY --from=dependencies /app/vendor ./vendor
COPY . .

RUN chmod +x bin/mcp-server \
    && mkdir -p var \
    && chown -R www-data:www-data var

USER www-data

ENTRYPOINT ["php", "server.php"]
