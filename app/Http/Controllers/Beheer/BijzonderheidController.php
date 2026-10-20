<?php

declare(strict_types=1);

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Support\BijzonderheidCatalog;
use Illuminate\Http\JsonResponse;

class BijzonderheidController extends Controller
{
    public function index(): JsonResponse
    {
        BijzonderheidCatalog::ensureSeeded();

        return response()->json([
            'data' => BijzonderheidCatalog::selecteerbaarQuery()
                ->orderBy('naam')
                ->get(['id', 'naam']),
        ]);
    }
}
