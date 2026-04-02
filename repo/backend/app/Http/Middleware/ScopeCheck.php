<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Payload;

class ScopeCheck
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('UNAUTHORIZED', Response::HTTP_UNAUTHORIZED);
        }

        $payload = $request->attributes->get('jwt_payload');

        $siteId = $user->site_id;
        $deptId = $user->department_id;

        if ($payload instanceof Payload) {
            if ($payload->hasKey('site_id')) {
                $siteId = $payload->get('site_id');
            }

            if ($payload->hasKey('department_id')) {
                $deptId = $payload->get('department_id');
            }
        }

        $request->merge([
            '_site_id' => $siteId,
            '_dept_id' => $deptId,
        ]);

        return $next($request);
    }

    private function error(string $code, int $status, array $data = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => $code,
            'data' => $data,
        ], $status);
    }
}
