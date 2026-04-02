<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMuted
{
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $user = $request->user();

            if ($user && $user->muted_until && $user->muted_until->isFuture()) {
                return response()->json([
                    'success' => false,
                    'error' => 'ACCOUNT_MUTED',
                    'data' => [
                        'muted_until' => $user->muted_until->toIso8601String(),
                    ],
                ], Response::HTTP_FORBIDDEN);
            }
        }

        return $next($request);
    }
}
