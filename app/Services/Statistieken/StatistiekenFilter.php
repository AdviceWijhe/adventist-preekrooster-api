<?php

declare(strict_types=1);

namespace App\Services\Statistieken;

use Illuminate\Database\Eloquent\Builder;

readonly class StatistiekenFilter
{
    public function __construct(
        public int $jaar,
        public ?int $gemeenteId = null,
        public ?int $districtId = null,
        public ?int $sprekerId = null,
        public ?string $van = null,
        public ?string $tot = null,
        public ?string $type = null,
        public ?string $taal = null,
        public ?string $dienstwijze = null,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromArray(array $validated): self
    {
        return new self(
            jaar: (int) $validated['jaar'],
            gemeenteId: isset($validated['gemeente_id']) ? (int) $validated['gemeente_id'] : null,
            districtId: isset($validated['district_id']) ? (int) $validated['district_id'] : null,
            sprekerId: isset($validated['spreker_id']) ? (int) $validated['spreker_id'] : null,
            van: $validated['van'] ?? null,
            tot: $validated['tot'] ?? null,
            type: $validated['type'] ?? null,
            taal: $validated['taal'] ?? null,
            dienstwijze: $validated['dienstwijze'] ?? null,
        );
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'jaar' => $this->jaar,
            'gemeente_id' => $this->gemeenteId,
            'district_id' => $this->districtId,
            'spreker_id' => $this->sprekerId,
            'van' => $this->van,
            'tot' => $this->tot,
            'type' => $this->type,
            'taal' => $this->taal,
            'dienstwijze' => $this->dienstwijze,
        ];
    }

    public function applyToDienst(Builder $query): void
    {
        $query->whereYear('datum', $this->jaar);

        if ($this->gemeenteId !== null) {
            $query->where('gemeente_id', $this->gemeenteId);
        }

        if ($this->districtId !== null) {
            $query->whereHas('gemeente', fn (Builder $q) => $q->where('district_id', $this->districtId));
        }

        if ($this->van !== null) {
            $query->whereDate('datum', '>=', $this->van);
        }

        if ($this->tot !== null) {
            $query->whereDate('datum', '<=', $this->tot);
        }

        if ($this->type !== null) {
            $query->where('type', $this->type);
        }

        if ($this->taal !== null) {
            $query->where('taal', $this->taal);
        }

        if ($this->dienstwijze !== null) {
            $query->where('dienstwijze', $this->dienstwijze);
        }
    }

    public function applyToSpreekbeurt(Builder $query): void
    {
        if ($this->sprekerId !== null) {
            $query->where('spreekbeurten.spreker_id', $this->sprekerId);
        }

        $query->whereHas('dienst', fn (Builder $q) => $this->applyToDienst($q));
    }
}
