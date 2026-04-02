<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\FeeRuleRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FeeRuleController extends Controller
{
    public function __construct(
        private readonly FeeRuleRepository $feeRuleRepository,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        if ($actor->role !== 'administrator') {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'success' => true,
            'data' => $this->feeRuleRepository->listForSite((int) $actor->site_id),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();
        if ($actor->role !== 'administrator') {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'fee_type' => ['required', 'string', 'in:no_show,overdue,lost_damaged'],
            'amount' => ['required', 'numeric', 'min:0'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'period_days' => ['nullable', 'integer', 'min:1'],
            'grace_minutes' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['site_id'] = (int) $actor->site_id;

        $rule = $this->feeRuleRepository->createOrUpdateRule($validated);

        return response()->json([
            'success' => true,
            'data' => ['fee_rule' => $rule],
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        if ($actor->role !== 'administrator') {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $rule = $this->feeRuleRepository->findById($id);

        if (! $rule) {
            return $this->error('NOT_FOUND', Response::HTTP_NOT_FOUND);
        }

        $rule->is_active = false;
        $this->feeRuleRepository->save($rule);

        return response()->json([
            'success' => true,
            'data' => ['fee_rule' => $rule],
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
