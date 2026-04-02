<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();

        if (! in_array($actor->role, ['reviewer', 'administrator'], true)) {
            return response()->json([
                'success' => false,
                'error' => 'FORBIDDEN',
                'data' => [],
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'action' => ['nullable', 'string', 'max:100'],
            'target_type' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = AuditLog::query()->with('user:id,identifier');

        if ($actor->role !== 'administrator') {
            $query->whereHas('user', function ($q) use ($actor): void {
                $q->where('site_id', $actor->site_id);
            });
        }

        if (! empty($validated['user_id'])) {
            $query->where('user_id', (int) $validated['user_id']);
        }

        if (! empty($validated['action'])) {
            $query->where('action', $validated['action']);
        }

        if (! empty($validated['target_type'])) {
            $query->where('target_type', $validated['target_type']);
        }

        if (! empty($validated['from'])) {
            $query->where('created_at', '>=', $validated['from']);
        }

        if (! empty($validated['to'])) {
            $query->where('created_at', '<=', $validated['to']);
        }

        $logs = $query->orderByDesc('id')->paginate((int) ($validated['per_page'] ?? 20));

        $logs->getCollection()->transform(function (AuditLog $log): array {
            return [
                'id' => $log->id,
                'user_id' => $log->user_id,
                'user_identifier' => $log->user?->identifier,
                'action' => $log->action,
                'target_type' => $log->target_type,
                'target_id' => $log->target_id,
                'payload' => $log->payload,
                'ip_address' => $log->ip_address,
                'created_at' => optional($log->created_at)->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }
}
