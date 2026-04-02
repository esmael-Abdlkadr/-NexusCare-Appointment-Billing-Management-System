<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Resource;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Support\AuditLogger;
use Carbon\Carbon;

class RecycleBinService
{
    public function list(string $entityType = ''): array
    {
        $items = [];

        if ($entityType === '' || $entityType === 'user') {
            foreach (User::onlyTrashed()->get() as $item) {
                $items[] = $this->formatItem('user', $item->id, $item->deleted_at, $item->identifier);
            }
        }

        if ($entityType === '' || $entityType === 'appointment') {
            foreach (Appointment::onlyTrashed()->get() as $item) {
                $items[] = $this->formatItem('appointment', $item->id, $item->deleted_at, $item->service_type);
            }
        }

        if ($entityType === '' || $entityType === 'resource') {
            foreach (Resource::onlyTrashed()->get() as $item) {
                $items[] = $this->formatItem('resource', $item->id, $item->deleted_at, $item->name);
            }
        }

        if ($entityType === '' || $entityType === 'waitlist') {
            foreach (WaitlistEntry::onlyTrashed()->get() as $item) {
                $items[] = $this->formatItem('waitlist', $item->id, $item->deleted_at, $item->service_type);
            }
        }

        usort($items, fn ($a, $b) => strcmp($b['deleted_at'], $a['deleted_at']));

        return $items;
    }

    public function restore(string $type, int $id, User $actor): array
    {
        $model = $this->resolveModel($type, $id, true);
        if (! $model) {
            return ['success' => false, 'status' => 404, 'error' => 'NOT_FOUND', 'data' => []];
        }

        $model->restore();
        AuditLogger::write($actor->id, 'RESTORE_'.strtoupper($type), get_class($model), $id, [], request()?->ip());

        return ['success' => true, 'status' => 200, 'data' => ['message' => 'Restored']];
    }

    public function hardDelete(string $type, int $id, User $actor, bool $force): array
    {
        $model = $this->resolveModel($type, $id, true);
        if (! $model) {
            return ['success' => false, 'status' => 404, 'error' => 'NOT_FOUND', 'data' => []];
        }

        $deletedAt = $model->deleted_at ? Carbon::parse($model->deleted_at) : null;
        $olderThan24Months = $deletedAt ? $deletedAt->lt(now()->subMonths(24)) : false;

        if (! $force && ! $olderThan24Months) {
            return [
                'success' => false,
                'status' => 422,
                'error' => 'HARD_DELETE_BLOCKED',
                'data' => ['message' => 'Record is newer than 24 months. Set force=true to hard delete.'],
            ];
        }

        $model->forceDelete();
        AuditLogger::write($actor->id, 'HARD_DELETE_'.strtoupper($type), get_class($model), $id, ['force' => $force], request()?->ip());

        return ['success' => true, 'status' => 200, 'data' => ['message' => 'Deleted permanently']];
    }

    private function formatItem(string $entityType, int $entityId, $deletedAt, ?string $displayName = null): array
    {
        $deletedBy = AuditLog::query()
            ->where('action', 'DELETE_'.strtoupper($entityType))
            ->where('target_id', $entityId)
            ->latest('id')
            ->value('user_id');

        return [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'display_name' => $displayName,
            'deleted_at' => optional($deletedAt)->toIso8601String(),
            'deleted_by' => $deletedBy,
        ];
    }

    private function resolveModel(string $type, int $id, bool $withTrashed = false): ?object
    {
        $query = match ($type) {
            'user' => User::query(),
            'appointment' => Appointment::query(),
            'resource' => Resource::query(),
            'waitlist' => WaitlistEntry::query(),
            default => null,
        };

        if (! $query) {
            return null;
        }

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->find($id);
    }
}
