<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StoreClient
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach (config('store.clients') as $client => $token) {
            if (is_string($token) && strlen($token) >= 32 && hash_equals($token, $request->bearerToken() ?? '')) {
                $request->attributes->set('store_client', $client);

                return $next($request);
            }
        }

        return response()->json(['message' => 'غير مصرح بالوصول للمخزن.'], 401);
    }
}
