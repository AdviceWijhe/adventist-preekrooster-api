<?php

declare(strict_types=1);

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Http\Requests\GebruikerRequest;
use App\Mail\ProfielGewijzigdMail;
use App\Models\Functie;
use App\Models\Gemeente;
use App\Models\Publicatie;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\GebruikerUitnodigingService;
use App\Services\Auth\SessionInvalidationService;
use App\Services\GebruikerRolAutorisatieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

class GebruikerController extends Controller
{
    public function __construct(
        private readonly GebruikerRolAutorisatieService $rolAutorisatie,
        private readonly GebruikerUitnodigingService $uitnodiging,
        private readonly SessionInvalidationService $sessionInvalidation,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $gebruikers = User::query()
            ->with('roles', 'gemeente', 'functies', 'gemeentes')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('voornaam', 'like', "%{$search}%")
                        ->orWhere('tussenvoegsel', 'like', "%{$search}%")
                        ->orWhere('achternaam', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('achternaam')
            ->get()
            ->makeHidden('password');

        return response()->json(['data' => $gebruikers]);
    }

    public function store(GebruikerRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->rolAutorisatie->assertKanRolToewijzen($request->user(), null, $data['role']);
        $role = Role::query()->where('slug', $data['role'])->firstOrFail();

        $primaireGemeente = $this->primaireGemeente($data);
        $data['gemeente_id'] = $primaireGemeente;

        if (empty($data['password'])) {
            $data['password'] = Str::password(32);
        }

        $gebruiker = User::query()->create($this->userKolommen($request, $data));
        $gebruiker->roles()->attach($role->id, ['gemeente_id' => $primaireGemeente]);
        $this->syncFuncties($gebruiker, $data);
        $this->wisSpreekniveauZonderSprekersfunctie($gebruiker, $data);
        $this->syncGemeentes($gebruiker, $data, $primaireGemeente);
        $this->syncGemeenteRollen($gebruiker);

        if (filled($gebruiker->email)) {
            $this->uitnodiging->stuurUitnodiging($gebruiker);
        }

        return response()->json([
            'data' => $gebruiker->load('roles', 'functies', 'gemeentes'),
        ], 201);
    }

    public function stuurUitnodiging(Request $request, User $gebruiker): JsonResponse
    {
        $this->rolAutorisatie->assertKanGebruikerBeheren($request->user(), $gebruiker);

        try {
            $this->uitnodiging->stuurUitnodiging($gebruiker);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => __('api.beheer.uitnodiging_verstuurd'),
        ]);
    }

    public function show(Request $request, User $gebruiker): JsonResponse
    {
        $this->rolAutorisatie->assertKanGebruikerBeheren($request->user(), $gebruiker);

        return response()->json([
            'data' => $gebruiker->load('roles', 'gemeente', 'functies', 'gemeentes')->makeHidden('password'),
        ]);
    }

    public function update(GebruikerRequest $request, User $gebruiker): JsonResponse
    {
        $this->rolAutorisatie->assertKanGebruikerBeheren($request->user(), $gebruiker);

        $data = $request->validated();
        $this->rolAutorisatie->assertKanGevoeligeVeldenWijzigen($request->user(), $gebruiker, $data);
        $oudeEmail = (string) $gebruiker->email;

        $primaireGemeente = $this->primaireGemeente($data);
        $data['gemeente_id'] = $primaireGemeente;

        if (isset($data['role'])) {
            $this->rolAutorisatie->assertKanRolToewijzen($request->user(), $gebruiker, $data['role']);
            $role = Role::query()->where('slug', $data['role'])->firstOrFail();
            $gebruiker->roles()->sync([$role->id => ['gemeente_id' => $primaireGemeente]]);
        }

        $kolommen = $this->userKolommen($request, $data);
        $moetSessiesInvalideren = array_key_exists('password', $kolommen)
            || (array_key_exists('active', $kolommen) && $kolommen['active'] === false && $gebruiker->active);

        $gebruiker->update($kolommen);
        $gewijzigdeVelden = $this->betekenisvolleWijzigingen($gebruiker);
        $this->syncFuncties($gebruiker, $data);
        $this->wisSpreekniveauZonderSprekersfunctie($gebruiker, $data);
        $gewijzigdeVelden = array_values(array_unique([
            ...$gewijzigdeVelden,
            ...$this->betekenisvolleWijzigingen($gebruiker),
        ]));
        $this->syncGemeentes($gebruiker, $data, $primaireGemeente);
        $this->syncGemeenteRollen($gebruiker);

        if ($moetSessiesInvalideren) {
            $this->sessionInvalidation->invalidateFor($gebruiker->fresh());
        }

        $this->stuurProfielGewijzigdMail($gebruiker, $oudeEmail, $gewijzigdeVelden);

        return response()->json([
            'data' => $gebruiker->fresh(['roles', 'functies', 'gemeentes'])->makeHidden('password'),
        ]);
    }

    /**
     * Bepaalt de primaire gemeente (eerste van gemeente_ids, anders gemeente_id).
     *
     * @param  array<string, mixed>  $data
     */
    private function primaireGemeente(array $data): ?int
    {
        if (! empty($data['gemeente_ids']) && is_array($data['gemeente_ids'])) {
            return (int) $data['gemeente_ids'][0];
        }

        return isset($data['gemeente_id']) ? (int) $data['gemeente_id'] : null;
    }

    /**
     * Filtert de aanleverdata tot enkel kolommen op de users-tabel.
     * statistieken_toegang is voorbehouden aan admins (privilege-escalatie).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function userKolommen(Request $request, array $data): array
    {
        unset($data['role'], $data['functies'], $data['gemeente_ids']);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        if (! $request->user()?->isAdmin()) {
            unset($data['statistieken_toegang']);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncFuncties(User $gebruiker, array $data): void
    {
        if (! array_key_exists('functies', $data)) {
            return;
        }

        $functieIds = Functie::query()
            ->whereIn('slug', (array) $data['functies'])
            ->pluck('id')
            ->all();

        $gebruiker->functies()->sync($functieIds);
        $gebruiker->unsetRelation('functies');
    }

    /** Spreekniveau hoort alleen bij spreker/predikant; wissen bij overige functiekeuze. */
    private function wisSpreekniveauZonderSprekersfunctie(User $gebruiker, array $data): void
    {
        if (! array_key_exists('functies', $data)) {
            return;
        }

        if ($gebruiker->hasAnyFunctie(['spreker', 'predikant'])) {
            return;
        }

        if ($gebruiker->spreekniveau === null) {
            return;
        }

        $gebruiker->forceFill(['spreekniveau' => null])->save();
    }

    /**
     * @return list<string>
     */
    private function betekenisvolleWijzigingen(User $gebruiker): array
    {
        return array_values(array_filter(
            array_keys($gebruiker->getChanges()),
            static fn (string $veld): bool => ! in_array($veld, ['updated_at', 'password'], true)
        ));
    }

    /**
     * @param  list<string>  $gewijzigdeVelden
     */
    private function stuurProfielGewijzigdMail(User $gebruiker, string $oudeEmail, array $gewijzigdeVelden): void
    {
        if (! config('mail.features.profiel_gewijzigd', true) || $gewijzigdeVelden === [] || ! filled($gebruiker->email)) {
            return;
        }

        try {
            $mailNaar = (string) $gebruiker->email;
            if ($oudeEmail !== '' && $oudeEmail !== $mailNaar) {
                Mail::to($oudeEmail)->send(new ProfielGewijzigdMail($gebruiker, $gewijzigdeVelden));
            }
            Mail::to($mailNaar)->send(new ProfielGewijzigdMail($gebruiker, $gewijzigdeVelden));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncGemeentes(User $gebruiker, array $data, ?int $primaireGemeente): void
    {
        if (! array_key_exists('gemeente_ids', $data)) {
            return;
        }

        $ids = array_values(array_unique(array_map('intval', (array) $data['gemeente_ids'])));

        if ($primaireGemeente !== null && ! in_array($primaireGemeente, $ids, true)) {
            $ids[] = $primaireGemeente;
        }

        $gebruiker->gemeentes()->sync($ids);
    }

    /**
     * Houd gemeente.predikant_id / contactpersoon_id in sync met functie + gekoppelde gemeentes.
     * Zo krijgt een predikant/contactpersoon automatisch rooster-scope op die gemeenten.
     */
    private function syncGemeenteRollen(User $gebruiker): void
    {
        $gebruiker->loadMissing('functies');
        $gemeenteIds = $gebruiker->gemeentes()->pluck('gemeentes.id')->map(fn ($id) => (int) $id)->all();
        $isPredikant = $gebruiker->hasFunctie('predikant');
        $isContactpersoon = $gebruiker->hasFunctie('contactpersoon');

        Gemeente::query()
            ->where('predikant_id', $gebruiker->id)
            ->when(
                $isPredikant && $gemeenteIds !== [],
                fn ($q) => $q->whereNotIn('id', $gemeenteIds),
                fn ($q) => $q
            )
            ->update(['predikant_id' => null]);

        if ($isPredikant && $gemeenteIds !== []) {
            Gemeente::query()->whereIn('id', $gemeenteIds)->update(['predikant_id' => $gebruiker->id]);
        }

        Gemeente::query()
            ->where('contactpersoon_id', $gebruiker->id)
            ->when(
                $isContactpersoon && $gemeenteIds !== [],
                fn ($q) => $q->whereNotIn('id', $gemeenteIds),
                fn ($q) => $q
            )
            ->update(['contactpersoon_id' => null]);

        if ($isContactpersoon && $gemeenteIds !== []) {
            Gemeente::query()->whereIn('id', $gemeenteIds)->update(['contactpersoon_id' => $gebruiker->id]);
        }
    }

    public function destroy(Request $request, User $gebruiker): JsonResponse
    {
        $this->rolAutorisatie->assertKanGebruikerBeheren($request->user(), $gebruiker);

        if ($gebruiker->is($request->user())) {
            return response()->json([
                'message' => __('api.beheer.cannot_delete_self'),
            ], 403);
        }

        if ($gebruiker->spreekbeurten()->exists()) {
            return response()->json([
                'message' => __('api.beheer.cannot_delete_has_beurten'),
            ], 409);
        }

        if (Gemeente::query()
            ->where('predikant_id', $gebruiker->id)
            ->orWhere('contactpersoon_id', $gebruiker->id)
            ->exists()) {
            return response()->json([
                'message' => __('api.beheer.cannot_delete_gemeente_role'),
            ], 409);
        }

        if ($gebruiker->ingevoerdeSpreekbeurten()->exists()) {
            return response()->json([
                'message' => __('api.beheer.cannot_delete_entered_beurten'),
            ], 409);
        }

        if (Publicatie::query()->where('gepubliceerd_door', $gebruiker->id)->exists()) {
            return response()->json([
                'message' => __('api.beheer.cannot_delete_has_publications'),
            ], 409);
        }

        $gebruiker->delete();

        return response()->json(null, 204);
    }
}
