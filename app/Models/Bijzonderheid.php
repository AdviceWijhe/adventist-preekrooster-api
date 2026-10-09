<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bijzonderheid extends Model
{
    use HasFactory;

    protected $table = 'bijzonderheden';

    protected $fillable = ['naam', 'omschrijving'];

    public function diensten(): HasMany
    {
        return $this->hasMany(Dienst::class);
    }
}
