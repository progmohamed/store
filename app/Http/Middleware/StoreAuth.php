<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StoreAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->get('store_unlocked')) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
