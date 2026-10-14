<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class UserBeschikbaarheid extends Model
{
    use HasFactory;

    protected $table = 'user_beschikbaarheid';

    protected $fillable = ['user_id', 'datum_van', 'datum_tot', 'opmerking'];

    protected function casts(): array
    {
        return [
            'datum_van' => 'date',
            'datum_tot' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dektDatum(string $datum): bool
    {
        $d = Carbon::parse($datum)->startOfDay();

        return $this->datum_van->lte($d) && $this->datum_tot->gte($d);
    }
}
