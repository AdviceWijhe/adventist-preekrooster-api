<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\TestMailDiagnostics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailInterceptorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        config(['mail.default' => 'array']);
    }

    public function test_interceptor_leidt_mail_om_naar_veilig_adres(): void
    {
        config([
            'mail.intercept.enabled' => true,
            'mail.intercept.redirect_to' => 'catch-all@test.local',
            'mail.intercept.subject_prefix' => '[TEST]',
        ]);

        Mail::to('predikant@example.com')->send(new TestMailDiagnostics('staging'));

        $messages = app('mailer')->getSymfonyTransport()->messages();

        $this->assertCount(1, $messages);

        $sent = $messages[0]->getOriginalMessage();
        $ontvangers = array_map(fn ($a) => $a->getAddress(), $sent->getTo());

        $this->assertSame(['catch-all@test.local'], $ontvangers);
        $this->assertStringContainsString('[TEST]', (string) $sent->getSubject());
        $this->assertStringContainsString('predikant@example.com', (string) $sent->getSubject());
    }

    public function test_interceptor_doet_niets_wanneer_uitgeschakeld(): void
    {
        config([
            'mail.intercept.enabled' => false,
            'mail.intercept.redirect_to' => 'catch-all@test.local',
        ]);

        Mail::to('predikant@example.com')->send(new TestMailDiagnostics('staging'));

        $messages = app('mailer')->getSymfonyTransport()->messages();
        $sent = $messages[0]->getOriginalMessage();
        $ontvangers = array_map(fn ($a) => $a->getAddress(), $sent->getTo());

        $this->assertSame(['predikant@example.com'], $ontvangers);
    }
}
