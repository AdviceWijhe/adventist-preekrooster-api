<?php

declare(strict_types=1);

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Models\Publicatie;
use App\Services\Rooster\RoosterPublicatieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicatieController extends Controller
{
    public function __construct(
        private readonly RoosterPublicatieService $publicatieService,
    ) {}

    public function publiceer(Request $request): JsonResponse
    {
        $data = $request->validate(['periode' => ['required', 'date_format:Y-m']]);

        $this->publicatieService->publiceerMaand(
            $data['periode'],
            $request->user()->id,
            stuurBekendmaking: true,
        );

        return response()->json(['message' => "Rooster voor {$data['periode']} gepubliceerd."]);
    }

    public function depublicer(string $periode): JsonResponse
    {
        validator(
            ['periode' => $periode],
            ['periode' => ['required', 'date_format:Y-m']],
        )->validate();

        $this->publicatieService->depublicerMaand($periode);

        return response()->json(['message' => "Rooster voor {$periode} gedepubliceerd."]);
    }

    public function overzicht(): JsonResponse
    {
        $publicaties = Publicatie::query()
            ->orderByDesc('periode')
            ->take(12)
            ->get();

        return response()->json(['data' => $publicaties]);
    }
}
