<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogger
{
    public static function write(?int $userId, string $action, ?string $targetType, mixed $targetId, array $payload, ?string $ipAddress): void
    {
        AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'payload' => self::stripSensitive($payload),
            'ip_address' => $ipAddress,
            'created_at' => now(),
        ]);
    }

    public static function sanitizedPayload(Request $request): array
    {
        return self::stripSensitive($request->all());
    }

    private static function stripSensitive(array $payload): array
    {
        $sensitiveKeys = [
            'password',
            'password_hash',
            'government_id',
            'phone',
            'token',
            'access_token',
            'secret',
            'credit_card',
            'ssn',
        ];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = self::stripSensitive($value);
                continue;
            }

            $keyStr = strtolower((string) $key);
            $isSensitive = collect($sensitiveKeys)->contains(fn (string $needle): bool => str_contains($keyStr, $needle));

            if ($isSensitive) {
                $payload[$key] = '[REDACTED]';
            }
        }

        return $payload;
    }
}
