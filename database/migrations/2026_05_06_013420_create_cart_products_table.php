<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_cart_products_table
 *
 * ERD spec:
 *   PK  id
 *       updated_at
 *       product_name
 *       product_image
 *       shop_name
 *       shop_id
 *   FK  product_id  → cross-service ref to Product Service catalog
 *
 *   Relationship: many cart_products belong to ONE cart (carts.id)
 *
 * Extra functional fields (not on ERD but required for cart logic):
 *   - cart_id    : FK to carts.id (the ERD relationship line implies this)
 *   - quantity   : number of units added
 *   - unit_price : price snapshot at time of adding (IDR, stored as integer)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_products', function (Blueprint $table) {
            $table->id();                               // PK

            // FK to carts — the ERD "many → one" line between cart_products and carts
            $table->foreignId('cart_id')
                  ->constrained('carts')
                  ->cascadeOnDelete();

            // ── ERD fields ─────────────────────────────────────────────────────
            // Cross-service reference to Product Service (no real DB FK)
            $table->unsignedBigInteger('product_id');

            $table->string('product_name', 255);

            $table->string('product_image', 512)->nullable();

            $table->string('shop_name', 255);

            $table->unsignedBigInteger('shop_id');

            // ── Functional extras (required for cart operations) ───────────────
            // Price snapshot at the moment the item was added.
            // Stored as integer (IDR, e.g. 18500000 = Rp 18.500.000)
            $table->unsignedBigInteger('unit_price');

            // Quantity of this product in the cart (min 1)
            $table->unsignedInteger('quantity')->default(1);

            // ── Constraints ───────────────────────────────────────────────────
            // A product can only appear once per cart; adding again → update qty
            $table->unique(['cart_id', 'product_id']);

            $table->timestamps();                       // created_at + updated_at

            // ── Indexes ───────────────────────────────────────────────────────
            $table->index('product_id');
            $table->index('shop_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_products');
    }
};
