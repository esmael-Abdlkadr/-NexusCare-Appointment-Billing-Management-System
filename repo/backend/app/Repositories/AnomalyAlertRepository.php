<?php

namespace App\Repositories;

use App\Models\AnomalyAlert;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class AnomalyAlertRepository
{
    public function create(array $data): AnomalyAlert
    {
        return AnomalyAlert::query()->create($data);
    }

    public function findById(int $id): ?AnomalyAlert
    {
        return AnomalyAlert::query()->with(['settlementImport', 'acknowledgedBy'])->find($id);
    }

    public function save(AnomalyAlert $alert): AnomalyAlert
    {
        $alert->save();
        return $alert;
    }

    public function list(?User $actor = null): Collection
    {
        $query = AnomalyAlert::query()->with(['settlementImport', 'acknowledgedBy']);

        if ($actor && $actor->role !== 'administrator') {
            $query->whereHas('settlementImport', function ($q) use ($actor): void {
                $q->where('site_id', $actor->site_id);
            });
        }

        return $query->orderByDesc('id')->get();
    }
}
