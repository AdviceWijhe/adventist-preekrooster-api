<?php

declare(strict_types=1);

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Models\PublicNavigationItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PublicNavigationController extends Controller
{
    public function index(): JsonResponse
    {
        $items = PublicNavigationItem::query()
            ->orderBy('display_order')
            ->orderBy('id')
            ->get()
            ->map(fn (PublicNavigationItem $item): array => $this->toPayload($item))
            ->values()
            ->all();

        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        $item = PublicNavigationItem::query()->create($validated);

        return response()->json(['data' => $this->toPayload($item)], 201);
    }

    public function update(Request $request, PublicNavigationItem $publicNavigationItem): JsonResponse
    {
        $validated = $request->validate($this->rules($publicNavigationItem->id));
        $publicNavigationItem->update($validated);

        return response()->json(['data' => $this->toPayload($publicNavigationItem->fresh())]);
    }

    public function destroy(PublicNavigationItem $publicNavigationItem): JsonResponse
    {
        $publicNavigationItem->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?int $ignoreId = null): array
    {
        return [
            'key' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('public_navigation_items', 'key')->ignore($ignoreId),
            ],
            'label_nl' => ['required', 'string', 'max:255'],
            'label_en' => ['required', 'string', 'max:255'],
            'url' => [
                'required',
                'string',
                'max:2048',
                request()->boolean('external') ? 'url:http,https' : 'regex:/^\//',
            ],
            'display_order' => ['required', 'integer', 'min:0', 'max:10000'],
            'visible' => ['required', 'boolean'],
            'external' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toPayload(PublicNavigationItem $item): array
    {
        return [
            'id' => $item->key,
            'label_nl' => $item->label_nl,
            'label_en' => $item->label_en,
            'url' => $item->url,
            'order' => $item->display_order,
            'visible' => $item->visible,
            'external' => $item->external,
        ];
    }
}
