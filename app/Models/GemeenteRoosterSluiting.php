<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GemeenteRoosterSluiting extends Model
{
    protected $table = 'gemeente_rooster_sluitingen';

    protected $fillable = [
        'gemeente_id',
        'datum',
    ];

    protected function casts(): array
    {
        return [
            'datum' => 'date',
        ];
    }

    public function gemeente(): BelongsTo
    {
        return $this->belongsTo(Gemeente::class);
    }
}
