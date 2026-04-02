<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\FeeAssessmentRepository;
use App\Services\FeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class FeeAssessmentController extends Controller
{
    public function __construct(
        private readonly FeeAssessmentRepository $feeAssessmentRepository,
        private readonly FeeService $feeService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'string', 'in:pending,paid,waived,written_off'],
            'fee_type' => ['nullable', 'string', 'in:no_show,overdue,lost_damaged'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $items = $this->feeAssessmentRepository->paginate($validated, (int) ($validated['per_page'] ?? 15));

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $fee = $this->feeAssessmentRepository->findById($id);

        if (! $fee) {
            return $this->error('NOT_FOUND', Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'data' => ['fee_assessment' => $fee],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();

        if (! in_array($actor->role, ['staff', 'administrator'], true)) {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'client_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('site_id', $actor->site_id)),
            ],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $assessment = $this->feeService->assessLostDamagedFee(
            (int) $validated['client_id'],
            (int) $actor->site_id,
            (float) $validated['amount'],
            $validated['notes'] ?? null,
            $actor,
        );

        return response()->json([
            'success' => true,
            'data' => ['fee_assessment' => $assessment],
        ], Response::HTTP_CREATED);
    }

    public function waiver(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();

        if (! in_array($actor->role, ['reviewer', 'administrator'], true)) {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $fee = $this->feeAssessmentRepository->findByIdWithoutActorScope($id);

        if (! $fee) {
            return $this->error('NOT_FOUND', Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'waiver_type' => ['required', 'string', 'in:waived,written_off'],
            'waiver_note' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $result = $this->feeService->approveWaiver(
            $fee,
            $actor,
            $validated['waiver_type'],
            $validated['waiver_note'],
        );

        return response()->json([
            'success' => $result['success'],
            'error' => $result['success'] ? null : ($result['error'] ?? null),
            'data' => $result['data'] ?? [],
        ], (int) ($result['status'] ?? 200));
    }

    public function writeOff(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();

        if (! in_array($actor->role, ['reviewer', 'administrator'], true)) {
            return $this->error('FORBIDDEN', Response::HTTP_FORBIDDEN);
        }

        $fee = $this->feeAssessmentRepository->findByIdWithoutActorScope($id);

        if (! $fee) {
            return $this->error('NOT_FOUND', Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'note' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $result = $this->feeService->approveWaiver(
            $fee,
            $actor,
            'written_off',
            $validated['note'],
        );

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
