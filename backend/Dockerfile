FROM php:8.1-apache

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    PORT=10000 \
    COMPOSER_ALLOW_SUPERUSER=1 \
    EMBEDDING_MODEL=text-embedding-3-small \
    CHAT_MODEL=gpt-4o-mini \
    JWT_SECRET_KEY=/var/www/html/config/jwt/private.pem \
    JWT_PUBLIC_KEY=/var/www/html/config/jwt/public.pem \
    MYSQL_SSL_CA=/var/www/html/config/ssl/aiven-ca.pem

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libonig-dev \
        libxml2-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" curl dom mbstring opcache pdo_mysql zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock symfony.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --no-autoloader

COPY . .
COPY .env.example .env

RUN composer dump-autoload \
    --no-dev \
    --classmap-authoritative \
    --no-interaction \
    --no-scripts \
    && mkdir -p var/cache var/log config/jwt config/ssl \
    && chown -R www-data:www-data var config/jwt config/ssl

COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/render-entrypoint.sh /usr/local/bin/render-entrypoint
RUN chmod +x /usr/local/bin/render-entrypoint

EXPOSE 10000

ENTRYPOINT ["render-entrypoint"]
CMD ["apache2-foreground"]
