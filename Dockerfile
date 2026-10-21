FROM php:8.4-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libpq-dev libicu-dev libzip-dev libonig-dev libsqlite3-dev \
    && docker-php-ext-install -j$(nproc) pdo_pgsql pdo_sqlite mbstring intl zip bcmath pcntl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    && printf 'upload_max_filesize = 25M\npost_max_size = 32M\n' > "$PHP_INI_DIR/conf.d/uploads.ini"

WORKDIR /var/www/html

COPY --chmod=755 entrypoint.sh /usr/local/bin/app-entrypoint

ENV COMPOSER_ALLOW_SUPERUSER=1

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]
