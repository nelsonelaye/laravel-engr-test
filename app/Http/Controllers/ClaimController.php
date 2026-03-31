<?php

namespace App\Http\Controllers;

use App\Actions\CreateClaimAction;
use App\Http\Requests\StoreClaimRequest;
use App\Models\Claim;
use App\Models\Insurer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClaimController extends Controller
{
    public function store(StoreClaimRequest $request, CreateClaimAction $action): JsonResponse
    {
        $claim = $action->execute($request->validated(), $request->user());

        return response()->json([
            'message' => 'Claim submitted successfully',
            'data' => $claim,
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $claims = $request->user()->claims()
            ->with(['insurer', 'items', 'batch'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $claims,
        ]);
    }

    public function show(Claim $claim): JsonResponse
    {
        $this->authorize('view', $claim);

        return response()->json([
            'data' => $claim->load(['insurer', 'items', 'batch']),
        ]);
    }
}
