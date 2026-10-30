FROM php:8.2-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends libpng-dev libjpeg-dev libwebp-dev libfreetype6-dev libzip-dev \
 && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
 && docker-php-ext-install -j"$(nproc)" mysqli pdo_mysql gd zip opcache \
 && a2enmod rewrite headers expires deflate \
 && rm -rf /var/lib/apt/lists/*

COPY docker/apache-security.conf /etc/apache2/conf-enabled/zz-commerza-security.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-commerza.ini
COPY --chown=www-data:www-data . /var/www/html

RUN rm -rf /var/www/html/.git /var/www/html/.env /var/www/html/storage \
 && mkdir -p /var/www/html/storage/mail && chown -R www-data:www-data /var/www/html/storage

HEALTHCHECK --interval=10s --timeout=5s --retries=12 CMD curl -fsS -o /dev/null http://localhost/about || exit 1
