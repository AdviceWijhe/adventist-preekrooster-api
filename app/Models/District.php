<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class District extends Model
{
    use HasFactory;

    protected $fillable = ['naam', 'visible'];

    protected function casts(): array
    {
        return [
            'visible' => 'boolean',
        ];
    }

    public function gemeentes(): HasMany
    {
        return $this->hasMany(Gemeente::class);
    }
}
