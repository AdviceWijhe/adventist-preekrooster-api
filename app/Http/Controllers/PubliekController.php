<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Dienst;
use App\Models\Gemeente;
use App\Models\Publicatie;
use App\Models\PublicNavigationItem;
use App\Services\Branding\BrandingService;
use App\Services\RoosterMatrixService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PubliekController extends Controller
{
    public function standaardMaand(RoosterMatrixService $service): JsonResponse
    {
        return response()->json($service->publiekStandaardMaand());
    }

    public function matrix(Request $request, RoosterMatrixService $service): JsonResponse
    {
        $data = $request->validate([
            'maand' => ['required', 'date_format:Y-m'],
        ]);

        return response()->json($service->buildVoorPubliek($data['maand']));
    }

    public function rooster(Request $request): JsonResponse
    {
        $data = $request->validate([
            'maand' => ['required', 'date_format:Y-m'],
            'gemeente_id' => [
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === 'all') {
                        return;
                    }

                    if (ctype_digit((string) $value) && Gemeente::query()->whereKey((int) $value)->exists()) {
                        return;
                    }

                    $fail(__('api.validation.invalid_gemeente'));
                },
            ],
        ]);
        $maand = $data['maand'];
        $gemeenteId = $data['gemeente_id'] ?? null;

        $isGepubliceerd = Publicatie::query()
            ->where('periode', $maand)
            ->where('gemeente_id', null)
            ->where('gepubliceerd', true)
            ->exists();

        if (! $isGepubliceerd) {
            return response()->json(['data' => []]);
        }

        $dienstenQuery = Dienst::query()
            ->with(['gemeente:id,naam', 'bijzonderheid:id,naam', 'spreekbeurten.spreker:id,voornaam,tussenvoegsel,achternaam'])
            ->whereYear('datum', substr($maand, 0, 4))
            ->whereMonth('datum', substr($maand, 5, 2))
            ->orderBy('datum')
            ->orderBy('gemeente_id');

        if ($gemeenteId !== null && $gemeenteId !== 'all') {
            $dienstenQuery->where('gemeente_id', (int) $gemeenteId);
        }

        $diensten = $dienstenQuery
            ->get()
            ->map(fn (Dienst $dienst) => $this->mapDienst($dienst))
            ->values();

        return response()->json(['data' => $diensten]);
    }

    public function navigatie(): JsonResponse
    {
        $items = PublicNavigationItem::query()
            ->where('visible', true)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get()
            ->map(function (PublicNavigationItem $item): array {
                return [
                    'id' => $item->key,
                    'label_nl' => $item->label_nl,
                    'label_en' => $item->label_en,
                    'url' => $item->url,
                    'order' => $item->display_order,
                    'visible' => $item->visible,
                    'external' => $item->external,
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => $items,
            'error' => null,
        ]);
    }

    public function branding(BrandingService $brandingService): JsonResponse
    {
        return response()->json([
            'data' => $brandingService->all(),
        ]);
    }

    private function mapDienst(Dienst $dienst): array
    {
        $spreekbeurt = $dienst->spreekbeurten->first(
            static fn ($sb): bool => (int) ($sb->bevestigd ?? -1) === 1
        );

        $sprekerNaam = $spreekbeurt?->spreker !== null
            ? trim(implode(' ', array_filter([
                $spreekbeurt->spreker->voornaam,
                $spreekbeurt->spreker->tussenvoegsel,
                $spreekbeurt->spreker->achternaam,
            ])))
            : null;

        return [
            'id' => $dienst->id,
            'datum' => Carbon::parse($dienst->datum)->format('Y-m-d'),
            'type' => $dienst->type,
            'taal' => $dienst->taal,
            'dienstwijze' => $dienst->dienstwijze,
            'gemeente_id' => $dienst->gemeente_id,
            'gemeente_naam' => $dienst->gemeente?->naam,
            'gemeente' => $dienst->gemeente?->naam,
            'gemeente_object' => $dienst->gemeente,
            'bijzonderheid' => $dienst->bijzonderheid?->naam,
            'spreker_naam' => $sprekerNaam,
            'status' => $spreekbeurt !== null ? 'bevestigd' : null,
        ];
    }
}
