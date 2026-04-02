<?php

namespace App\Http\Middleware;

use App\Models\UserRole;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('UNAUTHORIZED', Response::HTTP_UNAUTHORIZED);
        }

        $allowedRoles = array_values(array_filter(array_map('trim', $roles)));
        $scopeSiteId = $request->input('_site_id');

        $authorized = UserRole::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->whereHas('role', function ($query) use ($allowedRoles): void {
                $query->whereIn('name', $allowedRoles);
            })
            ->where(function ($query) use ($scopeSiteId): void {
                $query->whereNull('site_id');

                if ($scopeSiteId !== null) {
                    $query->orWhere('site_id', $scopeSiteId);
                }
            })
            ->exists();

        if (! $authorized) {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

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
