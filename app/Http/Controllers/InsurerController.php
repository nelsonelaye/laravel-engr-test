<?php

namespace App\Http\Controllers;

use App\Models\Insurer;
use Illuminate\Http\JsonResponse;

class InsurerController extends Controller
{
    public function index(): JsonResponse
    {
        $insurers = Insurer::select(['id', 'code', 'name', 'specialty_efficiencies', 'preferred_date_type'])
            ->orderBy('code')
            ->get();

        return response()->json([
            'data' => $insurers,
        ]);
    }
}
