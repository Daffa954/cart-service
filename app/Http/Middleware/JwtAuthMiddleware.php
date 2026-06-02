<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

/**
 * JwtAuthMiddleware
 *
 * Validates the Bearer JWT token that was issued by the Auth Service.
 *
 * Strategy:
 *   1. Parse the token using the shared JWT_SECRET (fast, no network call).
 *   2. If validation fails, return 401 immediately — do NOT propagate the
 *      request to the controller.
 *   3. Inject a plain-object "auth user" (id, role) into the request so
 *      controllers can access it without calling Auth Service again.
 *
 * The Auth Service and Cart Service MUST share the same JWT_SECRET
 * (set via .env). In production, this secret is provided by the DevOps
 * team and stored in a secrets manager (e.g. AWS Secrets Manager).
 */
class JwtAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);

        if (!$token) {
            return $this->unauthorizedResponse('Token tidak ditemukan. Silakan login.');
        }

        try {
            // Decode & verify signature + expiry using the shared secret
            $payload = JWTAuth::setToken($token)->getPayload();

            // Attach the decoded claims to the request for downstream use
            $request->merge([
                'auth_user_id' => $payload->get('userId'),   // subject = user id
                'auth_user_role' => $payload->get('role') ?? 'USER',
                'auth_user_email' => $payload->get('email') ?? '', // Ekstrak email jika diperlukan
            ]);

        } catch (TokenExpiredException $e) {
            return $this->unauthorizedResponse('Token telah kedaluwarsa. Silakan login ulang.');
        } catch (TokenInvalidException $e) {
            return $this->unauthorizedResponse('Token tidak valid.');
        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Terjadi kesalahan autentikasi: ' . $e->getMessage());
        }

        return $next($request);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Extract Bearer token from Authorization header.
     * Supports: "Bearer <token>" format.
     */
    private function extractToken(Request $request): ?string
    {
        $authHeader = $request->header('Authorization', '');

        if (str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        // Fallback: check query param (e.g. for WebSocket connections)
        return $request->query('token');
    }

    /**
     * Return a standard 401 JSON response.
     */
    private function unauthorizedResponse(string $message): Response
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
        ], Response::HTTP_UNAUTHORIZED);
    }
}
