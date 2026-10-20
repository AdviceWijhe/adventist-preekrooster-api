<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Bijzonderheid;
use App\Models\Dienst;
use Illuminate\Database\Eloquent\Builder;

final class BijzonderheidCatalog
{
    /** @var list<string> */
    public const NAMEN = [
        'Opdragingsdienst',
        'Intrededienst',
        'Doopdienst',
        'Heilig Avondmaalsdienst',
        'Jeugddienst',
        'Middagdienst',
        'Studiemiddag',
    ];

    /** @var list<string> */
    public const UITGESLOTEN = [
        'Buitendienst',
        'Dankdienst',
        'Muziekdienst',
        'Kerstdienst',
        'Nieuwjaarsdienst',
        'Paasdienst',
    ];

    public static function ensureSeeded(): void
    {
        foreach (self::NAMEN as $naam) {
            Bijzonderheid::query()->firstOrCreate(['naam' => $naam]);
        }

        foreach (self::UITGESLOTEN as $naam) {
            $bijzonderheid = Bijzonderheid::query()->where('naam', $naam)->first();
            if ($bijzonderheid === null) {
                continue;
            }

            $inGebruik = Dienst::query()->where('bijzonderheid_id', $bijzonderheid->id)->exists();
            if (! $inGebruik) {
                $bijzonderheid->delete();
            }
        }
    }

    /** @return Builder<Bijzonderheid> */
    public static function selecteerbaarQuery(): Builder
    {
        return Bijzonderheid::query()->whereIn('naam', self::NAMEN);
    }
}
