<?php

declare(strict_types=1);

namespace App\Http\Controllers\Predikant;

use App\Http\Controllers\Controller;
use App\Mail\ProfielGewijzigdMail;
use App\Models\ChangeLog;
use App\Models\Taal;
use App\Models\User;
use App\Services\Auth\SessionInvalidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class ProfielController extends Controller
{
    private const RELATIES = ['roles', 'gemeente', 'talen'];

    public function __construct(
        private readonly SessionInvalidationService $sessionInvalidation,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->load(self::RELATIES)->makeHidden('password'),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $oudeEmail = (string) $user->email;

        $data = $request->validate([
            'voornaam' => ['sometimes', 'string', 'max:50'],
            'tussenvoegsel' => ['nullable', 'string', 'max:20'],
            'achternaam' => ['sometimes', 'string', 'max:50'],
            'email' => ['sometimes', 'email', 'unique:users,email,'.$user->id],
            'telefoonnummer' => ['nullable', 'string', 'max:30'],
            'mobiel' => ['nullable', 'string', 'max:30'],
            'taal' => ['sometimes', 'in:nl,en'],
            'talen' => ['sometimes', 'array'],
            'talen.*' => ['string', 'max:50'],
            'huidig_wachtwoord' => ['required_with:nieuw_wachtwoord', 'current_password'],
            'nieuw_wachtwoord' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        $wachtwoordGewijzigd = ! empty($data['nieuw_wachtwoord']);
        if ($wachtwoordGewijzigd) {
            $user->password = $data['nieuw_wachtwoord'];
        }
        unset($data['nieuw_wachtwoord'], $data['huidig_wachtwoord'], $data['talen']);

        $user->fill($data);
        $user->save();

        if ($wachtwoordGewijzigd) {
            $this->sessionInvalidation->invalidateFor($user->fresh());
        }

        if ($request->has('talen')) {
            $this->syncTalen($user, (array) $request->input('talen', []));
        }

        ChangeLog::log('Profiel bijgewerkt', $user);

        $gewijzigdeVelden = array_keys($user->getChanges());
        if (config('mail.features.profiel_gewijzigd', true) && $gewijzigdeVelden !== [] && filled($user->email)) {
            if ($oudeEmail !== '' && $oudeEmail !== $user->email) {
                Mail::to($oudeEmail)->send(new ProfielGewijzigdMail($user, $gewijzigdeVelden));
            }
            Mail::to($user->email)->send(new ProfielGewijzigdMail($user, $gewijzigdeVelden));
        }

        return response()->json([
            'data' => $user->fresh()->load(self::RELATIES)->makeHidden('password'),
        ]);
    }

    public function uploadFoto(Request $request): JsonResponse
    {
        $request->validate([
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        $user = $request->user();

        if ($user->photo && Storage::disk('public')->exists($user->photo)) {
            Storage::disk('public')->delete($user->photo);
        }

        $pad = $request->file('foto')->store('profielfotos', 'public');
        $user->forceFill(['photo' => $pad])->save();

        return response()->json([
            'data' => $user->fresh()->load(self::RELATIES)->makeHidden('password'),
        ]);
    }

    public function verwijderFoto(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->photo && Storage::disk('public')->exists($user->photo)) {
            Storage::disk('public')->delete($user->photo);
        }

        $user->forceFill(['photo' => null])->save();

        return response()->json([
            'data' => $user->fresh()->load(self::RELATIES)->makeHidden('password'),
        ]);
    }

    /**
     * Koppelt de gebruiker aan de opgegeven talen. Onbekende talen worden
     * toegevoegd aan de gedeelde talenlijst, zodat iedereen ze daarna kan kiezen.
     *
     * @param  array<int, string>  $namen
     */
    private function syncTalen(User $user, array $namen): void
    {
        $ids = [];

        foreach ($namen as $naam) {
            $naam = trim((string) $naam);
            if ($naam === '') {
                continue;
            }

            $taal = Taal::query()->firstOrCreate(
                ['slug' => Str::slug($naam)],
                ['naam' => $naam],
            );
            $ids[] = $taal->id;
        }

        $user->talen()->sync(array_values(array_unique($ids)));
    }
}
