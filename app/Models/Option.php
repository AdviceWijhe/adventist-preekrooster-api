<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Option extends Model
{
    use HasFactory;

    protected $fillable = ['naam', 'waarde'];

    public static function getValue(string $naam): ?string
    {
        return static::query()->where('naam', $naam)->value('waarde');
    }

    public static function setValue(string $naam, string $waarde): void
    {
        static::query()->updateOrCreate(['naam' => $naam], ['waarde' => $waarde]);
    }
}
