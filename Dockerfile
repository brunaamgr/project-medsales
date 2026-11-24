# Imagem só para teste local. Produção é ValueHost (sem Docker); Apache aqui imita o cPanel.
FROM composer:latest AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci --ignore-scripts
COPY --from=vendor /app/vendor ./vendor
COPY vite.config.js ./
COPY resources ./resources
COPY app/Modules ./app/Modules
RUN npm run build

FROM php:8.3-apache
RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libzip-dev libpng-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql intl gd zip bcmath opcache \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/* \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\n AllowOverride All\n Require all granted\n Options -Indexes\n</Directory>\nServerTokens Prod\n' > /etc/apache2/conf-enabled/medsales.conf \
    && printf 'expose_php=Off\nmemory_limit=256M\n' > /usr/local/etc/php/conf.d/medsales.ini
WORKDIR /var/www/html
COPY --from=vendor /app ./
COPY --from=assets /app/public/build ./public/build
COPY docker/entrypoint.sh /usr/local/bin/medsales-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/medsales-entrypoint \
    && chmod +x /usr/local/bin/medsales-entrypoint \
    && chown -R www-data:www-data storage bootstrap/cache
ENTRYPOINT ["medsales-entrypoint"]
CMD ["apache2-foreground"]
