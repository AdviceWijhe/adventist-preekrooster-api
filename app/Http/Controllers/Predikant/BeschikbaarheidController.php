<?php

declare(strict_types=1);

namespace App\Http\Controllers\Predikant;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserBeschikbaarheid;
use App\Services\RoosterAutorisatieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BeschikbaarheidController extends Controller
{
    public function __construct(
        private readonly RoosterAutorisatieService $autorisatie,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertMagBeschikbaarheidBeheren($request);

        $beschikbaarheid = UserBeschikbaarheid::query()
            ->where('user_id', $request->user()->id)
            ->where('datum_tot', '>=', today())
            ->orderBy('datum_van')
            ->get();

        return response()->json(['data' => $beschikbaarheid]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertMagBeschikbaarheidBeheren($request);

        $data = $request->validate([
            'datum_van' => ['required', 'date', 'after_or_equal:today'],
            'datum_tot' => ['required', 'date', 'after_or_equal:datum_van'],
            'opmerking' => ['nullable', 'string', 'max:200'],
        ]);

        $beschikbaarheid = UserBeschikbaarheid::query()->create([
            ...$data,
            'user_id' => $request->user()->id,
        ]);

        return response()->json(['data' => $beschikbaarheid], 201);
    }

    public function update(Request $request, UserBeschikbaarheid $beschikbaarheid): JsonResponse
    {
        $this->assertMagBeschikbaarheidBeheren($request);
        abort_if($beschikbaarheid->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'datum_van' => ['required', 'date', 'after_or_equal:today'],
            'datum_tot' => ['required', 'date', 'after_or_equal:datum_van'],
            'opmerking' => ['nullable', 'string', 'max:200'],
        ]);

        $beschikbaarheid->update($data);

        return response()->json(['data' => $beschikbaarheid->fresh()]);
    }

    public function destroy(Request $request, UserBeschikbaarheid $beschikbaarheid): JsonResponse
    {
        $this->assertMagBeschikbaarheidBeheren($request);
        abort_if($beschikbaarheid->user_id !== $request->user()->id, 403);
        $beschikbaarheid->delete();

        return response()->json(null, 204);
    }

    private function assertMagBeschikbaarheidBeheren(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless(
            $this->autorisatie->kanZichzelfInschrijven($user),
            403,
            __('api.signup.only_speakers')
        );
    }
}
