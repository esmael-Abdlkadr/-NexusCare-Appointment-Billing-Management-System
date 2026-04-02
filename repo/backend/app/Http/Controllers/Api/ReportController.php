<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {
    }

    public function appointments(Request $request): Response
    {
        $actor = $request->user();
        if (! in_array($actor->role, ['reviewer', 'administrator'], true)) {
            return response()->json(['success' => false, 'error' => 'FORBIDDEN', 'data' => []], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'site_id' => ['nullable', 'integer'],
            'format' => ['nullable', 'string', 'in:json,csv,xlsx'],
        ]);

        return $this->reportService->appointments($actor, $validated);
    }

    public function financial(Request $request): Response
    {
        $actor = $request->user();
        if (! in_array($actor->role, ['reviewer', 'administrator'], true)) {
            return response()->json(['success' => false, 'error' => 'FORBIDDEN', 'data' => []], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'site_id' => ['nullable', 'integer'],
            'format' => ['nullable', 'string', 'in:json,csv,xlsx'],
        ]);

        return $this->reportService->financial($actor, $validated);
    }

    public function audit(Request $request): Response
    {
        $actor = $request->user();
        if ($actor->role !== 'administrator') {
            return response()->json(['success' => false, 'error' => 'FORBIDDEN', 'data' => []], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'format' => ['nullable', 'string', 'in:csv,xlsx'],
        ]);

        return $this->reportService->audit($actor, $validated);
    }
}
