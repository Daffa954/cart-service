<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CartTest — Feature Tests for Cart Service API
 *
 * Uses SQLite in-memory (configured in phpunit.xml).
 * Http::fake() intercepts all inter-service HTTP calls.
 *
 * Run: php artisan test --filter CartTest
 */
class CartTest extends TestCase
{
    use RefreshDatabase;

    private int $userId = 1;
    private array $authHeaders;

    private array $fakeProduct = [
        'id'        => 10,
        'name'      => 'Laptop Gaming ROG',
        'price'     => 18500000,
        'stock'     => 15,
        'image_url' => null,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->authHeaders = $this->makeAuthHeaders($this->userId);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // GET /api/cart
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function it_returns_empty_cart_for_new_user(): void
    {
        $response = $this->getJson('/api/cart', $this->authHeaders);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success', 'message',
                     'data' => ['id', 'user_id', 'status', 'item_count', 'subtotal', 'total', 'items'],
                 ])
                 ->assertJson([
                     'success' => true,
                     'data'    => [
                         'user_id'    => $this->userId,
                         'status'     => 'active',
                         'item_count' => 0,
                         'subtotal'   => 0,
                         'items'      => [],
                     ],
                 ]);
    }

    #[Test]
    public function it_returns_401_when_no_token_provided(): void
    {
        $this->getJson('/api/cart')
             ->assertStatus(401)
             ->assertJson(['success' => false]);
    }

    #[Test]
    public function it_returns_same_cart_on_subsequent_calls(): void
    {
        $id1 = $this->getJson('/api/cart', $this->authHeaders)->json('data.id');
        $id2 = $this->getJson('/api/cart', $this->authHeaders)->json('data.id');

        $this->assertEquals($id1, $id2, 'Repeated GET /api/cart should return the same cart ID');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // POST /api/cart/items — Add Item
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function it_adds_a_new_product_to_the_cart(): void
    {
        Http::fake([
            config('services.product_service.url') . '/products/10' => Http::response(
                ['data' => $this->fakeProduct], 200
            ),
        ]);

        $response = $this->postJson('/api/cart/items', [
            'product_id' => 10,
            'quantity'   => 2,
        ], $this->authHeaders);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'data'    => [
                         'item' => [
                             'product_id'   => 10,
                             'product_name' => 'Laptop Gaming ROG',
                             'unit_price'   => 18500000,
                             'quantity'     => 2,
                             'subtotal'     => 37000000,
                         ],
                     ],
                 ]);

        $this->assertDatabaseHas('cart_items', [
            'product_id' => 10,
            'quantity'   => 2,
            'unit_price' => 18500000,
        ]);
    }

    #[Test]
    public function it_increments_quantity_when_same_product_added_again(): void
    {
        Http::fake([
            config('services.product_service.url') . '/products/10' => Http::response(
                ['data' => $this->fakeProduct], 200
            ),
        ]);

        $this->postJson('/api/cart/items', ['product_id' => 10, 'quantity' => 2], $this->authHeaders);
        $response = $this->postJson('/api/cart/items', ['product_id' => 10, 'quantity' => 3], $this->authHeaders);

        $response->assertStatus(201)
                 ->assertJson(['data' => ['item' => ['quantity' => 5]]]);

        $this->assertDatabaseCount('cart_items', 1);
    }

    #[Test]
    public function it_returns_422_when_quantity_exceeds_stock(): void
    {
        Http::fake([
            config('services.product_service.url') . '/products/10' => Http::response(
                ['data' => array_merge($this->fakeProduct, ['stock' => 3])], 200
            ),
        ]);

        $this->postJson('/api/cart/items', [
            'product_id' => 10,
            'quantity'   => 10,
        ], $this->authHeaders)
        ->assertStatus(422)
        ->assertJson(['success' => false]);
    }

    #[Test]
    public function it_returns_422_on_invalid_add_item_payload(): void
    {
        $this->postJson('/api/cart/items', ['quantity' => 1], $this->authHeaders)
             ->assertStatus(422)
             ->assertJsonStructure(['errors' => ['product_id']]);

        $this->postJson('/api/cart/items', ['product_id' => 1, 'quantity' => 0], $this->authHeaders)
             ->assertStatus(422)
             ->assertJsonStructure(['errors' => ['quantity']]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // PATCH /api/cart/items/{itemId} — Update Quantity
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function it_updates_cart_item_quantity(): void
    {
        Http::fake([
            config('services.product_service.url') . '*' => Http::response(
                ['data' => $this->fakeProduct], 200
            ),
        ]);

        $this->postJson('/api/cart/items', ['product_id' => 10, 'quantity' => 1], $this->authHeaders);
        $item = CartItem::first();

        $this->patchJson("/api/cart/items/{$item->id}", ['quantity' => 5], $this->authHeaders)
             ->assertStatus(200)
             ->assertJson(['data' => ['quantity' => 5, 'subtotal' => 18500000 * 5]]);

        $this->assertDatabaseHas('cart_items', ['id' => $item->id, 'quantity' => 5]);
    }

    #[Test]
    public function it_returns_404_when_updating_nonexistent_item(): void
    {
        $this->patchJson('/api/cart/items/9999', ['quantity' => 2], $this->authHeaders)
             ->assertStatus(404);
    }

    #[Test]
    public function it_cannot_update_another_users_cart_item(): void
    {
        Http::fake([
            config('services.product_service.url') . '*' => Http::response(
                ['data' => $this->fakeProduct], 200
            ),
        ]);

        $this->postJson('/api/cart/items', ['product_id' => 10, 'quantity' => 1], $this->authHeaders);
        $item = CartItem::first();

        $otherUserHeaders = $this->makeAuthHeaders(2);
        $this->patchJson("/api/cart/items/{$item->id}", ['quantity' => 5], $otherUserHeaders)
             ->assertStatus(404);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // DELETE /api/cart/items/{itemId} — Remove Item
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function it_removes_an_item_from_the_cart(): void
    {
        Http::fake([
            config('services.product_service.url') . '*' => Http::response(
                ['data' => $this->fakeProduct], 200
            ),
        ]);

        $this->postJson('/api/cart/items', ['product_id' => 10, 'quantity' => 1], $this->authHeaders);
        $item = CartItem::first();

        $this->deleteJson("/api/cart/items/{$item->id}", [], $this->authHeaders)
             ->assertStatus(200)
             ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // DELETE /api/cart — Clear Cart
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function it_clears_all_items_from_the_cart(): void
    {
        Http::fake([
            config('services.product_service.url') . '*' => Http::response(
                ['data' => $this->fakeProduct], 200
            ),
        ]);

        $this->postJson('/api/cart/items', ['product_id' => 10, 'quantity' => 2], $this->authHeaders);

        $this->deleteJson('/api/cart', [], $this->authHeaders)
             ->assertStatus(200)
             ->assertJson(['data' => ['item_count' => 0, 'subtotal' => 0]]);

        $this->assertDatabaseCount('cart_items', 0);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // POST /api/cart/promo
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function it_applies_a_valid_percentage_promo_code(): void
    {
        Http::fake([
            config('services.product_service.url') . '/products/10' => Http::response(
                ['data' => $this->fakeProduct], 200
            ),
            config('services.product_service.url') . '/promotions/validate*' => Http::response([
                'data' => ['type' => 'percentage', 'discount' => 20, 'code' => 'NEXA20'],
            ], 200),
        ]);

        $this->postJson('/api/cart/items', ['product_id' => 10, 'quantity' => 1], $this->authHeaders);

        $this->postJson('/api/cart/promo', ['code' => 'NEXA20'], $this->authHeaders)
             ->assertStatus(200)
             ->assertJson([
                 'success' => true,
                 'data'    => [
                     'promo_code'      => 'NEXA20',
                     'discount_amount' => (int) (18500000 * 0.20),
                 ],
             ]);
    }

    #[Test]
    public function it_returns_422_when_promo_service_rejects_code(): void
    {
        Http::fake([
            config('services.product_service.url') . '/products/10' => Http::response(
                ['data' => $this->fakeProduct], 200
            ),
            config('services.product_service.url') . '/promotions/validate*' => Http::response(
                ['message' => 'Kode promo tidak valid.'], 422
            ),
        ]);

        $this->postJson('/api/cart/items', ['product_id' => 10, 'quantity' => 1], $this->authHeaders);

        $this->postJson('/api/cart/promo', ['code' => 'BADCODE'], $this->authHeaders)
             ->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // POST /api/cart/checkout
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function it_successfully_checkouts_and_marks_cart_as_checked_out(): void
    {
        Http::fake([
            config('services.product_service.url') . '*' => Http::response(
                ['data' => $this->fakeProduct], 200
            ),
            config('services.order_service.url') . '/orders' => Http::response([
                'success' => true,
                'message' => 'Order created.',
                'data'    => ['order_id' => 'ORD-12345', 'status' => 'pending'],
            ], 201),
        ]);

        $this->postJson('/api/cart/items', ['product_id' => 10, 'quantity' => 1], $this->authHeaders);

        $this->postJson('/api/cart/checkout', [
            'shipping_address' => 'Jl. Sudirman No. 1, Jakarta',
            'payment_method'   => 'transfer_bank',
        ], $this->authHeaders)
        ->assertStatus(201)
        ->assertJson(['data' => ['data' => ['order_id' => 'ORD-12345']]]);

        $this->assertDatabaseHas('carts', [
            'user_id' => $this->userId,
            'status'  => 'checked_out',
        ]);
    }

    #[Test]
    public function it_returns_422_when_checking_out_empty_cart(): void
    {
        $this->postJson('/api/cart/checkout', [
            'shipping_address' => 'Jl. Sudirman No. 1, Jakarta',
            'payment_method'   => 'transfer_bank',
        ], $this->authHeaders)
        ->assertStatus(422)
        ->assertJson(['message' => 'Keranjang kosong, tidak dapat checkout.']);
    }

    #[Test]
    public function it_returns_422_for_invalid_checkout_payload(): void
    {
        $this->postJson('/api/cart/checkout', [
            'payment_method' => 'invalid_method',
        ], $this->authHeaders)
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['shipping_address', 'payment_method']]);
    }

    #[Test]
    public function it_returns_error_when_order_service_is_unavailable(): void
    {
        Http::fake([
            config('services.product_service.url') . '*' => Http::response(
                ['data' => $this->fakeProduct], 200
            ),
            config('services.order_service.url') . '/orders' => Http::response(
                ['message' => 'Order Service Error'], 500
            ),
        ]);

        $this->postJson('/api/cart/items', ['product_id' => 10, 'quantity' => 1], $this->authHeaders);

        $this->postJson('/api/cart/checkout', [
            'shipping_address' => 'Jl. Sudirman No. 1, Jakarta',
            'payment_method'   => 'qris',
        ], $this->authHeaders)
        ->assertStatus(500);

        $this->assertDatabaseHas('carts', ['user_id' => $this->userId, 'status' => 'active']);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Health check
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function health_check_returns_ok(): void
    {
        $this->getJson('/api/health')
             ->assertStatus(200)
             ->assertJson(['service' => 'cart-service', 'status' => 'ok']);
    }

    // ── Helper ─────────────────────────────────────────────────────────────────

    private function makeAuthHeaders(int $userId, string $role = 'buyer'): array
    {
        // Build a raw JWT payload manually — no User model needed.
        // This matches exactly what the Auth Service would issue.
        $payload = \Tymon\JWTAuth\Facades\JWTAuth::factory()->customClaims([
            'sub'  => $userId,
            'role' => $role,
            'name' => "Test User {$userId}",
        ])->make();

        $token = \Tymon\JWTAuth\Facades\JWTAuth::encode($payload)->get();

        return ['Authorization' => "Bearer {$token}"];
    }
}
