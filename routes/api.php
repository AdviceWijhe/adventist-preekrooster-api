<?php

use App\Http\Controllers\Auth\AvgConsentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Beheer\AvgStatusController;
use App\Http\Controllers\Beheer\BerichtController;
use App\Http\Controllers\Beheer\BijzonderheidController;
use App\Http\Controllers\Beheer\BrandingController;
use App\Http\Controllers\Beheer\ChangeLogController;
use App\Http\Controllers\Beheer\DashboardController;
use App\Http\Controllers\Beheer\DienstController;
use App\Http\Controllers\Beheer\DistrictController;
use App\Http\Controllers\Beheer\GebruikerController;
use App\Http\Controllers\Beheer\GemeenteController;
use App\Http\Controllers\Beheer\InactieveAccountsController;
use App\Http\Controllers\Beheer\InstellingenController;
use App\Http\Controllers\Beheer\MailController;
use App\Http\Controllers\Beheer\MailTemplateController;
use App\Http\Controllers\Beheer\PublicatieController;
use App\Http\Controllers\Beheer\PublicNavigationController;
use App\Http\Controllers\Beheer\RoosterVergrendelController;
use App\Http\Controllers\Beheer\SpreekbeurtController;
use App\Http\Controllers\Beheer\StatistiekenController;
use App\Http\Controllers\BerichtInboxController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\Predikant\AgendaController;
use App\Http\Controllers\Predikant\BeschikbaarheidController;
use App\Http\Controllers\Predikant\BeurtController;
use App\Http\Controllers\Predikant\ContactpersoonController;
use App\Http\Controllers\Predikant\InschrijfController;
use App\Http\Controllers\Predikant\InstellingenController as PredikantInstellingenController;
use App\Http\Controllers\Predikant\ProfielController;
use App\Http\Controllers\Predikant\RoosterController as PredikantRoosterController;
use App\Http\Controllers\PubliekController;
use App\Http\Controllers\PubliekInschrijfController;
use App\Http\Controllers\TaalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'throttle:5,1'])->group(function (): void {
    Route::post('/auth/login', [LoginController::class, 'login']);
    Route::post('/auth/wachtwoord-vergeten', [PasswordResetController::class, 'vergeten']);
    Route::post('/auth/wachtwoord-reset', [PasswordResetController::class, 'reset']);
    Route::post('/auth/wachtwoord-aanmaken', [PasswordResetController::class, 'aanmaken']);
});

Route::get('/publiek/rooster/standaard-maand', [PubliekController::class, 'standaardMaand']);
Route::get('/publiek/rooster/matrix', [PubliekController::class, 'matrix']);
Route::get('/publiek/rooster', [PubliekController::class, 'rooster']);
Route::get('/publiek/navigatie', [PubliekController::class, 'navigatie']);
Route::get('/publiek/branding', [PubliekController::class, 'branding']);
Route::get('/agenda/{token}.ics', [AgendaController::class, 'feed'])
    ->middleware('throttle:60,1')
    ->where('token', '[a-f0-9]{64}');

Route::middleware(['web', 'auth:sanctum', 'auth.session'])->group(function (): void {
    Route::post('/auth/logout', [LoginController::class, 'logout']);
});

Route::middleware(['web', 'auth:sanctum', 'auth.session', 'active'])->group(function (): void {
    Route::post('/auth/two-factor/challenge', [TwoFactorController::class, 'challenge'])
        ->middleware('throttle:two-factor');
    Route::post('/auth/two-factor/resend', [TwoFactorController::class, 'resend'])
        ->middleware('throttle:two-factor-resend');
    Route::get('/auth/me', [LoginController::class, 'me']);
});

Route::middleware(['web', 'auth:sanctum', 'auth.session', 'active', 'two_factor'])->group(function (): void {
    Route::post('/auth/avg-consent', [AvgConsentController::class, 'store']);
    Route::post('/auth/avg-consent/defer', [AvgConsentController::class, 'defer']);
    Route::post('/publiek/rooster/diensten/{dienst}/inschrijf', [PubliekInschrijfController::class, 'store']);

    Route::get('/berichten/inbox', [BerichtInboxController::class, 'index']);
    Route::get('/berichten/prullenbak', [BerichtInboxController::class, 'prullenbak']);
    Route::get('/berichten/unread-count', [BerichtInboxController::class, 'unreadCount']);
    Route::post('/berichten/{bericht}/gelezen', [BerichtInboxController::class, 'markeerGelezen']);
    Route::delete('/berichten/{bericht}/gelezen', [BerichtInboxController::class, 'markeerOngelezen']);
    Route::delete('/berichten/{bericht}', [BerichtInboxController::class, 'verplaatsNaarPrullenbak']);
    Route::post('/berichten/{bericht}/herstel', [BerichtInboxController::class, 'herstel']);
});

Route::middleware(['web', 'auth:sanctum', 'active', 'two_factor', 'rooster_scope'])
    ->prefix('beheer')
    ->group(function (): void {
        Route::get('gemeentes', [GemeenteController::class, 'index']);
        Route::get('gemeentes/{gemeente}', [GemeenteController::class, 'show']);
        Route::put('gemeentes/{gemeente}', [GemeenteController::class, 'update']);
        Route::get('roosters/standaard-maand', [DienstController::class, 'standaardMaand']);
        Route::get('roosters/matrix', [DienstController::class, 'matrix']);
        Route::get('roosters/dag-status', [DienstController::class, 'dagStatus']);
        Route::post('roosters/dag-sluiten', [DienstController::class, 'sluitDag']);
        Route::post('roosters/dag-openen', [DienstController::class, 'openDag']);
        Route::get('roosters', [DienstController::class, 'index']);
        Route::post('roosters', [DienstController::class, 'store']);
        Route::put('roosters/{dienst}', [DienstController::class, 'update']);
        Route::delete('roosters/{dienst}', [DienstController::class, 'destroy']);
        Route::get('roosters/beschikbare-sprekers', [DienstController::class, 'beschikbareSprekers']);
        Route::get('bijzonderheden', [BijzonderheidController::class, 'index']);
        Route::post('roosters/{dienst}/sprekers', [SpreekbeurtController::class, 'store']);
        Route::delete('roosters/{dienst}/sprekers/{spreekbeurt}', [SpreekbeurtController::class, 'destroy']);
        Route::get('rooster/vergrendel-status', [RoosterVergrendelController::class, 'status']);
        Route::get('roosters/{dienst}/notities', [NoteController::class, 'index']);
        Route::post('roosters/{dienst}/notities', [NoteController::class, 'store']);
        Route::delete('roosters/{dienst}/notities/{note}', [NoteController::class, 'destroy']);
    });

Route::middleware(['web', 'auth:sanctum', 'auth.session', 'active', 'two_factor', 'role:admin,beheerder'])
    ->prefix('beheer')
    ->group(function (): void {
        Route::apiResource('districts', DistrictController::class)->except(['show']);
        Route::post('gemeentes', [GemeenteController::class, 'store']);
        Route::delete('gemeentes/{gemeente}', [GemeenteController::class, 'destroy']);
        Route::get('gebruikers/contactpersonen', [GebruikerController::class, 'contactpersonenMetConsent']);
        Route::apiResource('gebruikers', GebruikerController::class);
        Route::post('gebruikers/{gebruiker}/uitnodiging', [GebruikerController::class, 'stuurUitnodiging']);
        Route::get('gebruikers/{gebruiker}/planning', [GebruikerController::class, 'planning']);
        Route::get('publieke-navigatie', [PublicNavigationController::class, 'index']);
        Route::post('publieke-navigatie', [PublicNavigationController::class, 'store']);
        Route::put('publieke-navigatie/{publicNavigationItem}', [PublicNavigationController::class, 'update']);
        Route::delete('publieke-navigatie/{publicNavigationItem}', [PublicNavigationController::class, 'destroy']);
        Route::get('statistieken/jaren', [StatistiekenController::class, 'jaren'])->middleware('statistiek_toegang');
        Route::get('statistieken', [StatistiekenController::class, 'index'])->middleware('statistiek_toegang');
        Route::get('statistieken/export', [StatistiekenController::class, 'export'])->middleware('statistiek_toegang');
        Route::get('dashboard/stats', [DashboardController::class, 'stats']);
        Route::get('changelog', [ChangeLogController::class, 'index']);
        Route::get('changelog/maanden', [ChangeLogController::class, 'maanden']);
        Route::get('changelog/export', [ChangeLogController::class, 'export'])->middleware('admin');
        Route::delete('changelog', [ChangeLogController::class, 'legen'])->middleware('admin');
        Route::get('inactieve-accounts', [InactieveAccountsController::class, 'index']);
        Route::post('inactieve-accounts/{gebruiker}/activeer', [InactieveAccountsController::class, 'activeer']);
        Route::post('publicatie', [PublicatieController::class, 'publiceer']);
        Route::delete('publicatie/{periode}', [PublicatieController::class, 'depublicer']);
        Route::get('publicatie', [PublicatieController::class, 'overzicht']);
        Route::post('mail/rooster', [MailController::class, 'verstuurRooster']);
        Route::get('mail/diagnostics', [MailController::class, 'diagnostics'])->middleware('admin');
        Route::post('mail/test', [MailController::class, 'sendTest'])->middleware(['admin', 'throttle:mail-test']);
        Route::get('mail/templates', [MailTemplateController::class, 'index']);
        Route::get('mail/templates/{sleutel}', [MailTemplateController::class, 'show']);
        Route::put('mail/templates/{sleutel}', [MailTemplateController::class, 'update'])->middleware('admin');
        Route::post('mail/templates/{sleutel}/reset', [MailTemplateController::class, 'reset'])->middleware('admin');
        Route::post('mail/preview', [MailTemplateController::class, 'preview'])->middleware('admin');
        Route::get('mail/signature', [MailTemplateController::class, 'signature']);
        Route::put('mail/signature', [MailTemplateController::class, 'updateSignature'])->middleware('admin');
        Route::post('mail/signature/reset', [MailTemplateController::class, 'resetSignature'])->middleware('admin');
        Route::get('branding', [BrandingController::class, 'show'])->middleware('admin');
        Route::put('branding', [BrandingController::class, 'update'])->middleware('admin');
        Route::get('instellingen', [InstellingenController::class, 'show']);
        Route::put('instellingen', [InstellingenController::class, 'update']);
        Route::get('avg/status', [AvgStatusController::class, 'index'])->middleware('admin');
        Route::post('avg/gebruikers/{gebruiker}/reset', [AvgStatusController::class, 'reset'])->middleware('admin');

        Route::post('rooster/vergrendel', [RoosterVergrendelController::class, 'vergrendel']);
        Route::post('rooster/ontgrendel', [RoosterVergrendelController::class, 'ontgrendel']);

        Route::get('berichten', [BerichtController::class, 'index']);
        Route::post('berichten', [BerichtController::class, 'store']);
        Route::put('berichten/{bericht}', [BerichtController::class, 'update']);
        Route::delete('berichten/{bericht}', [BerichtController::class, 'destroy']);
        Route::post('berichten/{bericht}/publiceren', [BerichtController::class, 'publiceren']);
    });

// Eigen omgeving: bereikbaar voor iedere ingelogde gebruiker met een account.
// Alle endpoints zijn gescoped op de huidige gebruiker; toegang per kerkelijke
// functie wordt binnen de controllers/relaties afgehandeld.
Route::middleware(['web', 'auth:sanctum', 'auth.session', 'active', 'two_factor'])
    ->prefix('predikant')
    ->group(function (): void {
        Route::get('beurten', [BeurtController::class, 'index']);
        Route::get('beurten/{spreekbeurt}', [BeurtController::class, 'show']);
        Route::post('beurten/{spreekbeurt}/bevestig', [BeurtController::class, 'bevestig']);
        Route::post('beurten/{spreekbeurt}/annuleer', [BeurtController::class, 'annuleer']);

        Route::get('instellingen/beurt-annuleren', [PredikantInstellingenController::class, 'beurtAnnuleren']);

        Route::get('beschikbaarheid', [BeschikbaarheidController::class, 'index']);
        Route::post('beschikbaarheid', [BeschikbaarheidController::class, 'store']);
        Route::put('beschikbaarheid/{beschikbaarheid}', [BeschikbaarheidController::class, 'update']);
        Route::delete('beschikbaarheid/{beschikbaarheid}', [BeschikbaarheidController::class, 'destroy']);

        Route::get('contactpersonen', [ContactpersoonController::class, 'index']);

        Route::get('profiel', [ProfielController::class, 'show']);
        Route::put('profiel', [ProfielController::class, 'update']);
        Route::post('profiel/foto', [ProfielController::class, 'uploadFoto']);
        Route::delete('profiel/foto', [ProfielController::class, 'verwijderFoto']);

        Route::get('talen', [TaalController::class, 'index']);

        Route::get('rooster/standaard-maand', [PredikantRoosterController::class, 'standaardMaand']);
        Route::get('rooster/matrix', [PredikantRoosterController::class, 'matrix']);
        Route::post('rooster/diensten/{dienst}/inschrijf', [InschrijfController::class, 'store']);

        Route::get('agenda', [AgendaController::class, 'show']);
        Route::post('agenda/regenerate', [AgendaController::class, 'regenerate']);
    });
