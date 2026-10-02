<?php

declare(strict_types=1);

namespace App\Http\Controllers\Predikant;

use App\Http\Controllers\Controller;
use App\Services\RoosterMatrixService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoosterController extends Controller
{
    public function standaardMaand(RoosterMatrixService $service): JsonResponse
    {
        return response()->json($service->ingelogdStandaardMaand());
    }

    public function matrix(Request $request, RoosterMatrixService $service): JsonResponse
    {
        $data = $request->validate([
            'maand' => ['required', 'date_format:Y-m'],
        ]);

        return response()->json($service->buildVoorIngelogd($data['maand']));
    }
}
