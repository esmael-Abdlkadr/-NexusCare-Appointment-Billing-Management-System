<?php

namespace App\Repositories;

use App\Models\FeeRule;
use Illuminate\Database\Eloquent\Collection;

class FeeRuleRepository
{
    public function activeNoShowRulesBySite(): Collection
    {
        return FeeRule::withoutGlobalScopes()
            ->where('fee_type', 'no_show')
            ->where('is_active', true)
            ->whereNotNull('grace_minutes')
            ->get();
    }

    public function listForSite(int $siteId): Collection
    {
        return FeeRule::withoutGlobalScopes()
            ->where('site_id', $siteId)
            ->orderBy('fee_type')
            ->get();
    }

    public function findActiveByTypeAndSite(string $feeType, int $siteId): ?FeeRule
    {
        return FeeRule::withoutGlobalScopes()
            ->where('fee_type', $feeType)
            ->where('site_id', $siteId)
            ->where('is_active', true)
            ->first();
    }

    public function createOrUpdateRule(array $data): FeeRule
    {
        return FeeRule::withoutGlobalScopes()->updateOrCreate(
            [
                'fee_type' => $data['fee_type'],
                'site_id' => $data['site_id'],
            ],
            [
                'amount' => $data['amount'],
                'rate' => $data['rate'] ?? null,
                'period_days' => $data['period_days'] ?? null,
                'grace_minutes' => $data['grace_minutes'] ?? null,
                'is_active' => true,
            ],
        );
    }

    public function findById(int $id): ?FeeRule
    {
        return FeeRule::query()->find($id);
    }

    public function save(FeeRule $rule): FeeRule
    {
        $rule->save();
        return $rule;
    }
}
