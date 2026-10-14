<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TwoFactorCode;
use Illuminate\Console\Command;

class OpschonenTwoFactorCodes extends Command
{
    protected $signature = 'two-factor:opschonen';

    protected $description = 'Verwijder verlopen en oude gebruikte 2FA-codes';

    public function handle(): int
    {
        $verlopen = TwoFactorCode::query()
            ->where('expires_at', '<', now())
            ->delete();

        $gebruikt = TwoFactorCode::query()
            ->where('used', true)
            ->where('updated_at', '<', now()->subDay())
            ->delete();

        $this->info("Verwijderd: {$verlopen} verlopen, {$gebruikt} gebruikte codes.");

        return self::SUCCESS;
    }
}
