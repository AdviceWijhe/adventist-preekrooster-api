<?php

declare(strict_types=1);

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Avg\AvgConsentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvgStatusController extends Controller
{
    public function __construct(
        private readonly AvgConsentService $avgConsent,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'filter' => ['sometimes', 'string', 'in:pending,open,wacht,geweigerd,akkoord,all'],
        ]);

        $filter = $data['filter'] ?? AvgConsentService::STATUS_FILTER_PENDING;

        return response()->json([
            'data' => $this->avgConsent->statusOverzicht($filter),
        ]);
    }

    public function reset(User $gebruiker): JsonResponse
    {
        $this->avgConsent->resetConsent($gebruiker);

        return response()->json([
            'message' => __('api.beheer.avg_reset_success'),
        ]);
    }
}
