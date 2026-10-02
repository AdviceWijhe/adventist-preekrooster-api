<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Bericht;
use App\Models\User;
use App\Services\Bericht\BerichtService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BerichtInboxController extends Controller
{
    public function __construct(
        private readonly BerichtService $berichtService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => $this->mapBerichten($this->berichtService->inboxQuery($user), $user),
        ]);
    }

    public function prullenbak(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => $this->mapBerichten($this->berichtService->prullenbakQuery($user), $user),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => [
                'count' => $this->berichtService->unreadCount($user),
            ],
        ]);
    }

    public function markeerGelezen(Request $request, Bericht $bericht): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->berichtService->isZichtbaarVoor($bericht, $user)) {
            abort(404);
        }

        $this->berichtService->markeerGelezen($bericht, $user);

        return response()->json([
            'data' => $this->toPayload($bericht->fresh(['auteur', 'gelezenDoor', 'verwijderdDoor']), $user),
        ]);
    }

    public function markeerOngelezen(Request $request, Bericht $bericht): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->berichtService->isZichtbaarVoor($bericht, $user)) {
            abort(404);
        }

        $this->berichtService->markeerOngelezen($bericht, $user);

        return response()->json([
            'data' => $this->toPayload($bericht->fresh(['auteur', 'gelezenDoor', 'verwijderdDoor']), $user),
        ]);
    }

    public function verplaatsNaarPrullenbak(Request $request, Bericht $bericht): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->berichtService->isZichtbaarVoor($bericht, $user)) {
            abort(404);
        }

        $this->berichtService->verplaatsNaarPrullenbak($bericht, $user);

        return response()->json([
            'data' => $this->toPayload($bericht->fresh(['auteur', 'gelezenDoor', 'verwijderdDoor']), $user),
        ]);
    }

    public function herstel(Request $request, Bericht $bericht): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->berichtService->isZichtbaarVoor($bericht, $user)) {
            abort(404);
        }

        $this->berichtService->herstelUitPrullenbak($bericht, $user);

        return response()->json([
            'data' => $this->toPayload($bericht->fresh(['auteur', 'gelezenDoor', 'verwijderdDoor']), $user),
        ]);
    }

    /**
     * @param  Builder<Bericht>  $query
     * @return list<array<string, mixed>>
     */
    private function mapBerichten($query, User $user): array
    {
        return $query
            ->with(['auteur:id,voornaam,achternaam,tussenvoegsel,photo'])
            ->with(['gelezenDoor' => fn ($q) => $q->where('users.id', $user->id)])
            ->with(['verwijderdDoor' => fn ($q) => $q->where('users.id', $user->id)])
            ->get()
            ->map(fn (Bericht $bericht) => $this->toPayload($bericht, $user))
            ->all();
    }

    private function toPayload(Bericht $bericht, User $user): array
    {
        $verwijderdPivot = $bericht->relationLoaded('verwijderdDoor')
            ? $bericht->verwijderdDoor->firstWhere('id', $user->id)?->pivot
            : null;

        return [
            'id' => $bericht->id,
            'titel' => $bericht->titel,
            'inhoud' => $bericht->inhoud,
            'doelgroep' => $bericht->doelgroep,
            'gepubliceerd_op' => $bericht->gepubliceerd_op?->toIso8601String(),
            'auteur' => $bericht->auteur ? [
                'id' => $bericht->auteur->id,
                'naam' => $bericht->auteur->volledigeNaam(),
                'photo_url' => $bericht->auteur->photo_url,
            ] : null,
            'gelezen' => $bericht->relationLoaded('gelezenDoor') && $bericht->gelezenDoor->isNotEmpty(),
            'verwijderd' => $verwijderdPivot !== null,
            'verwijderd_op' => $verwijderdPivot?->verwijderd_op
                ? (string) $verwijderdPivot->verwijderd_op
                : null,
        ];
    }
}
