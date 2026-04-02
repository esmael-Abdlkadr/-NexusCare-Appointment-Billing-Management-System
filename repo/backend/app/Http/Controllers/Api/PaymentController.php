<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\PaymentRepository;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly PaymentRepository $paymentRepository,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'method' => ['nullable', 'string', 'in:cash,check,terminal_batch'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 15);
        $page = (int) ($request->input('page', 1));

        $paginator = $this->paymentRepository->paginate($validated, $perPage, $page);

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

        $validated = $request->validate([
            'reference_id' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'string', 'in:cash,check,terminal_batch'],
            'fee_assessment_id' => ['nullable', 'integer', 'exists:fee_assessments,id'],
            'client_id' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'batch_file' => [
                $request->input('method') === 'terminal_batch' ? 'required' : 'nullable',
                'file', 'mimes:csv,txt', 'max:10240',
            ],
        ]);

        if ($request->hasFile('batch_file') && $request->file('batch_file')->isValid()) {
            $file = $request->file('batch_file');
            $filename = now()->format('Ymd_His') . '_' . $validated['reference_id'] . '.' . $file->getClientOriginalExtension();
            $storedPath = Storage::disk('local')->putFileAs('batch_uploads', $file, $filename);

            // Parse CSV to count data rows and validate basic structure
            $csvContent = Storage::disk('local')->get($storedPath);
            $lines = array_filter(array_map('trim', explode("\n", $csvContent ?? '')));
            $dataRows = count($lines) > 1 ? count($lines) - 1 : 0; // exclude header row

            $validated['batch_file_path'] = $storedPath;
            $validated['batch_row_count'] = $dataRows;
        }

        $result = $this->paymentService->postPayment($validated, $actor);

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
