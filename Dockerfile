# syntax=docker/dockerfile:1

# ---------- PHP base + dipendenze composer ----------
FROM php:8.3-fpm AS app-base

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    zip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    libzip-dev \
 && docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    intl \
    zip \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

RUN chmod +x docker/*.sh

RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader

# ---------- Frontend assets ----------
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./

RUN npm ci

COPY . .

# il tema Filament importa CSS da vendor/, quindi serve durante il build Vite
COPY --from=app-base /var/www/vendor ./vendor

RUN npm run build

# ---------- Applicazione (app / queue) ----------
FROM app-base AS app

COPY --from=assets /app/public/build ./public/build

# storage symlink nell'immagine, cosi nginx (che copia public/ da qui) serve /storage;
# il target vive nel volume storage a runtime
RUN ln -sfn ../storage/app/public public/storage

RUN chown -R www-data:www-data /var/www

ENTRYPOINT ["docker/entrypoint.sh"]

CMD ["php-fpm"]

# ---------- Web server (nginx) ----------
FROM nginx:alpine AS nginx

WORKDIR /var/www

# public/ (asset compilati in public/build + symlink storage) preso dallo stage app
COPY --from=app /var/www/public ./public

COPY docker/nginx/conf.d/app.conf /etc/nginx/conf.d/default.conf
