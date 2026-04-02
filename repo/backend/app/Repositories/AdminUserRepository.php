<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class AdminUserRepository
{
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = User::query();
        $this->applyScope($query);

        if (! empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (! empty($filters['identifier'])) {
            $query->where('identifier', 'like', '%'.$filters['identifier'].'%');
        }

        if (! empty($filters['is_banned'])) {
            $query->where('is_banned', true);
        }

        if (! empty($filters['is_muted'])) {
            $query->whereNotNull('muted_until')
                ->where('muted_until', '>', now());
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function findByIdWithTrashed(int $id): ?User
    {
        $query = User::withTrashed();
        $this->applyScope($query);
        return $query->find($id);
    }

    public function create(array $data): User
    {
        return User::query()->create($data);
    }

    public function save(User $user): User
    {
        $user->save();
        return $user;
    }

    private function applyScope(Builder $query): void
    {
        $actor = request()?->user();
        if (! $actor || $actor->role !== 'administrator') {
            return;
        }

        $query->where('site_id', $actor->site_id);
    }
}
