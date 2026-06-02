<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyServicePassword
{
    public function handle(Request $request, Closure $next): Response
    {
        // Ambil nilai header (Laravel otomatis menangani case-insensitive)
        $servicePassword = $request->header('X-Service-Password');

        // Cocokkan dengan nilai di file .env
        if ($servicePassword !== env('SERVICE_PASSWORD')) {
            return response()->json([
                'message' => 'Unauthorized: Invalid service password'
            ], 401);
        }

        // Lanjut ke Controller jika password benar
        return $next($request);
    }
}