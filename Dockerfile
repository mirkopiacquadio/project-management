# syntax=docker/dockerfile:1

# ---------- Frontend assets ----------
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./

RUN npm ci

COPY . .

RUN npm run build

# ---------- PHP application (app / queue) ----------
FROM php:8.3-fpm AS app

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

COPY --from=assets /app/public/build ./public/build

RUN chmod +x docker/*.sh

RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader

# storage symlink baked into the image so the nginx stage (which copies public/
# from here) can serve /storage; target lives in the storage volume at runtime
RUN ln -sfn ../storage/app/public public/storage

RUN chown -R www-data:www-data /var/www

ENTRYPOINT ["docker/entrypoint.sh"]

CMD ["php-fpm"]

# ---------- Web server (nginx) ----------
FROM nginx:alpine AS nginx

WORKDIR /var/www

# public/ (compiled assets in public/build + the storage symlink) taken straight
# from the app stage, so nginx and php-fpm always serve matching assets
COPY --from=app /var/www/public ./public

COPY docker/nginx/conf.d/app.conf /etc/nginx/conf.d/default.conf
