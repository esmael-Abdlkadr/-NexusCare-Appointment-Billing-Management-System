<?php

namespace App\Repositories;

use App\Models\RefundOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class RefundOrderRepository
{
    public function create(array $data): RefundOrder
    {
        return RefundOrder::query()->create($data);
    }

    public function findById(int $id): ?RefundOrder
    {
        $query = RefundOrder::query()->with(['payment', 'client', 'requestedBy', 'approvedBy']);
        $this->applyActorSiteScope($query);
        return $query->find($id);
    }

    public function save(RefundOrder $refundOrder): RefundOrder
    {
        $refundOrder->save();
        return $refundOrder;
    }

    public function list(array $filters = []): Collection
    {
        $query = RefundOrder::query()->with(['payment', 'client', 'requestedBy', 'approvedBy']);

        $this->applyActorSiteScope($query);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['client_id'])) {
            $query->where('client_id', (int) $filters['client_id']);
        }

        return $query->orderByDesc('id')->get();
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
