<?php

declare(strict_types=1);

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Models\Bijzonderheid;
use Illuminate\Http\JsonResponse;

class BijzonderheidController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Bijzonderheid::query()->orderBy('naam')->get(['id', 'naam']),
        ]);
    }
}
