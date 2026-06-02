-- =============================================================================
-- Cart Service — PostgreSQL Database Setup
-- Run this in pgAdmin 4 Query Tool or psql
-- =============================================================================

-- 1. Create the database (run as postgres superuser)
CREATE DATABASE cart_service_db
    WITH ENCODING = 'UTF8'
    LC_COLLATE  = 'en_US.UTF-8'
    LC_CTYPE    = 'en_US.UTF-8'
    TEMPLATE    = template0;

-- 2. Connect to cart_service_db, then run the rest:
-- \c cart_service_db   ← (psql command — skip in pgAdmin, just switch DB)

-- =============================================================================
-- ERD Tables
-- =============================================================================

-- Table: carts
-- ERD: id PK | user_id (UNIQUE) | updated_at
CREATE TABLE IF NOT EXISTS carts (
    id         BIGSERIAL    PRIMARY KEY,
    user_id    BIGINT       NOT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NOW(),

    CONSTRAINT carts_user_id_unique UNIQUE (user_id)
);

COMMENT ON TABLE  carts            IS 'One active cart per user (user_id from Auth Service)';
COMMENT ON COLUMN carts.user_id    IS 'Cross-service reference to Auth Service users.id';

-- Table: cart_products
-- ERD: id PK | updated_at | product_name | product_image | shop_name | shop_id | product_id FK
CREATE TABLE IF NOT EXISTS cart_products (
    id            BIGSERIAL    PRIMARY KEY,
    cart_id       BIGINT       NOT NULL,
    product_id    BIGINT       NOT NULL,
    product_name  VARCHAR(255) NOT NULL,
    product_image VARCHAR(512),
    shop_name     VARCHAR(255) NOT NULL,
    shop_id       BIGINT       NOT NULL,
    unit_price    BIGINT       NOT NULL,     -- IDR, e.g. 18500000 = Rp 18.500.000
    quantity      INTEGER      NOT NULL DEFAULT 1 CHECK (quantity >= 1),
    created_at    TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NOW(),
    updated_at    TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NOW(),

    CONSTRAINT cart_products_cart_id_fk
        FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,

    -- One product per cart (add again → update quantity, not duplicate row)
    CONSTRAINT cart_products_cart_product_unique
        UNIQUE (cart_id, product_id)
);

COMMENT ON TABLE  cart_products               IS 'Products inside a cart (cart_products ERD table)';
COMMENT ON COLUMN cart_products.product_id    IS 'Cross-service ref to Product Service';
COMMENT ON COLUMN cart_products.shop_id       IS 'Cross-service ref to Product Service shops';
COMMENT ON COLUMN cart_products.unit_price    IS 'Price snapshot in IDR at time of adding';
COMMENT ON COLUMN cart_products.quantity      IS 'Number of units';

-- Indexes for common query patterns
CREATE INDEX IF NOT EXISTS idx_cart_products_cart_id   ON cart_products(cart_id);
CREATE INDEX IF NOT EXISTS idx_cart_products_product_id ON cart_products(product_id);
CREATE INDEX IF NOT EXISTS idx_carts_user_id           ON carts(user_id);

-- =============================================================================
-- Laravel system tables (migrations tracker, cache, jobs)
-- These are created automatically by: php artisan migrate
-- Listed here for reference only.
-- =============================================================================
-- migrations  (laravel migration tracker)
-- cache        (if CACHE_STORE=database)
-- jobs         (if QUEUE_CONNECTION=database)
-- failed_jobs

-- =============================================================================
-- Demo seed data (matches CartSeeder.php)
-- =============================================================================
INSERT INTO carts (user_id) VALUES (1), (2);

INSERT INTO cart_products
    (cart_id, product_id, product_name, product_image, shop_name, shop_id, unit_price, quantity)
VALUES
    (1, 1,  'Laptop Gaming ROG',       NULL, 'Elektronik Maju',        10, 18500000, 1),
    (1, 5,  'Kopi Arabika Specialty',  NULL, 'Kedai Kopi Nusantara',   11,    85000, 3),
    (2, 3,  'Sepatu Nike Air Max',     NULL, 'Toko Sepatu Kece',       12,  1250000, 1);

-- =============================================================================
-- Verification queries
-- =============================================================================
SELECT
    c.id        AS cart_id,
    c.user_id,
    COUNT(cp.id)                                             AS product_count,
    SUM(cp.unit_price * cp.quantity)                         AS subtotal_idr,
    'Rp ' || TO_CHAR(SUM(cp.unit_price * cp.quantity), 'FM999,999,999,999') AS subtotal_fmt
FROM carts c
LEFT JOIN cart_products cp ON cp.cart_id = c.id
GROUP BY c.id, c.user_id
ORDER BY c.id;
