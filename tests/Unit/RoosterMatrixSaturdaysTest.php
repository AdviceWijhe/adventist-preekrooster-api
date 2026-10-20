<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RoosterMatrixService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class RoosterMatrixSaturdaysTest extends TestCase
{
    public function test_april_2026_heeft_vier_zaterdagen(): void
    {
        $dates = RoosterMatrixService::saturdaysInMonth('2026-04', 'Europe/Amsterdam');
        $this->assertSame(
            ['2026-04-04', '2026-04-11', '2026-04-18', '2026-04-25'],
            $dates
        );
    }

    public function test_standaard_weergave_maand_blijft_huidige_maand_voor_laatste_zaterdag(): void
    {
        $nu = Carbon::parse('2026-07-25', 'Europe/Amsterdam');
        $this->assertSame('2026-07', RoosterMatrixService::standaardWeergaveMaand($nu));
    }

    public function test_standaard_weergave_maand_schakelt_na_laatste_zaterdag(): void
    {
        $nu = Carbon::parse('2026-07-27', 'Europe/Amsterdam');
        $this->assertSame('2026-08', RoosterMatrixService::standaardWeergaveMaand($nu));
    }
}
