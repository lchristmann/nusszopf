# syntax=docker/dockerfile:1

# Nusszopf 2 application image.
#
# Stages:
#   base      - PHP 8.5-FPM Alpine + the extensions Laravel/Scout/Redis need.
#   dev       - base + Composer + Node, used by every *dev* compose service
#               (workspace, php-fpm, queue-worker, scheduler) via bind mounts.
#   vendor    - production Composer install (no dev deps), source baked in.
#   frontend  - Vite asset build, isolated from the PHP stages.
#   php-fpm   - production runtime image: vendor + built assets, non-root; its
#               entrypoint migrates and caches before PHP-FPM starts.
#   nginx     - production web image, built FROM the php-fpm stage's own
#               /var/www/public so the two images can never drift out of sync
#               (see docs/deployment/README.md, "Why a dedicated nginx image").

ARG PHP_VERSION=8.5

FROM php:${PHP_VERSION}-fpm-alpine AS base

RUN apk add --no-cache \
        postgresql-dev \
        icu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        oniguruma-dev \
        fcgi \
        $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install pdo_pgsql pgsql bcmath intl zip gd exif pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

RUN addgroup -g 1000 nusszopf && adduser -D -u 1000 -G nusszopf nusszopf

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-nusszopf.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www

# ---------------------------------------------------------------------------
FROM base AS dev

RUN apk add --no-cache bash git unzip nodejs npm postgresql-client linux-headers $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN pecl install xdebug
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/99-xdebug.ini

CMD ["php-fpm"]

# ---------------------------------------------------------------------------
FROM base AS vendor

RUN apk add --no-cache unzip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi

# A relative symlink, so it resolves inside any container that mounts
# `laravel-storage` at `storage/app` — the `php-fpm`/`queue-worker`/
# `scheduler` services always do (docker-compose.yaml); `web` mounts it
# read-only so nginx can serve `/storage/...` (avatars) as a static file
# without a PHP round trip (docs/deployment/README.md, "Persistent storage").
RUN ln -sfn ../storage/app/public public/storage

# ---------------------------------------------------------------------------
FROM node:22-alpine AS frontend

WORKDIR /var/www

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
COPY --from=vendor /var/www/vendor ./vendor
RUN npm run build

# ---------------------------------------------------------------------------
FROM base AS php-fpm

# The release this image was built from (docs/rewrite/architecture-decisions.md, "Version exposure"):
# the release workflow passes the Git tag; a local build reports "dev".
ARG NUSSZOPF_VERSION=dev
ENV NUSSZOPF_VERSION=${NUSSZOPF_VERSION}
LABEL org.opencontainers.image.title="Nusszopf" \
      org.opencontainers.image.description="Nusszopf application (PHP-FPM, queue worker and scheduler)" \
      org.opencontainers.image.source="https://github.com/lchristmann/nusszopf" \
      org.opencontainers.image.licenses="GPL-3.0-or-later" \
      org.opencontainers.image.version="${NUSSZOPF_VERSION}"

COPY --from=vendor --chown=nusszopf:nusszopf /var/www /var/www
COPY --from=frontend --chown=nusszopf:nusszopf /var/www/public/build /var/www/public/build

RUN mkdir -p storage/framework/{cache,sessions,testing,views} storage/logs bootstrap/cache \
    && chown -R nusszopf:nusszopf storage bootstrap/cache

COPY --chmod=755 docker/php/entrypoint.sh /usr/local/bin/nusszopf-entrypoint

USER nusszopf

ENTRYPOINT ["nusszopf-entrypoint"]

HEALTHCHECK --interval=10s --timeout=3s --start-period=20s --retries=5 \
    CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 || exit 1

CMD ["php-fpm"]

# ---------------------------------------------------------------------------
FROM nginx:alpine AS nginx

ARG NUSSZOPF_VERSION=dev
LABEL org.opencontainers.image.title="Nusszopf web" \
      org.opencontainers.image.description="Nusszopf web server (nginx, static assets, PHP-FPM proxy)" \
      org.opencontainers.image.source="https://github.com/lchristmann/nusszopf" \
      org.opencontainers.image.licenses="GPL-3.0-or-later" \
      org.opencontainers.image.version="${NUSSZOPF_VERSION}"

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=php-fpm /var/www/public /var/www/public

HEALTHCHECK --interval=10s --timeout=3s --start-period=10s --retries=5 \
    CMD wget -qO- http://127.0.0.1/up || exit 1
