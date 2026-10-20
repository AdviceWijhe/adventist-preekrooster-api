<?php

declare(strict_types=1);

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Models\Bericht;
use App\Models\User;
use App\Services\Bericht\BerichtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class BerichtController extends Controller
{
    public function __construct(
        private readonly BerichtService $berichtService,
    ) {}

    public function index(): JsonResponse
    {
        $this->ensureAdmin();

        $berichten = Bericht::query()
            ->with(['auteur:id,voornaam,tussenvoegsel,achternaam'])
            ->latest()
            ->get()
            ->map(fn (Bericht $bericht) => $this->toPayload($bericht));

        return response()->json(['data' => $berichten]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureAdmin();

        /** @var User $auteur */
        $auteur = $request->user();
        $validated = $this->valideer($request);

        $bericht = Bericht::query()->create([
            ...$validated,
            'auteur_id' => $auteur->id,
        ]);

        if ($request->boolean('direct_publiceren')) {
            $this->berichtService->publiceer($bericht);
        }

        return response()->json(['data' => $this->toPayload($bericht->fresh(['auteur']))], 201);
    }

    public function update(Request $request, Bericht $bericht): JsonResponse
    {
        $this->ensureAdmin();

        if ($bericht->isGepubliceerd()) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Gepubliceerde berichten kunnen niet meer worden gewijzigd.');
        }

        $validated = $this->valideer($request);
        $bericht->update($validated);

        return response()->json(['data' => $this->toPayload($bericht->fresh(['auteur']))]);
    }

    public function destroy(Bericht $bericht): JsonResponse
    {
        $this->ensureAdmin();
        $bericht->delete();

        return response()->json(null, 204);
    }

    public function publiceren(Bericht $bericht): JsonResponse
    {
        $this->ensureAdmin();

        $this->berichtService->publiceer($bericht);

        return response()->json([
            'data' => $this->toPayload($bericht->fresh(['auteur'])),
            'message' => __('api.beheer.message_published'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function regels(): array
    {
        return [
            'titel' => ['required', 'string', 'max:255'],
            'inhoud' => ['required', 'string', 'max:10000'],
            'doelgroep' => ['required', Rule::in([
                Bericht::DOELGROEP_ALLE,
                Bericht::DOELGROEP_PREDIKANTEN,
                Bericht::DOELGROEP_BEHEERDERS,
                Bericht::DOELGROEP_SPECIFIEK,
            ])],
            'gemeente_ids' => ['nullable', 'array'],
            'gemeente_ids.*' => ['integer', 'exists:gemeentes,id'],
            'gebruiker_ids' => ['nullable', 'array'],
            'gebruiker_ids.*' => ['integer', 'exists:users,id'],
            'kanaal' => ['required', Rule::in([
                Bericht::KANAAL_INTERN,
                Bericht::KANAAL_EMAIL,
                Bericht::KANAAL_BEIDE,
            ])],
            'direct_publiceren' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function valideer(Request $request): array
    {
        $validated = $request->validate($this->regels());

        $gemeenteIds = $validated['gemeente_ids'] ?? [];
        $gebruikerIds = $validated['gebruiker_ids'] ?? [];

        if ($validated['doelgroep'] === Bericht::DOELGROEP_SPECIFIEK && $gemeenteIds === [] && $gebruikerIds === []) {
            throw ValidationException::withMessages([
                'doelgroep' => 'Kies minimaal één gemeente of gebruiker voor een specifieke doelgroep.',
            ]);
        }

        if ($validated['doelgroep'] !== Bericht::DOELGROEP_SPECIFIEK) {
            $validated['gemeente_ids'] = null;
            $validated['gebruiker_ids'] = null;
        }

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function toPayload(Bericht $bericht): array
    {
        return [
            'id' => $bericht->id,
            'titel' => $bericht->titel,
            'inhoud' => $bericht->inhoud,
            'doelgroep' => $bericht->doelgroep,
            'gemeente_ids' => $bericht->gemeente_ids ?? [],
            'gebruiker_ids' => $bericht->gebruiker_ids ?? [],
            'kanaal' => $bericht->kanaal,
            'gepubliceerd_op' => $bericht->gepubliceerd_op?->toIso8601String(),
            'auteur' => $bericht->auteur ? [
                'id' => $bericht->auteur->id,
                'naam' => $bericht->auteur->volledigeNaam(),
            ] : null,
            'created_at' => $bericht->created_at?->toIso8601String(),
        ];
    }

    private function ensureAdmin(): void
    {
        if (! request()->user()?->isAdmin()) {
            abort(Response::HTTP_FORBIDDEN, 'Alleen administrators kunnen berichten beheren.');
        }
    }
}
