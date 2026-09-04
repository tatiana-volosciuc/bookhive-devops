# ---- Stage 1: Composer dependencies ----
FROM composer:2 AS composer_deps

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-scripts \
    --no-autoloader \
    --no-interaction \
    --prefer-dist

 COPY . .

RUN composer dump-autoload --optimize \
    && composer run-script post-install-cmd || true

# ---- Stage 2: Runtime image ----
FROM php:8.4-fpm AS runtime

RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        intl \
        zip \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN groupadd -g 1000 appuser \
    && useradd -u 1000 -g appuser -m -s /bin/bash appuser

RUN sed -i \
    -e 's/^user = www-data/user = appuser/' \
    -e 's/^group = www-data/group = appuser/' \
    /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html

COPY --from=composer_deps --chown=appuser:appuser /app ./

RUN mkdir -p var/cache var/log \
    && chown -R appuser:appuser var

USER appuser

EXPOSE 9000

CMD ["php-fpm"]
