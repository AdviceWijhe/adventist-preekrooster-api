<?php

declare(strict_types=1);

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Services\Statistieken\StatistiekenFilter;
use App\Services\Statistieken\StatistiekenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StatistiekenController extends Controller
{
    public function __construct(
        private readonly StatistiekenService $statistiekenService,
    ) {}

    public function jaren(): JsonResponse
    {
        return response()->json([
            'data' => $this->statistiekenService->beschikbareJaren(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $this->valideerFilters($request);
        $filter = StatistiekenFilter::fromArray($filters);
        $sectie = $request->string('sectie')->toString() ?: StatistiekenService::SECTIE_OVERZICHT;

        if (! in_array($sectie, $this->statistiekenService->beschikbareSecties(), true)) {
            return response()->json(['message' => __('api.beheer.unknown_section')], 422);
        }

        $data = $this->statistiekenService->voorSectie($sectie, $filter);

        if ($sectie === StatistiekenService::SECTIE_OVERZICHT) {
            $data['bevestigingspercentage'] = $data['kpis']['bevestigingspercentage'];
        }

        return response()->json([
            'data' => $data,
            'meta' => [
                'secties' => $this->statistiekenService->beschikbareSecties(),
                'kmEnabled' => (bool) config('statistieken.km_enabled'),
            ],
        ]);
    }

    public function export(Request $request): Response
    {
        $filters = $this->valideerFilters($request);
        $sectie = $request->string('sectie')->toString() ?: StatistiekenService::SECTIE_OVERZICHT;
        $csv = $this->statistiekenService->naarCsv($filters, $sectie !== '' ? $sectie : null);
        $filename = 'statistieken-'.$filters['jaar'].'-'.$sectie.'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function valideerFilters(Request $request): array
    {
        $validated = $request->validate([
            'jaar' => ['required', 'integer', 'min:2000', 'max:2099'],
            'gemeente_id' => ['nullable', 'integer', 'exists:gemeentes,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'spreker_id' => ['nullable', 'integer', 'exists:users,id'],
            'van' => ['nullable', 'date'],
            'tot' => ['nullable', 'date', 'after_or_equal:van'],
            'type' => ['nullable', 'string', 'in:sabbatschool,eredienst,speciaal'],
            'taal' => ['nullable', 'string', 'in:nl,en'],
            'dienstwijze' => ['nullable', 'string', 'in:fysiek,digitaal,fysiek_digitaal'],
        ]);

        return [
            'jaar' => (int) $validated['jaar'],
            'gemeente_id' => isset($validated['gemeente_id']) ? (int) $validated['gemeente_id'] : null,
            'district_id' => isset($validated['district_id']) ? (int) $validated['district_id'] : null,
            'spreker_id' => isset($validated['spreker_id']) ? (int) $validated['spreker_id'] : null,
            'van' => $validated['van'] ?? null,
            'tot' => $validated['tot'] ?? null,
            'type' => $validated['type'] ?? null,
            'taal' => $validated['taal'] ?? null,
            'dienstwijze' => $validated['dienstwijze'] ?? null,
        ];
    }
}
