<?php

declare(strict_types=1);

namespace App\Console;

use App\Console\Commands\StuurBevestigingsHerinneringen;
use App\Console\Commands\StuurRoosterPerMail;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        StuurBevestigingsHerinneringen::class,
        StuurRoosterPerMail::class,
    ];
}
