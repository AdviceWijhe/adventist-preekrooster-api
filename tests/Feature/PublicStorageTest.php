<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicStorageTest extends TestCase
{
    public function test_public_disk_bestand_is_bereikbaar_via_storage_url(): void
    {
        Storage::fake('public');
        $bestand = UploadedFile::fake()->image('pasfoto.jpg', 20, 20);
        Storage::disk('public')->put(
            'profielfotos/pasfoto.jpg',
            (string) file_get_contents($bestand->getPathname()),
        );

        $response = $this->get('/storage/profielfotos/pasfoto.jpg');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_ontbrekend_bestand_geeft_404(): void
    {
        Storage::fake('public');

        $this->get('/storage/profielfotos/bestaat-niet.jpg')->assertNotFound();
    }

    public function test_pad_traversal_wordt_geweigerd(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('geheim.txt', 'geheim');

        $this->get('/storage/'.rawurlencode('../geheim.txt'))->assertNotFound();
        $this->get('/storage/profielfotos/../../geheim.txt')->assertNotFound();
    }
}
