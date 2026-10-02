<?php

return [

    'timezone' => env('AGENDA_TIMEZONE', 'Europe/Amsterdam'),

    /** Aantal dagen terug dat bevestigde beurten nog in de feed staan. */
    'include_days_past' => (int) env('AGENDA_INCLUDE_DAYS_PAST', 30),

    /** Standaardduur per diensttype in minuten. */
    'duration_minutes' => [
        'sabbatschool' => 60,
        'eredienst' => 90,
        'speciaal' => 60,
    ],

    /** Fallback begintijd wanneer gemeente geen tijd heeft. */
    'default_start_time' => '10:00',

];
