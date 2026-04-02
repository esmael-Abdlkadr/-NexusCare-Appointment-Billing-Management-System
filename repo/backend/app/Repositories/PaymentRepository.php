<?php

namespace App\Repositories;

use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PaymentRepository
{
    public function create(array $data): Payment
    {
        return Payment::query()->create($data);
    }

    public function existsByReferenceId(string $referenceId): bool
    {
        return Payment::withoutGlobalScopes()->where('reference_id', $referenceId)->exists();
    }

    /**
     * @param  array{method?: string}  $filters
     */
    public function paginate(array $filters = [], int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        $query = Payment::query()->with(['feeAssessment', 'postedBy']);

        $this->applyActorSiteScope($query);

        if (! empty($filters['method'])) {
            $query->where('method', $filters['method']);
        }

        return $query->orderByDesc('id')->paginate($perPage, ['*'], 'page', $page);
    }

    public function findById(int $id): ?Payment
    {
        $query = Payment::query();
        $this->applyActorSiteScope($query);
        return $query->find($id);
    }

    private function applyActorSiteScope(Builder $query): void
    {
        $actor = request()?->user();

        if (! $actor || $actor->role === 'administrator') {
            return;
        }

        $query->where('site_id', $actor->site_id);
    }
}
