<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Dienst;
use App\Models\Note;
use App\Models\User;
use App\Services\RoosterAutorisatieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function __construct(
        private readonly RoosterAutorisatieService $authz,
    ) {}

    public function index(Request $request, Dienst $dienst): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($this->authz->kanRoosterBeheren($user, $dienst->gemeente_id), 403);

        return response()->json([
            'data' => $dienst->notes()->with('user:id,voornaam,tussenvoegsel,achternaam')->latest()->get(),
        ]);
    }

    public function store(Request $request, Dienst $dienst): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($this->authz->kanRoosterBeheren($user, $dienst->gemeente_id), 403);

        $data = $request->validate(['tekst' => ['required', 'string', 'max:1000']]);

        $note = $dienst->notes()->create([
            'user_id' => $request->user()->id,
            'tekst' => $data['tekst'],
        ]);

        return response()->json(['data' => $note->load('user:id,voornaam,tussenvoegsel,achternaam')], 201);
    }

    public function destroy(Request $request, Dienst $dienst, Note $note): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($this->authz->kanRoosterBeheren($user, $dienst->gemeente_id), 403);

        abort_if($note->dienst_id !== $dienst->id, 404);
        abort_if($note->user_id !== request()->user()->id && ! request()->user()->hasRole('admin'), 403);

        $note->delete();

        return response()->json(null, 204);
    }
}
