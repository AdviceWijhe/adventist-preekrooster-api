<?php

declare(strict_types=1);

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Mail\AccountGeactiveerdMail;
use App\Models\ChangeLog;
use App\Models\User;
use App\Services\GebruikerRolAutorisatieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class InactieveAccountsController extends Controller
{
    public function __construct(
        private readonly GebruikerRolAutorisatieService $rolAutorisatie,
    ) {}

    public function index(): JsonResponse
    {
        $inactief = User::query()
            ->where('active', false)
            ->with('roles')
            ->get()
            ->makeHidden('password');

        return response()->json(['data' => $inactief]);
    }

    public function activeer(Request $request, User $gebruiker): JsonResponse
    {
        $this->rolAutorisatie->assertKanGebruikerBeheren($request->user(), $gebruiker);

        $gebruiker->update([
            'active' => true,
            'inactiviteit_waarschuwing_at' => null,
        ]);
        ChangeLog::log('Account geactiveerd', $gebruiker);
        if (config('mail.features.account_reactivated', true) && filled($gebruiker->email)) {
            Mail::to($gebruiker->email)->send(new AccountGeactiveerdMail($gebruiker));
        }

        return response()->json(['message' => __('api.beheer.account_activated')]);
    }
}
