<?php

namespace App\Repositories;

use App\Models\LedgerEntry;
use Illuminate\Database\Eloquent\Collection;

class LedgerEntryRepository
{
    public function create(array $data): LedgerEntry
    {
        return LedgerEntry::query()->create($data);
    }

    public function listBySite(int $siteId): Collection
    {
        return LedgerEntry::withoutGlobalScopes()
            ->where('site_id', $siteId)
            ->orderByDesc('created_at')
            ->get();
    }
}
