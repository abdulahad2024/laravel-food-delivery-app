<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() instanceof User) {
            return response()->json(['message' => 'A platform user account is required.'], 403);
        }

        if ($request->user()->status !== 'active') {
            return response()->json(['message' => 'This platform account is not active.'], 403);
        }

        return $next($request);
    }
}
