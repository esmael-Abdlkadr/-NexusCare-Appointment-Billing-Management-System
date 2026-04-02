<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Models\UserSession;
use App\Rules\IdentifierFormatRule;
use App\Services\MaskingService;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function __construct(
        private readonly MaskingService $maskingService,
    ) {
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'identifier' => ['required', 'string', new IdentifierFormatRule()],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('identifier', $credentials['identifier'])->first();

        if ($user?->is_banned) {
            return $this->error('ACCOUNT_BANNED', Response::HTTP_FORBIDDEN);
        }

        if ($user?->locked_until && $user->locked_until->isFuture()) {
            Log::channel('auth')->warning('Account locked', [
                'event' => 'account_locked',
                'identifier' => $request->input('identifier'),
                'ip' => $request->ip(),
            ]);

            return $this->error('ACCOUNT_LOCKED', Response::HTTP_LOCKED, [
                'locked_until' => $user->locked_until->toIso8601String(),
            ]);
        }

        if (! $user || ! Hash::check($credentials['password'], $user->password_hash)) {
            if ($user) {
                $isLocked = $this->recordFailedAttempt($user, $request);

                if ($isLocked) {
                    Log::channel('auth')->warning('Account locked', [
                        'event' => 'account_locked',
                        'identifier' => $request->input('identifier'),
                        'ip' => $request->ip(),
                    ]);

                    return $this->error('ACCOUNT_LOCKED', Response::HTTP_LOCKED, [
                        'locked_until' => optional($user->fresh()?->locked_until)->toIso8601String(),
                    ]);
                }
            } else {
                LoginAttempt::create([
                    'identifier' => $credentials['identifier'],
                    'attempted_at' => now(),
                    'ip_address' => $request->ip(),
                ]);
            }

            Log::channel('auth')->warning('Login failed', [
                'event' => 'login_failed',
                'identifier' => $request->input('identifier'),
                'ip' => $request->ip(),
            ]);

            return $this->error('UNAUTHORIZED', Response::HTTP_UNAUTHORIZED);
        }

        $user->forceFill([
            'failed_attempts' => 0,
            'locked_until' => null,
        ])->save();

        $jti = (string) Str::uuid();
        $token = JWTAuth::claims(['jti' => $jti])->fromUser($user);

        UserSession::create([
            'user_id' => $user->id,
            'token_jti' => $jti,
            'last_active_at' => now(),
            'expires_at' => now()->addHours(12),
        ]);

        AuditLogger::write(
            $user->id,
            'LOGIN',
            User::class,
            $user->id,
            ['identifier' => $user->identifier],
            $request->ip(),
        );

        $isApiClient = $request->header('X-Client-Type') === 'api';

        $responseData = [
            'token_type' => $isApiClient ? 'bearer' : 'cookie',
            'expires_in' => 43200,
            'user' => $this->transformUser($user, true),
        ];

        if ($isApiClient) {
            $responseData['access_token'] = $token;
        }

        Log::channel('auth')->info('Login success', [
            'event' => 'login_success',
            'identifier' => $request->input('identifier'),
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $responseData,
        ])->withCookie(cookie(
            name: 'nexuscare_token',
            value: $token,
            minutes: 720,
            path: '/',
            domain: null,
            secure: app()->isProduction(),
            httpOnly: true,
            raw: false,
            sameSite: 'Lax',
        ));
    }

    public function logout(Request $request): JsonResponse
    {
        $tokenJti = $request->attributes->get('token_jti');

        UserSession::query()->where('token_jti', $tokenJti)->delete();

        AuditLogger::write(
            $request->user()->id,
            'LOGOUT',
            User::class,
            $request->user()->id,
            ['identifier' => $request->user()->identifier],
            $request->ip(),
        );

        return response()->json([
            'success' => true,
            'data' => ['message' => 'Logged out'],
        ])->withCookie(cookie()->forget('nexuscare_token'));
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $this->transformUser($user, true),
            ],
        ]);
    }

    private function recordFailedAttempt(User $user, Request $request): bool
    {
        $attempts = $user->failed_attempts + 1;

        LoginAttempt::create([
            'identifier' => $user->identifier,
            'attempted_at' => now(),
            'ip_address' => $request->ip(),
        ]);

        if ($attempts >= 5) {
            $user->forceFill([
                'failed_attempts' => 0,
                'locked_until' => now()->addMinutes(15),
            ])->save();

            return true;
        }

        $user->forceFill([
            'failed_attempts' => $attempts,
        ])->save();

        return false;
    }

    private function transformUser(User $user, bool $includeSensitive = false): array
    {
        $data = [
            'id' => $user->id,
            'identifier' => $user->identifier,
            'role' => $user->role,
            'site_id' => $user->site_id,
            'department_id' => $user->department_id,
            'is_banned' => $user->is_banned,
            'muted_until' => optional($user->muted_until)->toIso8601String(),
            'locked_until' => optional($user->locked_until)->toIso8601String(),
        ];

        if ($includeSensitive) {
            $data['email'] = $this->maskingService->mask($user, $user, 'email', $user->email ?? null);
            $data['government_id'] = $this->maskingService->mask($user, $user, 'government_id', $user->government_id);
            $data['phone'] = $this->maskingService->mask($user, $user, 'phone', $user->phone);
        }

        return $data;
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
