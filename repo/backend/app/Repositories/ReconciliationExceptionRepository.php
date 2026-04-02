<?php

namespace App\Repositories;

use App\Models\ReconciliationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ReconciliationExceptionRepository
{
    public function create(array $data): ReconciliationException
    {
        return ReconciliationException::query()->create($data);
    }

    public function findById(int $id): ?ReconciliationException
    {
        return ReconciliationException::query()
            ->with(['settlementImport', 'resolvedBy'])
            ->find($id);
    }

    public function save(ReconciliationException $exception): ReconciliationException
    {
        $exception->save();
        return $exception;
    }

    public function list(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = ReconciliationException::query()->with(['settlementImport', 'resolvedBy']);

        if (! empty($filters['import_id'])) {
            $query->where('import_id', (int) $filters['import_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['reason'])) {
            $query->where('reason', $filters['reason']);
        }

        $actor = request()?->user();
        if ($actor && $actor->role !== 'administrator') {
            $query->whereHas('settlementImport', function ($importQuery) use ($actor): void {
                $importQuery->where('site_id', $actor->site_id);
            });
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }
}
