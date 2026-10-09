<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Services\Branding\BrandingService;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MailDiagnosticsService
{
    public function __construct(
        private readonly BrandingService $brandingService
    ) {}

    public function run(): array
    {
        return [
            'checks' => [
                'config_presence' => $this->checkConfigPresence(),
                'smtp_connectivity' => $this->checkSmtpConnectivity(),
                'mailer_runtime' => $this->checkMailerRuntime(),
                'queue_status' => $this->checkQueueStatus(),
                'dns_check' => $this->checkDns(),
                'branding_presence' => $this->checkBrandingPresence(),
            ],
            'meta' => [
                'mail_mode' => $this->resolveDeliveryMode(),
                'app_env' => app()->environment(),
                'mailer' => (string) config('mail.default'),
                'queue_connection' => (string) config('queue.default'),
            ],
        ];
    }

    private function checkBrandingPresence(): array
    {
        $branding = $this->brandingService->all();
        $required = [
            'branding_app_name',
            'branding_primary_color',
            'branding_from_name',
            'branding_footer_text',
        ];

        $missing = collect($required)
            ->filter(fn (string $key): bool => trim((string) ($branding[$key] ?? '')) === '')
            ->values()
            ->all();

        if ($missing !== []) {
            return [
                'status' => 'warning',
                'message' => __('api.mail_diagnostics.branding_incomplete'),
                'details' => ['missing' => $missing],
            ];
        }

        return [
            'status' => 'ok',
            'message' => __('api.mail_diagnostics.branding_complete'),
        ];
    }

    public function resolveDeliveryMode(): string
    {
        $configuredMode = (string) config('mail.test_delivery_mode', 'auto_by_env');
        if ($configuredMode === 'sync' || $configuredMode === 'queue') {
            return $configuredMode;
        }

        return app()->environment('production') ? 'queue' : 'sync';
    }

    private function checkConfigPresence(): array
    {
        $mailer = (string) config('mail.default');
        $required = ['mail.from.address', 'mail.from.name'];

        if ($mailer === 'smtp') {
            $required = array_merge($required, [
                'mail.mailers.smtp.host',
                'mail.mailers.smtp.port',
                'mail.mailers.smtp.username',
                'mail.mailers.smtp.password',
            ]);
        }

        $missing = [];
        foreach ($required as $key) {
            $value = config($key);
            if ($value === null || $value === '') {
                $missing[] = $key;
            }
        }

        if ($missing !== []) {
            return [
                'status' => 'error',
                'message' => __('api.mail_diagnostics.config_missing'),
                'details' => ['missing' => $missing, 'mailer' => $mailer],
            ];
        }

        return [
            'status' => 'ok',
            'message' => __('api.mail_diagnostics.config_present'),
            'details' => ['mailer' => $mailer],
        ];
    }

    private function checkSmtpConnectivity(): array
    {
        $mailer = (string) config('mail.default');
        if ($mailer !== 'smtp') {
            return [
                'status' => 'warning',
                'message' => __('api.mail_diagnostics.smtp_skipped'),
            ];
        }

        $host = (string) config('mail.mailers.smtp.host');
        $port = (int) config('mail.mailers.smtp.port');
        $scheme = (string) config('mail.mailers.smtp.scheme', '');
        $transport = $scheme === 'ssl' ? 'ssl' : 'tcp';
        $target = sprintf('%s://%s:%d', $transport, $host, $port);

        $connection = @stream_socket_client($target, $errno, $error, 3);

        if ($connection === false) {
            return [
                'status' => 'error',
                'message' => "SMTP-host {$host}:{$port} is niet bereikbaar.",
                'details' => ['errno' => $errno, 'error' => $error],
            ];
        }

        fclose($connection);

        return [
            'status' => 'ok',
            'message' => "SMTP-host {$host}:{$port} is bereikbaar.",
            'details' => ['transport' => $transport],
        ];
    }

    private function checkMailerRuntime(): array
    {
        try {
            /** @var Mailer $mailer */
            $mailer = Mail::mailer();
            $mailer->getSymfonyTransport();

            return [
                'status' => 'ok',
                'message' => __('api.mail_diagnostics.runtime_ok'),
            ];
        } catch (Throwable $throwable) {
            return [
                'status' => 'error',
                'message' => __('api.mail_diagnostics.runtime_failed'),
                'details' => ['error' => $throwable->getMessage()],
            ];
        }
    }

    private function checkQueueStatus(): array
    {
        $connection = (string) config('queue.default');
        $mode = $this->resolveDeliveryMode();

        if ($mode !== 'queue') {
            return [
                'status' => 'ok',
                'message' => __('api.mail_diagnostics.queue_not_required'),
                'details' => ['queue_connection' => $connection, 'delivery_mode' => $mode],
            ];
        }

        if ($connection === 'sync') {
            return [
                'status' => 'warning',
                'message' => __('api.mail_diagnostics.queue_sync_warning'),
                'details' => ['queue_connection' => $connection, 'delivery_mode' => $mode],
            ];
        }

        return [
            'status' => 'ok',
            'message' => "Queue verbinding '{$connection}' actief voor asynchrone mail.",
            'details' => ['queue_connection' => $connection, 'delivery_mode' => $mode],
        ];
    }

    private function checkDns(): array
    {
        $fromAddress = (string) config('mail.from.address');
        $domain = Arr::last(explode('@', $fromAddress));

        if ($domain === null || $domain === '') {
            return [
                'status' => 'error',
                'message' => __('api.mail_diagnostics.invalid_from_domain'),
            ];
        }

        $mxRecords = @dns_get_record($domain, DNS_MX);
        $txtRecords = @dns_get_record($domain, DNS_TXT);
        $dmarcRecords = @dns_get_record('_dmarc.'.$domain, DNS_TXT);

        $hasMx = is_array($mxRecords) && $mxRecords !== [];
        $hasSpf = is_array($txtRecords) && collect($txtRecords)
            ->contains(fn (array $record): bool => str_starts_with((string) ($record['txt'] ?? ''), 'v=spf1'));
        $hasDmarc = is_array($dmarcRecords) && collect($dmarcRecords)
            ->contains(fn (array $record): bool => str_starts_with((string) ($record['txt'] ?? ''), 'v=DMARC1'));

        $issues = [];
        if (! $hasMx) {
            $issues[] = 'MX-record ontbreekt';
        }
        if (! $hasSpf) {
            $issues[] = 'SPF-record ontbreekt';
        }
        if (! $hasDmarc) {
            $issues[] = 'DMARC-record ontbreekt';
        }

        if ($issues !== []) {
            return [
                'status' => 'warning',
                'message' => __('api.mail_diagnostics.dns_issues'),
                'details' => ['domain' => $domain, 'issues' => $issues],
            ];
        }

        return [
            'status' => 'ok',
            'message' => __('api.mail_diagnostics.dns_ok'),
            'details' => ['domain' => $domain],
        ];
    }
}
