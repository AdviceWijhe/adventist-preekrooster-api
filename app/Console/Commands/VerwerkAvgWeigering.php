<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ChangeLog;
use App\Models\User;
use App\Services\Auth\SessionInvalidationService;
use App\Services\Instellingen\InstellingenService;
use Illuminate\Console\Command;

class VerwerkAvgWeigering extends Command
{
    protected $signature = 'gebruikers:verwerk-avg-weigering';

    protected $description = 'Deactiveert accounts die AVG-toestemming hebben geweigerd en de termijn hebben overschreden';

    public function __construct(
        private readonly InstellingenService $instellingenService,
        private readonly SessionInvalidationService $sessionInvalidation,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $settings = $this->instellingenService->avg();
        if (! $settings['enabled']) {
            $this->info('AVG-toestemming staat uit.');

            return self::SUCCESS;
        }

        $gedeactiveerd = 0;
        $dagen = $settings['dagen'];

        User::query()
            ->where('active', true)
            ->whereNotNull('avg_refused_at')
            ->whereNull('avg_consent_at')
            ->whereDoesntHave('roles', fn ($query) => $query->whereIn('slug', ['admin', 'beheerder']))
            ->where('avg_refused_at', '<=', now()->subDays($dagen)->endOfDay())
            ->chunkById(100, function ($users) use (&$gedeactiveerd): void {
                foreach ($users as $user) {
                    $user->update([
                        'active' => false,
                    ]);
                    $this->sessionInvalidation->invalidateFor($user->fresh() ?? $user);
                    ChangeLog::log('Account gedeactiveerd (AVG-weigering)', $user);
                    $gedeactiveerd++;
                }
            });

        $this->info("{$gedeactiveerd} account(s) gedeactiveerd wegens AVG-weigering.");

        return self::SUCCESS;
    }
}
