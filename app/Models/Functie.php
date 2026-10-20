<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Functie extends Model
{
    use HasFactory;

    protected $table = 'functies';

    protected $fillable = ['naam', 'slug'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_functies')->withTimestamps();
    }
}
