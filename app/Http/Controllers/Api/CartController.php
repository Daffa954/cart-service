<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Http\Resources\CartProductResource;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * CartController
 *
 * Thin HTTP layer — validates input, delegates to CartService, formats response.
 *
 * ERD schema:
 *   carts         (id, user_id UNIQUE, timestamps)
 *   cart_products (id, cart_id FK, product_id, product_name, product_image,
 *                  shop_name, shop_id, unit_price, quantity, timestamps)
 *
 * Routes (all prefixed /api, protected by jwt.auth middleware):
 *   GET    /cart               → getCart()
 *   POST   /cart/products      → addProduct()
 *   PATCH  /cart/products/{id} → updateProduct()
 *   DELETE /cart/products/{id} → removeProduct()
 *   DELETE /cart               → clearCart()
 *   POST   /cart/checkout      → checkout()
 *   GET    /health             → health check (no auth)
 */
class CartController extends Controller
{
    public function __construct(private readonly CartService $cartService) {}

    // ── GET /api/cart ─────────────────────────────────────────────────────────

    /**
     * Get the authenticated user's cart with all products.
     */
    public function getCart(Request $request): JsonResponse
    {
        $cart = $this->cartService->getOrCreateCart($request->auth_user_id);

        return $this->success(new CartResource($cart), 'Keranjang berhasil dimuat.');
    }

    // ── POST /api/cart/products ───────────────────────────────────────────────

    /**
     * Add a product to the cart.
     *
     * Body: { product_id: int, quantity: int }
     */
    public function addProduct(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer|min:1',
            'quantity'   => 'required|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        try {
            $result = $this->cartService->addProduct(
                $request->auth_user_id,
                $validator->validated()
            );

            return $this->success([
                'cart'    => new CartResource($result['cart']),
                'product' => new CartProductResource($result['product']),
            ], $result['message'], 201);

        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    // ── PATCH /api/cart/products/{id} ────────────────────────────────────────

    /**
     * Update quantity of a cart_products row.
     *
     * Body: { quantity: int }
     */
    public function updateProduct(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        try {
            $product = $this->cartService->updateProduct(
                $request->auth_user_id,
                $id,
                $validator->validated()['quantity']
            );

            return $this->success(new CartProductResource($product), 'Jumlah produk berhasil diperbarui.');

        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    // ── DELETE /api/cart/products/{id} ───────────────────────────────────────

    /**
     * Remove a single product from the cart.
     */
    public function removeProduct(Request $request, int $id): JsonResponse
    {
        try {
            $this->cartService->removeProduct($request->auth_user_id, $id);
            return $this->success(null, 'Produk berhasil dihapus dari keranjang.');

        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    // ── DELETE /api/cart ──────────────────────────────────────────────────────

    /**
     * Remove all products from the cart (cart record stays).
     */
    public function clearCart(Request $request): JsonResponse
    {
        $cart = $this->cartService->clearCart($request->auth_user_id);
        return $this->success(new CartResource($cart), 'Keranjang berhasil dikosongkan.');
    }

    // ── POST /api/cart/checkout ───────────────────────────────────────────────

    /**
     * Checkout: send cart to Order Service.
     *
     * Body: {
     *   shipping_address: string,
     *   payment_method: "transfer_bank"|"virtual_account"|"credit_card"|"qris",
     *   notes?: string
     * }
     */
    public function checkout(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'shipping_address' => 'required|string|max:512',
            'payment_method'   => 'required|in:transfer_bank,virtual_account,credit_card,qris',
            'notes'            => 'nullable|string|max:512',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $bearerToken = substr($request->header('Authorization', ''), 7);

        try {
            $order = $this->cartService->checkout(
                $request->auth_user_id,
                $validator->validated(),
                $bearerToken
            );

            return $this->success($order, 'Checkout berhasil! Pesanan sedang diproses.', 201);

        } catch (\RuntimeException $e) {
            Log::error('Checkout error', [
                'user_id' => $request->auth_user_id,
                'message' => $e->getMessage(),
            ]);
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    // ── Response helpers ──────────────────────────────────────────────────────

    private function success(mixed $data, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    private function error(string $message, int $status = 400): JsonResponse
    {
        $safeStatus = ($status >= 100 && $status < 600) ? $status : 400;
        return response()->json([
            'success' => false,
            'message' => $message,
            'data'    => null,
        ], $safeStatus);
    }

    private function validationError(array $errors): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Data tidak valid.',
            'errors'  => $errors,
        ], 422);
    }
}
