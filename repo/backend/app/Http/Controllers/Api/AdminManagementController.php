<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminManagementController extends Controller
{
    public function __construct(
        private readonly AdminUserService $adminUserService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        if ($actor->role !== 'administrator') {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'role' => ['nullable', 'string', 'in:staff,reviewer,administrator'],
            'identifier' => ['nullable', 'string', 'max:100'],
            'is_banned' => ['nullable', 'boolean'],
            'is_muted' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $result = $this->adminUserService->listUsers($actor, $validated, (int) ($validated['per_page'] ?? 20));
        return $this->response($result);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();
        if ($actor->role !== 'administrator') {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $result = $this->adminUserService->createUser($actor, $request->all());
        return $this->response($result);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        if (! in_array($actor->role, ['reviewer', 'administrator'], true)) {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $target = User::withTrashed()->find($id);
        if (! $target) {
            return $this->error('NOT_FOUND', Response::HTTP_NOT_FOUND);
        }

        $result = $this->adminUserService->viewUser($actor, $target);
        return $this->response($result, (int) ($result['status'] ?? 200));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        if ($actor->role !== 'administrator') {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $target = User::withTrashed()->find($id);
        if (! $target) {
            return $this->error('NOT_FOUND', Response::HTTP_NOT_FOUND);
        }

        $result = $this->adminUserService->updateUser($actor, $target, $request->all());
        return $this->response($result);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        if ($actor->role !== 'administrator') {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $target = User::withTrashed()->find($id);
        if (! $target) {
            return $this->error('NOT_FOUND', Response::HTTP_NOT_FOUND);
        }

        $result = $this->adminUserService->deleteUser($actor, $target);
        return $this->response($result);
    }

    public function bulk(Request $request): JsonResponse
    {
        $actor = $request->user();
        if ($actor->role !== 'administrator') {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $result = $this->adminUserService->bulkAction($actor, $request->all());
        return $this->response($result);
    }

    public function unlock(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        if ($actor->role !== 'administrator') {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $target = User::withTrashed()->find($id);
        if (! $target) {
            return $this->error('NOT_FOUND', Response::HTTP_NOT_FOUND);
        }

        $result = $this->adminUserService->unlockUser($actor, $target);
        return $this->response($result);
    }

    private function response(array $result, ?int $status = null): JsonResponse
    {
        return response()->json([
            'success' => $result['success'],
            'error' => $result['success'] ? null : ($result['error'] ?? null),
            'data' => $result['data'] ?? [],
        ], (int) ($status ?? $result['status'] ?? 200));
    }

    private function error(string $code, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => $code,
            'data' => [],
        ], $status);
    }
}
