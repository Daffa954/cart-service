<?php

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\CartProduct;
use Illuminate\Database\Seeder;

/**
 * CartSeeder — ERD schema
 *
 * Seeds demo data for local development using the ERD tables:
 *   carts         (id, user_id UNIQUE, timestamps)
 *   cart_products (id, cart_id, product_id, product_name, product_image,
 *                  shop_name, shop_id, unit_price, quantity, timestamps)
 *
 * Run: php artisan db:seed
 */
class CartSeeder extends Seeder
{
    public function run(): void
    {
        // ── Cart for user 1 (buyer: budi@example.com) ─────────────────────────
        $cart1 = Cart::create(['user_id' => 1]);

        CartProduct::create([
            'cart_id'       => $cart1->id,
            'product_id'    => 1,
            'product_name'  => 'Laptop Gaming ROG',
            'product_image' => null,
            'shop_name'     => 'Elektronik Maju',
            'shop_id'       => 10,
            'unit_price'    => 18500000,
            'quantity'      => 1,
        ]);

        CartProduct::create([
            'cart_id'       => $cart1->id,
            'product_id'    => 5,
            'product_name'  => 'Kopi Arabika Specialty',
            'product_image' => null,
            'shop_name'     => 'Kedai Kopi Nusantara',
            'shop_id'       => 11,
            'unit_price'    => 85000,
            'quantity'      => 3,
        ]);

        // ── Cart for user 2 (another buyer) ───────────────────────────────────
        $cart2 = Cart::create(['user_id' => 2]);

        CartProduct::create([
            'cart_id'       => $cart2->id,
            'product_id'    => 3,
            'product_name'  => 'Sepatu Nike Air Max',
            'product_image' => null,
            'shop_name'     => 'Toko Sepatu Kece',
            'shop_id'       => 12,
            'unit_price'    => 1250000,
            'quantity'      => 1,
        ]);

        $this->command->info('✅ CartSeeder — 2 carts, 3 cart_products created');
        $this->command->info("   Cart #{$cart1->id} → user_id=1  (subtotal: Rp 18.755.000)");
        $this->command->info("   Cart #{$cart2->id} → user_id=2  (subtotal: Rp 1.250.000)");
    }
}
