<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeLog extends Model
{
    protected $table = 'change_log';

    protected $fillable = ['user_id', 'actie', 'model', 'model_id', 'oud', 'nieuw'];

    protected function casts(): array
    {
        return [
            'oud' => 'array',
            'nieuw' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function log(string $actie, ?Model $model = null, ?array $oud = null, ?array $nieuw = null): void
    {
        static::create([
            'user_id' => auth()->id(),
            'actie' => $actie,
            'model' => $model ? class_basename($model) : null,
            'model_id' => $model?->id,
            'oud' => $oud,
            'nieuw' => $nieuw,
        ]);
    }
}
