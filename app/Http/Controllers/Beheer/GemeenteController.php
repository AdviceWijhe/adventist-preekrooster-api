<?php

declare(strict_types=1);

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Http\Requests\GemeenteRequest;
use App\Models\Dienst;
use App\Models\Gemeente;
use App\Models\Publicatie;
use App\Models\User;
use App\Services\RoosterAutorisatieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GemeenteController extends Controller
{
    public function __construct(
        private readonly RoosterAutorisatieService $authz,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));

        $gemeentes = Gemeente::query()
            ->with(['district', 'predikant', 'contactpersoon'])
            ->when(! $this->authz->isGlobaalBeheerder($user), function ($query) use ($user): void {
                $query->whereIn('id', $this->authz->beheerbareGemeenteIds($user));
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('naam', 'like', "%{$search}%")
                        ->orWhere('naam_kort', 'like', "%{$search}%")
                        ->orWhere('plaats', 'like', "%{$search}%")
                        ->orWhere('postcode', 'like', "%{$search}%")
                        ->orWhereHas('district', function ($districtQuery) use ($search): void {
                            $districtQuery->where('naam', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('naam')
            ->orderBy('id')
            ->get();

        return response()->json(['data' => $gemeentes]);
    }

    public function store(GemeenteRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($this->authz->isGlobaalBeheerder($user), 403);

        $gemeente = Gemeente::query()->create($request->validated());

        return response()->json(['data' => $gemeente->load(['district', 'predikant'])], 201);
    }

    public function show(Request $request, Gemeente $gemeente): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($this->authz->kanGemeenteBewerken($user, $gemeente->id), 403);

        return response()->json(['data' => $gemeente->load(['district', 'predikant', 'contactpersoon'])]);
    }

    public function update(GemeenteRequest $request, Gemeente $gemeente): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($this->authz->kanGemeenteBewerken($user, $gemeente->id), 403);

        $data = $request->validated();

        if (! $this->authz->isGlobaalBeheerder($user)) {
            unset($data['predikant_id'], $data['contactpersoon_id']);
        }

        $gemeente->update($data);

        return response()->json(['data' => $gemeente->fresh(['district', 'predikant'])]);
    }

    public function destroy(Request $request, Gemeente $gemeente): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($this->authz->isGlobaalBeheerder($user), 403);

        if (Dienst::query()->where('gemeente_id', $gemeente->id)->exists()) {
            return response()->json([
                'message' => __('api.beheer.gemeente_has_diensten'),
            ], 409);
        }

        if (Publicatie::query()->where('gemeente_id', $gemeente->id)->exists()) {
            return response()->json([
                'message' => __('api.beheer.gemeente_has_publications'),
            ], 409);
        }

        $gemeente->delete();

        return response()->json(null, 204);
    }
}
