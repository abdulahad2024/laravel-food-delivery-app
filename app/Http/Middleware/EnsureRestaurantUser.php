<?php

namespace App\Http\Middleware;

use App\Models\RestaurantUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRestaurantUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() instanceof RestaurantUser) {
            return response()->json(['message' => 'A restaurant account is required.'], 403);
        }

        if ($request->user()->status !== 'active') {
            return response()->json(['message' => 'This restaurant account is not active.'], 403);
        }

        return $next($request);
    }
}
