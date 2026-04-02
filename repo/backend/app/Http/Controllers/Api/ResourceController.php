<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Resource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResourceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $resources = Resource::withoutGlobalScopes()
            ->where('site_id', $request->user()->site_id)
            ->where('is_active', true)
            ->get(['id', 'name', 'type']);

        return response()->json([
            'success' => true,
            'data' => $resources,
        ]);
    }
}
