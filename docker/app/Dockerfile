FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    nginx \
    supervisor \
    sqlite-dev \
    icu-dev \
    oniguruma-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    openssl \
    nodejs \
    npm

RUN docker-php-ext-install \
    pdo_sqlite \
    pdo_mysql \
    intl \
    mbstring \
    zip \
    bcmath \
    pcntl

RUN echo 'upload_max_filesize = 110M' > /usr/local/etc/php/conf.d/uploads.ini \
    && echo 'post_max_size = 110M' >> /usr/local/etc/php/conf.d/uploads.ini

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN composer dump-autoload --optimize
RUN npm run build

RUN mkdir -p /var/log/supervisor

COPY docker/app/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/app/supervisord.conf /etc/supervisord.conf
COPY docker/app/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80 8080

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
