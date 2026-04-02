<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\LedgerEntryRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LedgerController extends Controller
{
    public function __construct(
        private readonly LedgerEntryRepository $ledgerEntryRepository,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        if ($actor->role !== 'administrator') {
            return response()->json([
                'success' => false,
                'error' => 'FORBIDDEN',
                'data' => [],
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'success' => true,
            'data' => $this->ledgerEntryRepository->listBySite((int) $actor->site_id),
        ]);
    }
}
