<?php

use App\Http\Controllers\Api\CartController;
use App\Http\Middleware\VerifyServicePassword;
use Illuminate\Support\Facades\Route;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Http\Request; // GANTI DARI Facades\Request KE Http\Request

/*
|--------------------------------------------------------------------------
| Cart Service — API Routes  (ERD schema)
|--------------------------------------------------------------------------
|
| All routes are prefixed /api (set in bootstrap/app.php).
| Protected routes require:  Authorization: Bearer <JWT>
|
| Endpoint Map:
|
|   GET    /api/cart                   → Get cart + all cart_products
|   POST   /api/cart/products          → Add product to cart
|   PATCH  /api/cart/products/{id}     → Update product quantity
|   DELETE /api/cart/products/{id}     → Remove product from cart
|   DELETE /api/cart                   → Clear entire cart
|   POST   /api/cart/checkout          → Checkout → Order Service
|   GET    /api/health                 → Health check (no auth)
|
*/
Route::get('/', function () {
    // Rute root untuk cek apakah API ini hidup dan return 200 OK dengan pesan sederhana
    return response()->json([
        'message' => 'Welcome to the Product Service API! Endpoint is working.',
        'status' => 'success',
        'timestamp' => now()->toDateTimeString()
    ]);
});
Route::middleware(['service.auth', 'micro.jwt'])->group(function () {

    Route::prefix('cart')->group(function () {

        // ── Cart resource ──────────────────────────────────────────────
        Route::get('/',          [CartController::class, 'getCart']);
        Route::delete('/',       [CartController::class, 'clearCart']);
        Route::post('/checkout', [CartController::class, 'checkout']);

        // ── Cart products (cart_products table) ────────────────────────
        Route::post('/products',        [CartController::class, 'addProduct']);
        Route::patch('/products/{id}',  [CartController::class, 'updateProduct'])->where('id', '[0-9]+');
        Route::delete('/products/{id}', [CartController::class, 'removeProduct'])->where('id', '[0-9]+');
    });
});

// Health check — no auth (used by API Gateway / load balancer)
Route::get('/health', fn () => response()->json([
    'service'   => 'cart-service',
    'status'    => 'ok',
    'database'  => 'postgresql',
    'timestamp' => now()->toISOString(),
]));

Route::middleware([VerifyServicePassword::class])->get('/debug-jwt-manual', function (Request $request) {
    $header = $request->header('Authorization');
    
    if (!$header) {
        return response()->json(['error' => 'Header Authorization kosong!']);
    }

    $token = str_replace('Bearer ', '', $header);
    
    try {
        // Menggunakan library JWTAuth untuk membongkar payload
        $payload = JWTAuth::setToken($token)->getPayload()->toArray();
        
        return response()->json([
            'status' => 'Token terbaca!',
            'raw_token' => $token,
            'decoded_payload' => $payload,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'Error',
            'message' => $e->getMessage(),
            'token_diterima' => $token
        ], 401);
    }
});
