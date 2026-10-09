<?php

declare(strict_types=1);

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Services\Instellingen\InstellingenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InstellingenController extends Controller
{
    public function __construct(
        private readonly InstellingenService $instellingenService
    ) {}

    public function show(): JsonResponse
    {
        return response()->json([
            'data' => $this->instellingenService->all(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'beurt_annuleren' => ['sometimes', 'array'],
            'beurt_annuleren.enabled' => ['sometimes', 'boolean'],
            'beurt_annuleren.dagen_vooraf' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'inactiviteit' => ['sometimes', 'array'],
            'inactiviteit.enabled' => ['sometimes', 'boolean'],
            'inactiviteit.dagen' => ['sometimes', 'integer', 'min:30', 'max:1095'],
            'inactiviteit.waarschuwing_dagen' => ['sometimes', 'integer', 'min:1', 'max:180'],
            'changelog' => ['sometimes', 'array'],
            'changelog.bewaartermijn_dagen' => ['sometimes', 'integer', 'min:7', 'max:3650'],
            'avg' => ['sometimes', 'array'],
            'avg.enabled' => ['sometimes', 'boolean'],
            'avg.dagen' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'avg.titel' => ['sometimes', 'array'],
            'avg.titel.nl' => ['sometimes', 'string', 'max:200'],
            'avg.titel.en' => ['sometimes', 'string', 'max:200'],
            'avg.tekst_akkoord' => ['sometimes', 'array'],
            'avg.tekst_akkoord.nl' => ['sometimes', 'string', 'max:5000'],
            'avg.tekst_akkoord.en' => ['sometimes', 'string', 'max:5000'],
            'avg.tekst_weigering' => ['sometimes', 'array'],
            'avg.tekst_weigering.nl' => ['sometimes', 'string', 'max:5000'],
            'avg.tekst_weigering.en' => ['sometimes', 'string', 'max:5000'],
        ]);

        if (isset($data['inactiviteit']['waarschuwing_dagen'], $data['inactiviteit']['dagen'])
            && $data['inactiviteit']['waarschuwing_dagen'] >= $data['inactiviteit']['dagen']) {
            throw ValidationException::withMessages([
                'inactiviteit.waarschuwing_dagen' => [__('api.beheer.inactiviteit_waarschuwing_te_groot')],
            ]);
        }

        return response()->json([
            'data' => $this->instellingenService->update($data),
        ]);
    }
}
