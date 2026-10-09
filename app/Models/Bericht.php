<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Bericht extends Model
{
    use HasFactory;

    protected $table = 'berichten';

    public const DOELGROEP_ALLE = 'alle';

    public const DOELGROEP_PREDIKANTEN = 'predikanten';

    public const DOELGROEP_BEHEERDERS = 'beheerders';

    public const DOELGROEP_SPECIFIEK = 'specifiek';

    public const KANAAL_INTERN = 'intern';

    public const KANAAL_EMAIL = 'email';

    public const KANAAL_BEIDE = 'beide';

    protected $fillable = [
        'titel',
        'inhoud',
        'auteur_id',
        'doelgroep',
        'gemeente_ids',
        'gebruiker_ids',
        'kanaal',
        'gepubliceerd_op',
    ];

    protected function casts(): array
    {
        return [
            'gemeente_ids' => 'array',
            'gebruiker_ids' => 'array',
            'gepubliceerd_op' => 'datetime',
        ];
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    public function gelezenDoor(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'bericht_gelezen')
            ->withPivot('gelezen_op');
    }

    public function verwijderdDoor(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'bericht_verwijderd')
            ->withPivot('verwijderd_op');
    }

    public function isGepubliceerd(): bool
    {
        return $this->gepubliceerd_op !== null && $this->gepubliceerd_op->isPast();
    }
}
