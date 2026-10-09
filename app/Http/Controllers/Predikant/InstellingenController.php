<?php

declare(strict_types=1);

namespace App\Http\Controllers\Predikant;

use App\Http\Controllers\Controller;
use App\Services\Instellingen\InstellingenService;
use Illuminate\Http\JsonResponse;

class InstellingenController extends Controller
{
    public function __construct(
        private readonly InstellingenService $instellingenService
    ) {}

    public function beurtAnnuleren(): JsonResponse
    {
        return response()->json([
            'data' => $this->instellingenService->beurtAnnuleren(),
        ]);
    }
}
