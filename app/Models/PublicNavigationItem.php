<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PublicNavigationItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'label_nl',
        'label_en',
        'url',
        'display_order',
        'visible',
        'external',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'visible' => 'boolean',
            'external' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'key';
    }
}
