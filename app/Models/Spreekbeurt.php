<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Spreekbeurt extends Model
{
    use HasFactory;

    protected $table = 'spreekbeurten';

    protected $fillable = ['dienst_id', 'spreker_id', 'bevestigd', 'bericht', 'kilometers', 'ingevoerd_door'];

    public function dienst(): BelongsTo
    {
        return $this->belongsTo(Dienst::class);
    }

    public function spreker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'spreker_id');
    }

    public function ingevoerdDoor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ingevoerd_door');
    }

    public function isBevestigd(): bool
    {
        return (int) $this->bevestigd === 1;
    }

    public function isAfgewezen(): bool
    {
        return (int) $this->bevestigd === 0;
    }

    public function isUitgevraagd(): bool
    {
        return $this->bevestigd === null;
    }
}
