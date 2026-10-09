<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Automatische publicatie
    |--------------------------------------------------------------------------
    |
    | Publiceert de huidige maand, en vanaf de 10e van de maand ook de volgende.
    | Handmatig publiceren blijft mogelijk via beheer.
    |
    */

    'automatische_publicatie' => [
        'enabled' => filter_var(env('ROOSTER_AUTO_PUBLICATIE_ENABLED', true), FILTER_VALIDATE_BOOL),
        'stuur_bekendmaking' => filter_var(env('ROOSTER_AUTO_PUBLICATIE_MAIL', false), FILTER_VALIDATE_BOOL),
    ],

    /*
    |--------------------------------------------------------------------------
    | Automatische annulering openstaande spreekbeurten
    |--------------------------------------------------------------------------
    |
    | Verwijdert spreekbeurten die binnen X dagen plaatsvinden en nog niet
    | bevestigd zijn. Standaard uit — expliciet aanzetten op productie.
    |
    */

    'auto_annuleer' => [
        'enabled' => filter_var(env('ROOSTER_AUTO_ANNULEER_ENABLED', false), FILTER_VALIDATE_BOOL),
        'dagen_vooraf' => (int) env('ROOSTER_AUTO_ANNULEER_DAGEN', 7),
    ],

];
