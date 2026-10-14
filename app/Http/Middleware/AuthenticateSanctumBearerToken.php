<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class AuthenticateSanctumBearerToken
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()) {
            $token = $request->bearerToken();
            $authorizationHeader = $request->header('Authorization', $request->server->get('HTTP_AUTHORIZATION'));

            if (! is_string($token) || $token === '') {
                if (is_string($authorizationHeader) && str_contains(strtolower($authorizationHeader), 'bearer ')) {
                    $token = trim(substr($authorizationHeader, 7));
                }
            }

            if (is_string($token) && $token !== '') {
                $accessToken = PersonalAccessToken::findToken($token);

                if ($accessToken && $accessToken->tokenable) {
                    $tokenable = $accessToken->tokenable->withAccessToken($accessToken);
                    $request->setUserResolver(fn () => $tokenable);
                }
            }
        }

        if (! $request->user()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
