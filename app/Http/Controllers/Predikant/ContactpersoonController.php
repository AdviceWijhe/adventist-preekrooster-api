<?php

declare(strict_types=1);

namespace App\Http\Controllers\Predikant;

use App\Http\Controllers\Controller;
use App\Services\Gebruiker\ContactpersoonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactpersoonController extends Controller
{
    public function index(Request $request, ContactpersoonService $contactpersonen): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        return response()->json([
            'data' => $contactpersonen->metZichtbareContactgegevens($search),
        ]);
    }
}
