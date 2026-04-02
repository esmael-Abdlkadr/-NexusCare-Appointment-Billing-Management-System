<?php

namespace App\Repositories;

use App\Models\SettlementImport;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SettlementImportRepository
{
    public function findByFileHash(string $fileHash): ?SettlementImport
    {
        return SettlementImport::withoutGlobalScopes()
            ->where('file_hash', $fileHash)
            ->first();
    }

    public function create(array $data): SettlementImport
    {
        return SettlementImport::query()->create($data);
    }

    public function paginateByActorSite(int $perPage = 15, ?User $actor = null): LengthAwarePaginator
    {
        $query = SettlementImport::query()->with(['importedBy']);

        if ($actor && $actor->role !== 'administrator') {
            $query->where('site_id', $actor->site_id);
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }
}
