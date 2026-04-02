<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\AnomalyAlertRepository;
use App\Repositories\ReconciliationExceptionRepository;
use App\Repositories\SettlementImportRepository;
use App\Services\ReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReconciliationController extends Controller
{
    public function __construct(
        private readonly ReconciliationService $reconciliationService,
        private readonly SettlementImportRepository $settlementImportRepository,
        private readonly ReconciliationExceptionRepository $reconciliationExceptionRepository,
        private readonly AnomalyAlertRepository $anomalyAlertRepository,
    ) {
    }

    public function import(Request $request): JsonResponse
    {
        $actor = $request->user();
        if (! in_array($actor->role, ['reviewer', 'administrator'], true)) {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $result = $this->reconciliationService->importSettlement($validated['file'], $actor);

        return response()->json([
            'success' => $result['success'],
            'error' => $result['success'] ? null : ($result['error'] ?? null),
            'data' => $result['data'] ?? [],
        ], (int) ($result['status'] ?? 200));
    }

    public function imports(Request $request): JsonResponse
    {
        $actor = $request->user();
        if (! in_array($actor->role, ['reviewer', 'administrator'], true)) {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->settlementImportRepository->paginateByActorSite((int) ($validated['per_page'] ?? 15), $actor),
        ]);
    }

    public function exceptions(Request $request): JsonResponse
    {
        $actor = $request->user();
        if (! in_array($actor->role, ['reviewer', 'administrator'], true)) {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'import_id' => ['nullable', 'integer', 'exists:settlement_imports,id'],
            'status' => ['nullable', 'string', 'in:unresolved,resolved'],
            'reason' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->reconciliationExceptionRepository->list(
                $validated,
                (int) ($validated['per_page'] ?? 20),
            ),
        ]);
    }

    public function resolveException(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        if (! in_array($actor->role, ['reviewer', 'administrator'], true)) {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $exception = $this->reconciliationExceptionRepository->findById($id);
        if (! $exception) {
            return $this->error('NOT_FOUND', Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'resolution_note' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $result = $this->reconciliationService->resolveException($exception, $actor, $validated['resolution_note']);

        return response()->json([
            'success' => $result['success'],
            'error' => $result['success'] ? null : ($result['error'] ?? null),
            'data' => $result['data'] ?? [],
        ], (int) ($result['status'] ?? 200));
    }

    public function anomalies(Request $request): JsonResponse
    {
        $actor = $request->user();
        if (! in_array($actor->role, ['reviewer', 'administrator'], true)) {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'anomaly_threshold' => \App\Services\ReconciliationService::ANOMALY_THRESHOLD,
                'alerts' => $this->anomalyAlertRepository->list($actor),
            ],
        ]);
    }

    public function acknowledgeAnomaly(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        if (! in_array($actor->role, ['reviewer', 'administrator'], true)) {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $alert = $this->anomalyAlertRepository->findById($id);
        if (! $alert) {
            return $this->error('NOT_FOUND', Response::HTTP_NOT_FOUND);
        }

        if ($actor->role !== 'administrator' && (int) $actor->site_id !== (int) $alert->site_id) {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $alert->status = 'acknowledged';
        $alert->acknowledged_by = $actor->id;
        $this->anomalyAlertRepository->save($alert);

        return response()->json([
            'success' => true,
            'data' => ['anomaly_alert' => $alert->fresh(['settlementImport', 'acknowledgedBy'])],
        ]);
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
