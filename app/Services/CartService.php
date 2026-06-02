<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * CartService
 *
 * All business logic for the Cart microservice.
 * Database schema follows the ERD:
 *   carts        (id, user_id UNIQUE, timestamps)
 *   cart_products (id, cart_id FK, product_id, product_name, product_image,
 *                  shop_name, shop_id, unit_price, quantity, timestamps)
 */
class CartService
{
    /**
     * Get (or lazily create) the active cart for a user.
     * Since user_id is UNIQUE on carts, there is always exactly one cart per user.
     */
    public function getOrCreateCart(int $userId): Cart
    {
        $cart = Cart::where('user_id', $userId)
                    ->with('products')
                    ->first();

        if (!$cart) {
            $cart = Cart::create(['user_id' => $userId]);
            $cart->setRelation('products', collect());
        }

        return $cart;
    }

    /**
     * Add a product to the cart.
     *
     * - Calls Product Service to validate stock and fetch product details
     *   (product_name, product_image, shop_name, shop_id, price).
     * - If the product is already in the cart → increment quantity (upsert).
     * - If new → create a CartProduct row with a price snapshot.
     *
     * @param  int   $userId
     * @param  array $data  { product_id, quantity }
     * @return array { cart, product, message }
     * @throws \RuntimeException  on stock insufficient / product not found
     */
    public function addProduct(int $userId, array $data): array
    {
        $productData = $this->fetchProduct($data['product_id']);

        if (!$productData) {
            throw new \RuntimeException('Produk tidak ditemukan.', 404);
        }

        if (($productData['stock'] ?? 0) < $data['quantity']) {
            throw new \RuntimeException(
                "Stok tidak mencukupi. Tersisa {$productData['stock']} unit.", 422
            );
        }

        return DB::transaction(function () use ($userId, $data, $productData) {
            $cart = $this->getOrCreateCart($userId);

            $existing = $cart->products()
                             ->where('product_id', $data['product_id'])
                             ->first();

            if ($existing) {
                $newQty = $existing->quantity + $data['quantity'];

                if (($productData['stock'] ?? 0) < $newQty) {
                    throw new \RuntimeException(
                        "Stok tidak mencukupi. Tersisa {$productData['stock']} unit.", 422
                    );
                }

                $existing->update(['quantity' => $newQty]);
                $product = $existing;
                $message = 'Jumlah produk di keranjang diperbarui.';
            } else {
                $product = $cart->products()->create([
                    'product_id'    => $productData['id'],
                    'product_name'  => $productData['name'],
                    'product_image' => $productData['image_url'] ?? null,
                    'shop_name'     => $productData['shop_name'] ?? 'Unknown Shop',
                    'shop_id'       => $productData['shop_id']   ?? 0,
                    'unit_price'    => $productData['price'],
                    'quantity'      => $data['quantity'],
                ]);
                $message = 'Produk berhasil ditambahkan ke keranjang.';
            }

            $cart->load('products');
            return compact('cart', 'product', 'message');
        });
    }

    /**
     * Update the quantity of a product in the cart.
     *
     * @throws \RuntimeException  on not found or stock insufficient
     */
    public function updateProduct(int $userId, int $productRowId, int $quantity): CartProduct
    {
        $cartProduct = $this->findProductOrFail($userId, $productRowId);

        $productData = $this->fetchProduct($cartProduct->product_id);
        if ($productData && ($productData['stock'] ?? 0) < $quantity) {
            throw new \RuntimeException(
                "Stok tidak mencukupi. Tersisa {$productData['stock']} unit.", 422
            );
        }

        $cartProduct->update(['quantity' => $quantity]);
        return $cartProduct->fresh();
    }

    /**
     * Remove one product row from the cart.
     *
     * @throws \RuntimeException  on not found
     */
    public function removeProduct(int $userId, int $productRowId): void
    {
        $this->findProductOrFail($userId, $productRowId)->delete();
    }

    /**
     * Remove all products from the user's cart (keep the cart record).
     */
    public function clearCart(int $userId): Cart
    {
        $cart = $this->getOrCreateCart($userId);
        $cart->products()->delete();
        return $cart->fresh(['products']);
    }

    /**
     * Checkout: forward cart contents to Order Service, then wipe cart products.
     *
     * Flow:
     *  1. Load cart with products — fail if empty.
     *  2. Build order payload and POST to Order Service via API Gateway.
     *  3. On success → delete all cart_products (cart record stays for history).
     *  4. Return the order reference from Order Service.
     *
     * @param  int    $userId
     * @param  array  $data { shipping_address, payment_method, notes? }
     * @param  string $bearerToken  forwarded to Order Service
     * @return array  Order Service JSON response
     * @throws \RuntimeException
     */
    public function checkout(int $userId, array $data, string $bearerToken): array
    {
        $cart = $this->getOrCreateCart($userId);

        if ($cart->products->isEmpty()) {
            throw new \RuntimeException('Keranjang kosong, tidak dapat checkout.', 422);
        }

        $orderPayload = [
            'user_id'          => $userId,
            'cart_id'          => $cart->id,
            'items'            => $cart->products->map(fn (CartProduct $p) => [
                'product_id'   => $p->product_id,
                'product_name' => $p->product_name,
                'product_image'=> $p->product_image,
                'shop_id'      => $p->shop_id,
                'shop_name'    => $p->shop_name,
                'unit_price'   => $p->unit_price,
                'quantity'     => $p->quantity,
                'subtotal'     => $p->subtotal,
            ])->toArray(),
            'subtotal'         => $cart->subtotal,
            'total'            => $cart->subtotal,    // no discount in ERD schema
            'shipping_address' => $data['shipping_address'],
            'payment_method'   => $data['payment_method'],
            'notes'            => $data['notes'] ?? null,
        ];

        $response = Http::withToken($bearerToken)
            ->timeout(10)
            ->post(config('services.order_service.url') . '/orders', $orderPayload);

        if ($response->failed()) {
            Log::error('Checkout failed — Order Service error', [
                'status'  => $response->status(),
                'body'    => $response->body(),
                'user_id' => $userId,
            ]);
            $msg = $response->json('message') ?? 'Order Service tidak merespons.';
            throw new \RuntimeException($msg, $response->status());
        }

        // Clear products from cart after successful order (cart record stays)
        DB::transaction(fn () => $cart->products()->delete());

        return $response->json();
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Find a CartProduct that belongs to the given user's cart.
     * Throws 404 if not found or if it belongs to another user.
     */
    private function findProductOrFail(int $userId, int $productRowId): CartProduct
    {
        $cartProduct = CartProduct::whereHas(
            'cart', fn ($q) => $q->where('user_id', $userId)
        )->find($productRowId);

        if (!$cartProduct) {
            throw new \RuntimeException('Item keranjang tidak ditemukan.', 404);
        }

        return $cartProduct;
    }

    /**
     * Fetch product details from Product Service via API Gateway.
     * Returns null if the service is unreachable or product doesn't exist.
     */
    /**
     * Fetch product details from Product Service via API Gateway.
     */
    private function fetchProduct(int $productId): ?array
    {
        $url = config('services.product_service.url') . "/{$productId}";
        
        Log::info("CartService: Sedang memanggil Product Service", [
            'product_id' => $productId,
            'target_url' => $url
        ]);

        try {
            // Tambahkan header X-Service-Password di sini
            $response = Http::timeout(5)
                ->withHeaders([
                    'x-service-password' => env('GATEWAY_PASSWORD', 'passwordAPIGateway')
                ])
                ->get($url);

            if ($response->successful()) {
                return $response->json('data') ?? $response->json();
            } else {
                Log::error("CartService: Product Service mengembalikan error", [
                    'product_id' => $productId,
                    'status'     => $response->status(),
                    'response'   => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error("CartService: Gagal memanggil Product Service", [
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }
}
