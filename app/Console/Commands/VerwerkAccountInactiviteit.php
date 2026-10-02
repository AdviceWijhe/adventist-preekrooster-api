<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\AccountInactiviteitWaarschuwingMail;
use App\Models\ChangeLog;
use App\Models\User;
use App\Services\Auth\SessionInvalidationService;
use App\Services\Instellingen\InstellingenService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class VerwerkAccountInactiviteit extends Command
{
    protected $signature = 'gebruikers:verwerk-inactiviteit';

    protected $description = 'Stuurt waarschuwingen en deactiveert accounts op basis van inactiviteit';

    public function __construct(
        private readonly InstellingenService $instellingenService,
        private readonly SessionInvalidationService $sessionInvalidation,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $settings = $this->instellingenService->inactiviteit();
        if (! $settings['enabled']) {
            $this->info('Account-inactiviteit staat uit.');

            return self::SUCCESS;
        }

        $dagen = $settings['dagen'];
        $waarschuwingDagen = $settings['waarschuwing_dagen'];
        $waarschuwingen = 0;
        $gedeactiveerd = 0;

        User::query()
            ->where('active', true)
            ->whereNotNull('email')
            ->whereDoesntHave('roles', fn ($query) => $query->whereIn('slug', ['admin', 'beheerder']))
            ->chunkById(100, function ($users) use ($dagen, $waarschuwingDagen, &$waarschuwingen, &$gedeactiveerd): void {
                foreach ($users as $user) {
                    $referentie = $user->last_login_at ?? $user->created_at;
                    if ($referentie === null) {
                        continue;
                    }

                    $dagenInactief = (int) $referentie->copy()->startOfDay()->diffInDays(now()->startOfDay());
                    $dagenTotDeactivatie = $dagen - $dagenInactief;

                    if ($dagenInactief >= $dagen) {
                        $user->update([
                            'active' => false,
                            'inactiviteit_waarschuwing_at' => null,
                        ]);
                        $this->sessionInvalidation->invalidateFor($user->fresh());
                        ChangeLog::log('Account gedeactiveerd (inactiviteit)', $user);
                        $gedeactiveerd++;

                        continue;
                    }

                    if ($dagenTotDeactivatie <= $waarschuwingDagen
                        && $dagenTotDeactivatie > 0
                        && $user->inactiviteit_waarschuwing_at === null
                        && config('mail.features.account_inactiviteit_waarschuwing', true)) {
                        Mail::to($user->email)->send(new AccountInactiviteitWaarschuwingMail($user, $dagenTotDeactivatie));
                        $user->update(['inactiviteit_waarschuwing_at' => now()]);
                        $waarschuwingen++;
                    }
                }
            });

        $this->info("{$waarschuwingen} waarschuwing(en) verstuurd, {$gedeactiveerd} account(s) gedeactiveerd.");

        return self::SUCCESS;
    }
}
