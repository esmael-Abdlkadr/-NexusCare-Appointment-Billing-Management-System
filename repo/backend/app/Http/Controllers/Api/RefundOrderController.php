<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\RefundOrderRepository;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RefundOrderController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly RefundOrderRepository $refundOrderRepository,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,approved,rejected,processed'],
            'client_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->refundOrderRepository->list($validated),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();

        if (! in_array($actor->role, ['staff', 'administrator'], true)) {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'payment_id' => ['required', 'integer', 'exists:payments,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $result = $this->paymentService->createRefundOrder($validated, $actor);

        return response()->json([
            'success' => $result['success'],
            'error' => $result['success'] ? null : ($result['error'] ?? null),
            'data' => $result['data'] ?? [],
        ], (int) ($result['status'] ?? 200));
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();

        if (! in_array($actor->role, ['reviewer', 'administrator'], true)) {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $refund = $this->refundOrderRepository->findById($id);

        if (! $refund) {
            return $this->error('NOT_FOUND', Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'decision' => ['required', 'string', 'in:approved,rejected'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = $this->paymentService->approveRefund($refund, $validated, $actor);

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
