<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class UserSearchRepository
{
    public function searchBySite(int $siteId, array $filters, int $limit = 50): Collection
    {
        $query = User::query()
            ->where('site_id', $siteId)
            ->select(['id', 'identifier', 'role', 'site_id', 'department_id']);

        if (! empty($filters['identifier'])) {
            $query->where('identifier', 'like', '%'.$filters['identifier'].'%');
        }

        if (! empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        return $query->orderBy('identifier')->limit($limit)->get();
    }
}
