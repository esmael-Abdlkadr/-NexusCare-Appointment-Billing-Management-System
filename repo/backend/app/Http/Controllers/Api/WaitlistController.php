<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WaitlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class WaitlistController extends Controller
{
    public function __construct(
        private readonly WaitlistService $waitlistService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();

        if (! in_array($actor->role, ['staff', 'administrator'], true)) {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);
        $page = (int) ($request->input('page', 1));

        $departmentId = $actor->role === 'staff' ? (int) $actor->department_id : null;
        $paginator = $this->waitlistService->listForSite(
            $actor->role === 'administrator' ? null : $actor->site_id,
            $departmentId,
            $perPage,
            $page,
        );

        return response()->json([
            'success' => true,
            'data' => [
                'data' => $paginator->items(),
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();

        if (! in_array($actor->role, ['staff', 'administrator'], true)) {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $siteId = (int) ($request->input('_site_id') ?? $actor->site_id);

        $validated = $request->validate([
            'client_id' => ['required', 'integer', Rule::exists('users', 'id')->where('site_id', $siteId)],
            'service_type' => ['required', 'string', 'max:100'],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            'preferred_start' => ['required', 'date'],
            'preferred_end' => ['required', 'date', 'after:preferred_start'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('site_id', $siteId)],
        ]);

        if ($actor->role === 'staff') {
            $requestedDepartment = (int) ($validated['department_id'] ?? $actor->department_id);
            if ($requestedDepartment !== (int) $actor->department_id) {
                return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
            }
            $validated['department_id'] = $requestedDepartment;
        }

        $validated['site_id'] = $siteId;

        $entry = $this->waitlistService->addToWaitlist($validated, $actor);

        return response()->json([
            'success' => true,
            'data' => [
                'waitlist' => $entry,
            ],
        ], Response::HTTP_CREATED);
    }

    public function confirmBackfill(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();

        if (! in_array($actor->role, ['staff', 'administrator'], true)) {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        // Load entry first to get site context for scoped validation
        $entry = $this->waitlistService->getEntry($id);
        if (! $entry || $entry->status !== 'proposed') {
            return response()->json([
                'success' => false,
                'error' => 'WAITLIST_NOT_PROPOSED',
                'data' => [],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $siteId = (int) $entry->site_id;

        $validated = $request->validate([
            'provider_id' => ['required', 'integer', Rule::exists('users', 'id')->where('site_id', $siteId)],
            'resource_id' => ['required', 'integer', Rule::exists('resources', 'id')->where('site_id', $siteId)],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')->where('site_id', $siteId)],
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after:start_time'],
        ]);

        if ($actor->role === 'staff' && (int) $validated['department_id'] !== (int) $actor->department_id) {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $result = $this->waitlistService->confirmBackfill($id, $validated, $actor);

        return response()->json([
            'success' => $result['success'],
            'error' => $result['success'] ? null : ($result['error'] ?? null),
            'data' => $result['data'] ?? [],
        ], (int) ($result['status'] ?? 200));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();

        if (! in_array($actor->role, ['staff', 'administrator'], true)) {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $result = $this->waitlistService->removeWaitingEntry($id, $actor);

        return response()->json([
            'success' => $result['success'],
            'error' => $result['success'] ? null : ($result['error'] ?? null),
            'data' => $result['data'] ?? [],
        ], (int) ($result['status'] ?? 200));
    }

    private function error(string $code, int $status, array $data = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => $code,
            'data' => $data,
        ], $status);
    }
}
