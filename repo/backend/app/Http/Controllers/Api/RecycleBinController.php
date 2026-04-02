<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RecycleBinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecycleBinController extends Controller
{
    public function __construct(
        private readonly RecycleBinService $recycleBinService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        if ($actor->role !== 'administrator') {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'entity_type' => ['nullable', 'string', 'in:user,appointment,resource,waitlist'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->recycleBinService->list($validated['entity_type'] ?? ''),
        ]);
    }

    public function restore(Request $request, string $type, int $id): JsonResponse
    {
        $actor = $request->user();
        if ($actor->role !== 'administrator') {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $result = $this->recycleBinService->restore($type, $id, $actor);

        return response()->json([
            'success' => $result['success'],
            'error' => $result['success'] ? null : ($result['error'] ?? null),
            'data' => $result['data'] ?? [],
        ], (int) ($result['status'] ?? 200));
    }

    public function destroy(Request $request, string $type, int $id): JsonResponse
    {
        $actor = $request->user();
        if ($actor->role !== 'administrator') {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'force' => ['nullable', 'boolean'],
        ]);

        $result = $this->recycleBinService->hardDelete($type, $id, $actor, (bool) ($validated['force'] ?? false));

        return response()->json([
            'success' => $result['success'],
            'error' => $result['success'] ? null : ($result['error'] ?? null),
            'data' => $result['data'] ?? [],
        ], (int) ($result['status'] ?? 200));
    }

    public function bulkRestore(Request $request): JsonResponse
    {
        $actor = $request->user();
        if ($actor->role !== 'administrator') {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.entity_type' => ['required', 'string', 'in:user,appointment,resource,waitlist'],
            'items.*.entity_id' => ['required', 'integer', 'min:1'],
        ]);

        $results = [];
        foreach ($validated['items'] as $item) {
            $results[] = $this->recycleBinService->restore($item['entity_type'], (int) $item['entity_id'], $actor);
        }

        $allOk = collect($results)->every(fn ($result) => $result['success'] ?? false);

        return response()->json([
            'success' => $allOk,
            'error' => $allOk ? null : 'PARTIAL_FAILURE',
            'data' => ['results' => $results],
        ], $allOk ? Response::HTTP_OK : Response::HTTP_MULTI_STATUS);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $actor = $request->user();
        if ($actor->role !== 'administrator') {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.entity_type' => ['required', 'string', 'in:user,appointment,resource,waitlist'],
            'items.*.entity_id' => ['required', 'integer', 'min:1'],
        ]);

        $results = [];
        foreach ($validated['items'] as $item) {
            $results[] = $this->recycleBinService->hardDelete(
                $item['entity_type'],
                (int) $item['entity_id'],
                $actor,
                true,
            );
        }

        $allOk = collect($results)->every(fn ($result) => $result['success'] ?? false);

        return response()->json([
            'success' => $allOk,
            'error' => $allOk ? null : 'PARTIAL_FAILURE',
            'data' => ['results' => $results],
        ], $allOk ? Response::HTTP_OK : Response::HTTP_MULTI_STATUS);
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
