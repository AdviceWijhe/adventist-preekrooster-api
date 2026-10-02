<?php

declare(strict_types=1);

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Http\Requests\DienstRequest;
use App\Models\Dienst;
use App\Models\Gemeente;
use App\Models\Option;
use App\Models\User;
use App\Services\RoosterAutorisatieService;
use App\Services\RoosterMatrixService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DienstController extends Controller
{
    public function __construct(
        private readonly RoosterAutorisatieService $authz,
    ) {}

    public function standaardMaand(RoosterMatrixService $service): JsonResponse
    {
        return response()->json($service->beheerStandaardMaand());
    }

    public function matrix(Request $request, RoosterMatrixService $service): JsonResponse
    {
        $data = $request->validate([
            'maand' => ['required', 'date_format:Y-m'],
        ]);

        $payload = $service->buildVoorBeheer($data['maand']);
        $user = $request->user();

        if ($user !== null && ! $this->authz->isGlobaalBeheerder($user)) {
            $allowed = array_flip($this->authz->beheerbareGemeenteIds($user));
            $payload['districts'] = array_values(array_filter(array_map(
                function (array $district) use ($allowed): ?array {
                    $gemeentes = array_values(array_filter(
                        $district['gemeentes'] ?? [],
                        fn (array $g): bool => isset($allowed[$g['id']])
                    ));
                    if ($gemeentes === []) {
                        return null;
                    }
                    $district['gemeentes'] = $gemeentes;

                    return $district;
                },
                $payload['districts']
            )));
        }

        return response()->json($payload);
    }

    public function index(Request $request): JsonResponse
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

                    $fail('De geselecteerde gemeente is ongeldig.');
                },
            ],
        ]);
        /** @var User $user */
        $user = $request->user();
        $maand = $data['maand'];
        $gemeenteId = $data['gemeente_id'] ?? null;

        if ($gemeenteId !== null && $gemeenteId !== 'all') {
            abort_unless($this->authz->kanRoosterBeheren($user, (int) $gemeenteId), 403);
        } elseif (! $this->authz->isGlobaalBeheerder($user)) {
            $scopeIds = $this->authz->beheerbareGemeenteIds($user);
            if ($scopeIds === []) {
                abort(403);
            }
        }

        $dienstenQuery = Dienst::query()
            ->with(['gemeente:id,naam', 'bijzonderheid:id,naam', 'spreekbeurten.spreker:id,voornaam,tussenvoegsel,achternaam,initialen,photo'])
            ->whereYear('datum', substr($maand, 0, 4))
            ->whereMonth('datum', substr($maand, 5, 2))
            ->orderBy('datum')
            ->orderBy('gemeente_id');

        if ($gemeenteId !== null && $gemeenteId !== 'all') {
            $dienstenQuery->where('gemeente_id', (int) $gemeenteId);
        } elseif (! $this->authz->isGlobaalBeheerder($user)) {
            $dienstenQuery->whereIn('gemeente_id', $this->authz->beheerbareGemeenteIds($user));
        }

        $diensten = $dienstenQuery
            ->get()
            ->map(fn (Dienst $dienst) => $this->mapDienst($dienst))
            ->values();

        return response()->json(['data' => $diensten]);
    }

    public function store(DienstRequest $request): JsonResponse
    {
        if (Option::getValue('rooster_vergrendeld') === '1') {
            return response()->json(['message' => __('api.beheer.rooster_locked')], 403);
        }

        $data = $request->validated();
        /** @var User $user */
        $user = $request->user();
        abort_unless($this->authz->kanRoosterBeheren($user, (int) $data['gemeente_id']), 403);
        $data['taal'] = $data['taal'] ?? 'nl';
        $data['dienstwijze'] = $data['dienstwijze'] ?? 'fysiek';

        $bestaat = Dienst::query()
            ->whereDate('datum', $data['datum'])
            ->where('gemeente_id', $data['gemeente_id'])
            ->exists();

        if ($bestaat) {
            return response()->json([
                'message' => __('api.beheer.dienst_exists'),
                'errors' => ['datum' => ['Er staat al een dienst op deze datum voor deze gemeente.']],
            ], 422);
        }

        $dienst = Dienst::query()->create($data);

        return response()->json(['data' => $dienst->load(['gemeente', 'bijzonderheid'])], 201);
    }

    public function update(DienstRequest $request, Dienst $dienst): JsonResponse
    {
        if (Option::getValue('rooster_vergrendeld') === '1') {
            return response()->json(['message' => __('api.beheer.rooster_locked')], 403);
        }

        $data = $request->validated();
        /** @var User $user */
        $user = $request->user();
        abort_unless($this->authz->kanRoosterBeheren($user, $dienst->gemeente_id), 403);
        abort_unless($this->authz->kanRoosterBeheren($user, (int) $data['gemeente_id']), 403);
        $data['taal'] = $data['taal'] ?? $dienst->taal ?? 'nl';
        $data['dienstwijze'] = $data['dienstwijze'] ?? $dienst->dienstwijze ?? 'fysiek';

        $bestaat = Dienst::query()
            ->whereDate('datum', $data['datum'])
            ->where('gemeente_id', $data['gemeente_id'])
            ->where('id', '!=', $dienst->id)
            ->exists();

        if ($bestaat) {
            return response()->json([
                'message' => __('api.beheer.dienst_exists'),
                'errors' => ['datum' => ['Er staat al een dienst op deze datum voor deze gemeente.']],
            ], 422);
        }

        $dienst->update($data);

        return response()->json(['data' => $dienst->fresh(['gemeente', 'bijzonderheid'])]);
    }

    public function destroy(Request $request, Dienst $dienst): JsonResponse
    {
        if (Option::getValue('rooster_vergrendeld') === '1') {
            return response()->json(['message' => __('api.beheer.rooster_locked')], 403);
        }

        /** @var User $user */
        $user = $request->user();
        abort_unless($this->authz->kanRoosterBeheren($user, $dienst->gemeente_id), 403);

        $dienst->delete();

        return response()->json(null, 204);
    }

    public function beschikbareSprekers(Request $request): JsonResponse
    {
        $data = $request->validate([
            'datum' => ['required', 'date'],
            'gemeente_id' => ['required', 'exists:gemeentes,id'],
        ]);

        /** @var User $user */
        $user = $request->user();
        abort_unless($this->authz->kanRoosterBeheren($user, (int) $data['gemeente_id']), 403);

        $sprekers = User::query()
            ->where('active', true)
            ->where(function ($query): void {
                $query
                    ->whereHas('functies', fn ($q) => $q->whereIn('slug', ['spreker', 'predikant']))
                    ->orWhereHas('roles', fn ($q) => $q->where('slug', 'predikant'));
            })
            ->whereDoesntHave('beschikbaarheid', function ($query) use ($data): void {
                $query->whereDate('datum_van', '<=', $data['datum'])
                    ->whereDate('datum_tot', '>=', $data['datum']);
            })
            ->whereDoesntHave('spreekbeurten', function ($query) use ($data): void {
                $query->whereHas('dienst', fn ($dienstQuery) => $dienstQuery->whereDate('datum', $data['datum']))
                    ->where('bevestigd', '!=', 0);
            })
            ->with(['functies:id,slug,naam'])
            ->orderByDesc('landelijk_actief')
            ->orderBy('voornaam')
            ->orderBy('achternaam')
            ->get(['id', 'voornaam', 'tussenvoegsel', 'achternaam', 'initialen', 'photo', 'landelijk_actief', 'spreekniveau']);

        return response()->json(['data' => $sprekers]);
    }

    private function mapDienst(Dienst $dienst): array
    {
        $spreekbeurt = $dienst->spreekbeurten->first();
        $status = match ((int) ($spreekbeurt?->bevestigd ?? -1)) {
            1 => 'bevestigd',
            0 => 'afgewezen',
            default => 'uitgevraagd',
        };

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
            'gemeente' => $dienst->gemeente,
            'bijzonderheid' => $dienst->bijzonderheid?->naam,
            'bijzonderheid_id' => $dienst->bijzonderheid_id,
            'eigeninvulling' => $dienst->eigeninvulling,
            'spreker_naam' => $sprekerNaam,
            'status' => $status,
            'spreekbeurten' => $dienst->spreekbeurten,
        ];
    }
}
