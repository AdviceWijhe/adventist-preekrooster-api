<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends Model
{
    use HasFactory;

    protected $fillable = ['dienst_id', 'user_id', 'tekst'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dienst(): BelongsTo
    {
        return $this->belongsTo(Dienst::class);
    }
}
