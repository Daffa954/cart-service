# =============================================================================
# Cart Service — Dockerfile
# Multi-stage build: PHP 8.2 + PostgreSQL + Composer
# =============================================================================

# ── Stage 1: Composer dependency install ─────────────────────────────────────
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# ── Stage 2: Runtime image ────────────────────────────────────────────────────
FROM php:8.4-cli-alpine

LABEL maintainer="NexaMarket Team"
LABEL service="cart-service"

# ── System dependencies ───────────────────────────────────────────────────────
RUN apk add --no-cache \
    libpq-dev \
    libpng-dev \
    zip \
    unzip \
    bash \
    git \
    curl \
    openssl

# ── PHP extensions ────────────────────────────────────────────────────────────
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    bcmath \
    pcntl

# ── Working directory ─────────────────────────────────────────────────────────
WORKDIR /var/www/html

# ── Copy vendor from stage 1 ──────────────────────────────────────────────────
COPY --from=vendor /app/vendor ./vendor

# ── Copy application source ───────────────────────────────────────────────────
COPY . .

# ── Storage & cache permissions ───────────────────────────────────────────────
RUN mkdir -p storage/framework/cache \
             storage/framework/sessions \
             storage/framework/views \
             storage/logs \
             bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# ── Copy and prepare entrypoint ───────────────────────────────────────────────
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# ── Expose port ───────────────────────────────────────────────────────────────
EXPOSE 8001

ENTRYPOINT ["/entrypoint.sh"]
