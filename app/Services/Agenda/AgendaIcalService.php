<?php

declare(strict_types=1);

namespace App\Services\Agenda;

use App\Models\Gemeente;
use App\Models\Spreekbeurt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AgendaIcalService
{
    public const TIMEZONE = 'Europe/Amsterdam';

    public function buildForUser(User $user): string
    {
        $beurten = $this->bevestigdeBeurten($user);
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Preekrooster//Agenda//NL',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escapeText('Preekrooster — '.$user->volledigeNaam()),
            'X-WR-TIMEZONE:'.self::TIMEZONE,
        ];

        foreach ($beurten as $spreekbeurt) {
            $lines = array_merge($lines, $this->eventLines($spreekbeurt));
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    public function etagForUser(User $user): string
    {
        $beurten = $this->bevestigdeBeurten($user);
        $fingerprint = $beurten
            ->map(fn (Spreekbeurt $s) => $s->id.':'.$s->updated_at?->timestamp)
            ->implode('|');

        return '"'.sha1($fingerprint).'"';
    }

    /**
     * @return Collection<int, Spreekbeurt>
     */
    private function bevestigdeBeurten(User $user): Collection
    {
        $tz = config('agenda.timezone', self::TIMEZONE);
        $vanaf = CarbonImmutable::now($tz)->subDays((int) config('agenda.include_days_past', 30))->startOfDay();

        return Spreekbeurt::query()
            ->with(['dienst.gemeente', 'dienst.bijzonderheid'])
            ->where('spreker_id', $user->id)
            ->where('bevestigd', 1)
            ->whereHas('dienst', fn ($query) => $query->whereDate('datum', '>=', $vanaf->toDateString()))
            ->get()
            ->sortBy(fn (Spreekbeurt $spreekbeurt) => $spreekbeurt->dienst?->datum?->format('Y-m-d') ?? '')
            ->values();
    }

    /**
     * @return list<string>
     */
    private function eventLines(Spreekbeurt $spreekbeurt): array
    {
        $dienst = $spreekbeurt->dienst;
        $gemeente = $dienst?->gemeente;
        $tz = config('agenda.timezone', self::TIMEZONE);

        $start = $this->startDateTime($dienst?->datum?->format('Y-m-d'), $dienst?->type, $gemeente);
        $duration = (int) (config('agenda.duration_minutes.'.$dienst?->type) ?? 60);
        $end = $start->addMinutes($duration);

        $typeLabel = match ($dienst?->type) {
            'sabbatschool' => 'Sabbatschool',
            'eredienst' => 'Eredienst',
            'speciaal' => 'Speciaal',
            default => 'Dienst',
        };

        $summary = $typeLabel;
        if ($gemeente?->naam) {
            $summary .= ' — '.$gemeente->naam;
        }

        $locationParts = array_filter([
            $gemeente?->kerk,
            trim(implode(', ', array_filter([$gemeente?->adres, $gemeente?->postcode, $gemeente?->plaats]))),
        ]);
        $location = implode(', ', $locationParts);

        $descriptionParts = array_filter([
            $gemeente?->naam ? 'Gemeente: '.$gemeente->naam : null,
            $dienst?->bijzonderheid?->naam ? 'Bijzonderheid: '.$dienst->bijzonderheid->naam : null,
        ]);

        $uidHost = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'preekrooster.local';
        $sequence = max(0, (int) $spreekbeurt->updated_at?->timestamp - (int) $spreekbeurt->created_at?->timestamp);

        return [
            'BEGIN:VEVENT',
            'UID:spreekbeurt-'.$spreekbeurt->id.'@'.$uidHost,
            'DTSTAMP:'.$this->formatUtc(now()),
            'DTSTART;TZID='.self::TIMEZONE.':'.$start->format('Ymd\THis'),
            'DTEND;TZID='.self::TIMEZONE.':'.$end->format('Ymd\THis'),
            'SUMMARY:'.$this->escapeText($summary),
            'LOCATION:'.$this->escapeText($location),
            'DESCRIPTION:'.$this->escapeText(implode("\n", $descriptionParts)),
            'STATUS:CONFIRMED',
            'SEQUENCE:'.$sequence,
            'LAST-MODIFIED:'.$this->formatUtc($spreekbeurt->updated_at ?? now()),
            'END:VEVENT',
        ];
    }

    private function startDateTime(?string $datum, ?string $type, ?Gemeente $gemeente): CarbonImmutable
    {
        $tz = config('agenda.timezone', self::TIMEZONE);
        $date = $datum ?? now($tz)->format('Y-m-d');

        $time = match ($type) {
            'sabbatschool' => $gemeente?->begintijd_avond,
            'eredienst' => $gemeente?->begintijd_ochtend,
            default => $gemeente?->begintijd_ochtend,
        };

        $time = $this->normaliseTime($time) ?? config('agenda.default_start_time', '10:00');

        return CarbonImmutable::parse($date.' '.$time, $tz);
    }

    private function normaliseTime(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $trimmed = trim($value);
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $trimmed) === 1) {
            return substr($trimmed, 0, 5);
        }

        return $trimmed;
    }

    private function formatUtc(\DateTimeInterface $moment): string
    {
        return CarbonImmutable::instance($moment)->utc()->format('Ymd\THis\Z');
    }

    private function escapeText(string $value): string
    {
        $escaped = str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\;', '\,', '\n', '\n', '\n'], $value);

        return $escaped;
    }
}
