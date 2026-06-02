#!/bin/bash
# =============================================================================
# Cart Service — Docker Entrypoint
# Runs on every container start. Handles first-time setup automatically.
# =============================================================================
set -e

echo ""
echo "╔══════════════════════════════════════════╗"
echo "║       NexaMarket — Cart Service          ║"
echo "║       PHP Laravel  |  PostgreSQL         ║"
echo "╚══════════════════════════════════════════╝"
echo ""

# ── 1. Set up .env if not present ─────────────────────────────────────────────
if [ ! -f ".env" ]; then
    echo "[entrypoint] No .env found — copying from .env.docker"
    cp .env.docker .env
fi

# ── 2. Generate app key if not set ────────────────────────────────────────────
if grep -q "^APP_KEY=$" .env || grep -q "^APP_KEY=base64:WILL" .env; then
    echo "[entrypoint] Generating application key..."
    php artisan key:generate --force
fi

# ── 3. Wait for PostgreSQL to be ready ────────────────────────────────────────
echo "[entrypoint] Waiting for PostgreSQL at ${DB_HOST}:${DB_PORT}..."
until php -r "
    try {
        \$pdo = new PDO(
            'pgsql:host=${DB_HOST};port=${DB_PORT};dbname=${DB_DATABASE}',
            '${DB_USERNAME}',
            '${DB_PASSWORD}'
        );
        echo 'connected';
    } catch (Exception \$e) {
        exit(1);
    }
" 2>/dev/null | grep -q 'connected'; do
    echo "[entrypoint]   PostgreSQL not ready — retrying in 2s..."
    sleep 2
done
echo "[entrypoint] ✅ PostgreSQL is ready."

# ── 4. Run migrations ─────────────────────────────────────────────────────────
echo "[entrypoint] Running migrations..."
php artisan migrate --force

# ── 5. Seed if SEED_ON_START=true (default: false) ────────────────────────────
if [ "${SEED_ON_START}" = "true" ]; then
    echo "[entrypoint] Seeding database..."
    php artisan db:seed --force
fi

# ── 6. Clear & cache config for performance ───────────────────────────────────
echo "[entrypoint] Caching config and routes..."
php artisan config:cache
php artisan route:cache

echo ""
echo "[entrypoint] 🚀 Starting cart-service on port 8001..."
echo ""

# ── 7. Start the server ───────────────────────────────────────────────────────
exec php artisan serve --host=0.0.0.0 --port=8001
