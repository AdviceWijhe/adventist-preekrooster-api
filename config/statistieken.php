<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Kilometer-statistieken
    |--------------------------------------------------------------------------
    |
    | Tab "Reizen" en km-rapportages zijn standaard uit tot de klant bevestigt
    | dat het kilometers-veld actief gebruikt wordt.
    |
    */
    'km_enabled' => (bool) env('STATISTIEKEN_KM_ENABLED', false),
];
