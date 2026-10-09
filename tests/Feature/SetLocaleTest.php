<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_returns_dutch_auth_message_by_default(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'onbekend@example.com',
            'password' => 'wrong-password',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ongeldige inloggegevens.');
    }

    public function test_api_returns_english_auth_message_with_x_locale_header(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'onbekend@example.com',
            'password' => 'wrong-password',
        ], [
            'X-Locale' => 'en',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'Invalid login credentials.');
    }
}
