<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gemeente extends Model
{
    use HasFactory;

    protected $fillable = [
        'naam',
        'naam_kort',
        'adres',
        'plaats',
        'postcode',
        'district_id',
        'predikant_id',
        'contactpersoon_id',
        'begintijd_ochtend',
        'begintijd_avond',
        'kerk',
        'website_url',
        'livestream_url',
        'taal',
        'active',
        'church_plant',
        'volgorde',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'church_plant' => 'boolean',
        ];
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function predikant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'predikant_id');
    }

    public function contactpersoon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contactpersoon_id');
    }

    public function gebruikers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_gemeentes')->withTimestamps();
    }

    public function diensten(): HasMany
    {
        return $this->hasMany(Dienst::class);
    }

    public function publicaties(): HasMany
    {
        return $this->hasMany(Publicatie::class);
    }
}
