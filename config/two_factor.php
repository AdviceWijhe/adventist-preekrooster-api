<?php

declare(strict_types=1);

return [

    /*
    | Zet in .env op false om e-mail-2FA over te slaan (lokale dev zonder werkende mail).
    | Nooit op productie uitzetten, tenzij je weet wat je doet.
    */
    'enabled' => filter_var(
        env('TWO_FACTOR_ENABLED', true),
        FILTER_VALIDATE_BOOL,
    ),

];
