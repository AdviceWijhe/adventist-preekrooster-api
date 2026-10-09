<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Publicatie extends Model
{
    use HasFactory;

    protected $fillable = ['gemeente_id', 'periode', 'gepubliceerd', 'gepubliceerd_op', 'gepubliceerd_door'];

    protected function casts(): array
    {
        return [
            'gepubliceerd' => 'boolean',
            'gepubliceerd_op' => 'datetime',
        ];
    }

    public function gemeente(): BelongsTo
    {
        return $this->belongsTo(Gemeente::class);
    }

    public function gepubliceerdDoor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gepubliceerd_door');
    }
}
