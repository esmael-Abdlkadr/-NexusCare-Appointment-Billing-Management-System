<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserSearchRepository;

class UserSearchService
{
    public function __construct(
        private readonly UserSearchRepository $userSearchRepository,
    ) {
    }

    public function search(User $actor, array $filters): array
    {
        $users = $this->userSearchRepository->searchBySite($actor->site_id, $filters, 50);

        return [
            'success' => true,
            'status' => 200,
            'data' => $users,
        ];
    }
}
