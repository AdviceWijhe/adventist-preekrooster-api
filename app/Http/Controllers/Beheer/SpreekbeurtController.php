<?php

declare(strict_types=1);

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Mail\PredikantUitvraagMail;
use App\Models\Dienst;
use App\Models\Option;
use App\Models\Spreekbeurt;
use App\Models\User;
use App\Services\RoosterAutorisatieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class SpreekbeurtController extends Controller
{
    public function __construct(
        private readonly RoosterAutorisatieService $authz,
    ) {}

    public function store(Request $request, Dienst $dienst): JsonResponse
    {
        if (Option::getValue('rooster_vergrendeld') === '1') {
            return response()->json(['message' => __('api.beheer.rooster_locked')], 403);
        }

        /** @var User $user */
        $user = $request->user();
        abort_unless($this->authz->kanRoosterBeheren($user, $dienst->gemeente_id), 403);

        $data = $request->validate([
            'spreker_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('active', true)),
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    $user = User::query()->whereKey($value)->first();

                    if ($user !== null && ! $user->kanPreken()) {
                        $fail('De geselecteerde gebruiker is geen actieve spreker of predikant.');
                    }
                },
            ],
        ]);

        $spreekbeurt = Spreekbeurt::query()->create([
            'dienst_id' => $dienst->id,
            'spreker_id' => $data['spreker_id'],
            'ingevoerd_door' => $request->user()->id,
        ]);

        $spreekbeurt->loadMissing(['spreker', 'dienst.gemeente']);

        if (filled($spreekbeurt->spreker?->email) && config('mail.features.predikant_uitvraag', true)) {
            Mail::to($spreekbeurt->spreker->email)->send(new PredikantUitvraagMail($spreekbeurt));
        }

        return response()->json(['data' => $spreekbeurt->load(['spreker', 'dienst'])], 201);
    }

    public function destroy(Request $request, Dienst $dienst, Spreekbeurt $spreekbeurt): JsonResponse
    {
        if (Option::getValue('rooster_vergrendeld') === '1') {
            return response()->json(['message' => __('api.beheer.rooster_locked')], 403);
        }

        /** @var User $user */
        $user = $request->user();
        abort_unless($this->authz->kanRoosterBeheren($user, $dienst->gemeente_id), 403);

        abort_if($spreekbeurt->dienst_id !== $dienst->id, 404);

        $spreekbeurt->delete();

        return response()->json(null, 204);
    }
}
