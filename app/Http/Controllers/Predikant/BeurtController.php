<?php

declare(strict_types=1);

namespace App\Http\Controllers\Predikant;

use App\Http\Controllers\Controller;
use App\Mail\BeurtAnnuleringMail;
use App\Mail\BeurtStatusMeldingMail;
use App\Models\Spreekbeurt;
use App\Models\User;
use App\Services\Avg\AvgConsentService;
use App\Services\Instellingen\InstellingenService;
use App\Services\RoosterAutorisatieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class BeurtController extends Controller
{
    public function __construct(
        private readonly InstellingenService $instellingenService,
        private readonly RoosterAutorisatieService $autorisatie,
        private readonly AvgConsentService $avgConsent,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertMagBeurtenBeheren($request);

        $beurten = Spreekbeurt::query()
            ->with(['dienst.gemeente', 'dienst.bijzonderheid'])
            ->where('spreker_id', $request->user()->id)
            ->join('diensten', 'spreekbeurten.dienst_id', '=', 'diensten.id')
            ->orderBy('diensten.datum')
            ->orderBy('spreekbeurten.created_at')
            ->select('spreekbeurten.*')
            ->get();

        return response()->json(['data' => $beurten]);
    }

    public function show(Request $request, Spreekbeurt $spreekbeurt): JsonResponse
    {
        $this->assertMagBeurtenBeheren($request);
        abort_if($spreekbeurt->spreker_id !== $request->user()->id, 403);

        $spreekbeurt->load([
            'ingevoerdDoor:id,voornaam,tussenvoegsel,achternaam,email',
            'dienst.bijzonderheid',
            'dienst.gemeente.district:id,naam',
            'dienst.gemeente.contactpersoon:id,voornaam,tussenvoegsel,achternaam,email,telefoonnummer,mobiel,avg_consent_at',
        ]);

        $viewer = $request->user();
        $contact = $spreekbeurt->dienst?->gemeente?->contactpersoon;
        if ($contact instanceof User) {
            $this->avgConsent->redactContactVoorViewer($contact, $viewer);
        }
        $ingevoerdDoor = $spreekbeurt->ingevoerdDoor;
        if ($ingevoerdDoor instanceof User) {
            $this->avgConsent->redactContactVoorViewer($ingevoerdDoor, $viewer);
        }

        return response()->json(['data' => $spreekbeurt]);
    }

    public function bevestig(Request $request, Spreekbeurt $spreekbeurt): JsonResponse
    {
        $this->assertMagBeurtenBeheren($request);
        abort_if($spreekbeurt->spreker_id !== $request->user()->id, 403);

        $data = $request->validate([
            'bevestigd' => ['required', 'in:0,1'],
            'bericht' => ['nullable', 'string', 'max:500'],
        ]);

        $spreekbeurt->update($data);
        $spreekbeurt->loadMissing(['dienst.gemeente', 'spreker']);

        $contactEmail = $spreekbeurt->dienst?->gemeente?->contactpersoon?->email;
        $emails = filled($contactEmail) ? [$contactEmail] : [];
        if (config('mail.features.beurt_status_melding', true)) {
            foreach ($emails as $email) {
                Mail::to($email)->send(new BeurtStatusMeldingMail($spreekbeurt));
            }
        }

        return response()->json(['data' => $spreekbeurt->fresh()]);
    }

    public function annuleer(Request $request, Spreekbeurt $spreekbeurt): Response
    {
        $this->assertMagBeurtenBeheren($request);
        abort_if($spreekbeurt->spreker_id !== $request->user()->id, 403);

        $data = $request->validate([
            'notitie' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        if (! $spreekbeurt->isBevestigd()) {
            throw ValidationException::withMessages([
                'beurt' => [__('api.predikant.beurt_not_confirmed')],
            ]);
        }

        $annuleerInstellingen = $this->instellingenService->beurtAnnuleren();
        if (! $annuleerInstellingen['enabled']) {
            throw ValidationException::withMessages([
                'beurt' => [__('api.predikant.beurt_annuleren_uitgeschakeld')],
            ]);
        }

        $spreekbeurt->loadMissing(['dienst.gemeente.contactpersoon', 'spreker']);

        $dienstDatum = $spreekbeurt->dienst?->datum;
        if ($dienstDatum !== null && $dienstDatum->isPast()) {
            throw ValidationException::withMessages([
                'beurt' => [__('api.predikant.beurt_past')],
            ]);
        }

        if ($dienstDatum !== null && ! $this->instellingenService->magBeurtAnnulerenOpDatum($dienstDatum)) {
            throw ValidationException::withMessages([
                'beurt' => [__('api.predikant.beurt_annuleren_te_laat', ['dagen' => $annuleerInstellingen['dagen_vooraf']])],
            ]);
        }

        $contactEmail = $spreekbeurt->dienst?->gemeente?->contactpersoon?->email;
        if (filled($contactEmail) && config('mail.features.beurt_annulering', true)) {
            Mail::to($contactEmail)->send(new BeurtAnnuleringMail($spreekbeurt, $data['notitie']));
        }

        $spreekbeurt->delete();

        return response()->noContent();
    }

    private function assertMagBeurtenBeheren(Request $request): void
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
