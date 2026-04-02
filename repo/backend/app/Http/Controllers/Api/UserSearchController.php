<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserSearchController extends Controller
{
    public function __construct(
        private readonly UserSearchService $userSearchService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'in:staff,reviewer,administrator'],
        ]);

        $result = $this->userSearchService->search($request->user(), $validated);

        return response()->json([
            'success' => $result['success'],
            'error' => $result['success'] ? null : ($result['error'] ?? null),
            'data' => $result['data'] ?? [],
        ], (int) ($result['status'] ?? 200));
    }
}
