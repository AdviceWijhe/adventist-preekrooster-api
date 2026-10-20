<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Taal;
use Illuminate\Http\JsonResponse;

class TaalController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Taal::query()->orderBy('naam')->get(['id', 'naam', 'slug']),
        ]);
    }
}
