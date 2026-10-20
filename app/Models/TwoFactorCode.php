<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TwoFactorCode extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'code', 'used', 'expires_at'];

    protected function casts(): array
    {
        return [
            'used' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function isValid(): bool
    {
        return ! $this->used && $this->expires_at->isFuture();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
