<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicStorageController extends Controller
{
    public function __invoke(string $path): StreamedResponse
    {
        if ($this->padIsOngeldig($path)) {
            abort(404);
        }

        $disk = Storage::disk('public');

        abort_unless($disk->exists($path), 404);

        return $disk->response($path);
    }

    private function padIsOngeldig(string $path): bool
    {
        if ($path === '' || str_contains($path, '..') || str_contains($path, '\\')) {
            return true;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                return true;
            }
        }

        return false;
    }
}
