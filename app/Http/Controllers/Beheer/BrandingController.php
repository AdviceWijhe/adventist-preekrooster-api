<?php

declare(strict_types=1);

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Services\Branding\BrandingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandingController extends Controller
{
    public function __construct(
        private readonly BrandingService $brandingService
    ) {}

    public function show(): JsonResponse
    {
        return response()->json([
            'data' => $this->brandingService->all(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branding_app_name' => ['nullable', 'string', 'max:120'],
            'branding_logo_url' => ['nullable', 'url', 'max:255'],
            'branding_primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'branding_secondary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'branding_accent_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'branding_from_name' => ['nullable', 'string', 'max:120'],
            'branding_footer_text' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json([
            'data' => $this->brandingService->update($data),
        ]);
    }
}
