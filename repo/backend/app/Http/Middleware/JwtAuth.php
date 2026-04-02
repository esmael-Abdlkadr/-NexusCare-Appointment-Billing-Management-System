<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Facades\JWTAuth as JwtFacade;
use Tymon\JWTAuth\Exceptions\JWTException;

class JwtAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?? $request->cookie('nexuscare_token');

        if (! $token) {
            return $this->error('UNAUTHORIZED', Response::HTTP_UNAUTHORIZED);
        }

        try {
            $payload = JwtFacade::setToken($token)->getPayload();
        } catch (TokenExpiredException) {
            return $this->error('SESSION_EXPIRED', Response::HTTP_UNAUTHORIZED);
        } catch (JWTException) {
            return $this->error('UNAUTHORIZED', Response::HTTP_UNAUTHORIZED);
        }

        if ((int) $payload->get('exp') < now()->timestamp) {
            return $this->error('SESSION_EXPIRED', Response::HTTP_UNAUTHORIZED);
        }

        $session = UserSession::query()
            ->where('token_jti', $payload->get('jti'))
            ->first();

        if (! $session) {
            return $this->error('UNAUTHORIZED', Response::HTTP_UNAUTHORIZED);
        }

        if ($session->expires_at && $session->expires_at->isPast()) {
            $session->delete();

            return $this->error('SESSION_EXPIRED', Response::HTTP_UNAUTHORIZED);
        }

        if ($session->last_active_at->diffInRealMinutes(now()) > 30) {
            $session->delete();

            return $this->error('SESSION_IDLE_TIMEOUT', Response::HTTP_UNAUTHORIZED);
        }

        $user = $session->user;

        if (! $user) {
            $session->delete();

            return $this->error('UNAUTHORIZED', Response::HTTP_UNAUTHORIZED);
        }

        if ($user->is_banned) {
            return $this->error('ACCOUNT_BANNED', Response::HTTP_FORBIDDEN);
        }

        if ($user->locked_until && $user->locked_until->isFuture()) {
            return $this->error('ACCOUNT_LOCKED', Response::HTTP_LOCKED, [
                'locked_until' => $user->locked_until->toIso8601String(),
            ]);
        }

        $session->update(['last_active_at' => now()]);

        $request->attributes->set('jwt_payload', $payload);
        $request->attributes->set('token_jti', $payload->get('jti'));
        $request->setUserResolver(fn () => $user);

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
