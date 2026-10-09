<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dienst extends Model
{
    use HasFactory;

    protected $table = 'diensten';

    protected $fillable = [
        'datum',
        'gemeente_id',
        'type',
        'eigeninvulling',
        'bijzonderheid_id',
        'taal',
        'dienstwijze',
    ];

    protected function casts(): array
    {
        return ['datum' => 'date'];
    }

    public function gemeente(): BelongsTo
    {
        return $this->belongsTo(Gemeente::class);
    }

    public function bijzonderheid(): BelongsTo
    {
        return $this->belongsTo(Bijzonderheid::class);
    }

    public function spreekbeurten(): HasMany
    {
        return $this->hasMany(Spreekbeurt::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }
}
