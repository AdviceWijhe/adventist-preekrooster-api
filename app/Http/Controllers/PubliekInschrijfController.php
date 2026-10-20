<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Dienst;
use App\Services\RoosterAutorisatieService;
use App\Services\SpreekbeurtKoppelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PubliekInschrijfController extends Controller
{
    public function store(
        Request $request,
        Dienst $dienst,
        SpreekbeurtKoppelService $service,
        RoosterAutorisatieService $autorisatie,
    ): JsonResponse {
        $user = $request->user();
        abort_if($user === null, 401);

        abort_unless($user->active && $autorisatie->kanZichzelfInschrijven($user), 403, __('api.signup.only_speakers'));

        $spreekbeurt = $service->koppel($user, $dienst, $user, validateRol: false, requirePublishedPubliekVenster: true);

        return response()->json(['data' => $spreekbeurt->load(['spreker', 'dienst.gemeente'])], 201);
    }
}
