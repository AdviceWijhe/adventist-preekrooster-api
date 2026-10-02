<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\Dienst;
use App\Models\Gemeente;
use App\Models\Spreekbeurt;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Levert per bewerkbare mailtemplatesleutel de echte Blade-views plus vaste
 * in-memory voorbeelddata voor de beheer-preview. Geen database-queries.
 */
class MailPreviewSampleData
{
    /**
     * @return array{htmlView: string, textView: string, with: array<string, mixed>}|null
     */
    public function for(string $sleutel): ?array
    {
        return match ($sleutel) {
            'welkom_gebruiker' => $this->entry(
                'mail.welkom_gebruiker_html',
                'mail.welkom_gebruiker',
                [
                    'user' => $this->voorbeeldSpreker(),
                    'setPasswordUrl' => $this->demoUrl('/wachtwoord-aanmaken?voorbeeld=1'),
                ],
            ),
            'account_geactiveerd' => $this->entry(
                'mail.account_geactiveerd_html',
                'mail.account_geactiveerd',
                ['user' => $this->voorbeeldSpreker()],
            ),
            'account_inactiviteit_waarschuwing' => $this->entry(
                'mail.account_inactiviteit_waarschuwing_html',
                'mail.account_inactiviteit_waarschuwing',
                ['user' => $this->voorbeeldSpreker()],
            ),
            'profiel_gewijzigd' => $this->entry(
                'mail.profiel_gewijzigd_html',
                'mail.profiel_gewijzigd',
                [
                    'user' => $this->voorbeeldSpreker(),
                    'velden' => ['E-mailadres', 'Telefoonnummer'],
                ],
            ),
            'predikant_uitvraag' => $this->entry(
                'mail.predikant_uitvraag_html',
                'mail.predikant_uitvraag',
                ['spreekbeurt' => $this->voorbeeldSpreekbeurt()],
            ),
            'beurt_status_melding' => $this->entry(
                'mail.beurt_status_melding_html',
                'mail.beurt_status_melding',
                ['spreekbeurt' => $this->voorbeeldSpreekbeurt(bevestigd: 1)],
            ),
            'beurt_annulering' => $this->entry(
                'mail.beurt_annulering_html',
                'mail.beurt_annulering',
                [
                    'spreekbeurt' => $this->voorbeeldSpreekbeurt(),
                    'notitie' => 'Helaas verhinderd door ziekte.',
                ],
            ),
            'spreekbeurt_auto_annulering' => $this->entry(
                'mail.spreekbeurt_auto_annulering_html',
                'mail.spreekbeurt_auto_annulering',
                ['spreekbeurt' => $this->voorbeeldSpreekbeurt()],
            ),
            'bevestigings_herinnering' => $this->entry(
                'mail.bevestigings_herinnering_html',
                'mail.bevestigings_herinnering',
                [
                    'predikant' => $this->voorbeeldSpreker(),
                    'beurten' => collect([
                        $this->voorbeeldSpreekbeurt(),
                        $this->voorbeeldSpreekbeurt(
                            datum: Carbon::parse('2026-08-15'),
                            type: 'sabbathschool',
                        ),
                    ]),
                ],
            ),
            'publicatie_bekendmaking' => $this->entry(
                'mail.publicatie_bekendmaking_html',
                'mail.publicatie_bekendmaking',
                [],
            ),
            'rooster_publicatie' => $this->entry(
                'mail.rooster_publicatie_html',
                'mail.rooster_publicatie',
                [
                    'periode' => '2026-07',
                    'gemeente' => $this->voorbeeldGemeente(),
                    'diensten' => $this->voorbeeldDiensten(),
                ],
            ),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $with
     * @return array{htmlView: string, textView: string, with: array<string, mixed>}
     */
    private function entry(string $htmlView, string $textView, array $with): array
    {
        return [
            'htmlView' => $htmlView,
            'textView' => $textView,
            'with' => $with,
        ];
    }

    private function demoUrl(string $pad): string
    {
        $base = rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/');

        return $base.$pad;
    }

    private function voorbeeldSpreker(): User
    {
        return new User([
            'voornaam' => 'Jan',
            'tussenvoegsel' => '',
            'achternaam' => 'de Vries',
            'email' => 'jan.de.vries@voorbeeld.nl',
        ]);
    }

    private function voorbeeldGemeente(): Gemeente
    {
        return new Gemeente([
            'naam' => 'Wijhe',
            'naam_kort' => 'Wijhe',
        ]);
    }

    private function voorbeeldSpreekbeurt(
        ?Carbon $datum = null,
        string $type = 'sabbath',
        ?int $bevestigd = null,
    ): Spreekbeurt {
        $gemeente = $this->voorbeeldGemeente();
        $spreker = $this->voorbeeldSpreker();

        $dienst = new Dienst([
            'datum' => $datum ?? Carbon::parse('2026-07-11'),
            'type' => $type,
            'taal' => 'nl',
        ]);
        $dienst->setRelation('gemeente', $gemeente);
        $dienst->setRelation('spreekbeurten', collect());

        $spreekbeurt = new Spreekbeurt([
            'bevestigd' => $bevestigd,
        ]);
        $spreekbeurt->setRelation('dienst', $dienst);
        $spreekbeurt->setRelation('spreker', $spreker);

        return $spreekbeurt;
    }

    /**
     * @return Collection<int, Dienst>
     */
    private function voorbeeldDiensten(): Collection
    {
        $gemeente = $this->voorbeeldGemeente();
        $spreekbeurt = $this->voorbeeldSpreekbeurt(bevestigd: 1);

        $dienst1 = new Dienst([
            'datum' => Carbon::parse('2026-07-04'),
            'type' => 'sabbath',
            'taal' => 'nl',
        ]);
        $dienst1->setRelation('gemeente', $gemeente);
        $dienst1->setRelation('spreekbeurten', collect([$spreekbeurt]));

        $dienst2 = new Dienst([
            'datum' => Carbon::parse('2026-07-11'),
            'type' => 'sabbathschool',
            'taal' => 'nl',
        ]);
        $dienst2->setRelation('gemeente', $gemeente);
        $dienst2->setRelation('spreekbeurten', collect());

        return collect([$dienst1, $dienst2]);
    }
}
