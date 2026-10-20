<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PublicNavigationItem;
use Illuminate\Database\Seeder;

class PublicNavigationSeeder extends Seeder
{
    public function run(): void
    {
        $configItems = config('public_navigation.items', []);

        foreach ($configItems as $item) {
            $key = (string) ($item['id'] ?? '');
            if ($key === '') {
                continue;
            }

            PublicNavigationItem::query()->updateOrCreate(
                ['key' => $key],
                [
                    'label_nl' => (string) ($item['label_nl'] ?? ''),
                    'label_en' => (string) ($item['label_en'] ?? ''),
                    'url' => (string) ($item['url'] ?? '/'),
                    'display_order' => (int) ($item['order'] ?? 0),
                    'visible' => (bool) ($item['visible'] ?? true),
                    'external' => (bool) ($item['external'] ?? false),
                ]
            );
        }
    }
}
