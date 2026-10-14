<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Spreekbeurt;
use App\Observers\SpreekbeurtObserver;
use App\Services\Branding\BrandingService;
use App\Services\Mail\MailTemplateService;
use App\Services\Security\ProductionConfigGuard;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View as ViewInstance;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        app(ProductionConfigGuard::class)->assertSafe();

        Password::defaults(function () {
            $rule = Password::min(8)->mixedCase();

            if (! app()->environment(['local', 'testing'])) {
                $rule->uncompromised();
            }

            return $rule;
        });

        Spreekbeurt::observe(SpreekbeurtObserver::class);
        $branding = app(BrandingService::class)->mailBranding();
        config([
            'mail.from.name' => $branding['from_name'],
        ]);

        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

            return "{$frontendUrl}/wachtwoord-reset/{$token}";
        });

        $this->registerMailInterceptor();
        $this->registerMailSignatureComposer();
    }

    /**
     * Maakt de (optioneel ingestelde) e-mailhandtekening beschikbaar als `$mailSignature`
     * in elke mailview, zodat deze zonder aanpassingen aan losse Mailables onderaan
     * elke uitgaande e-mail kan worden getoond.
     */
    private function registerMailSignatureComposer(): void
    {
        View::composer('mail.*', function (ViewInstance $view): void {
            $appName = app(BrandingService::class)->mailBranding()['app_name'] ?? config('app.name');

            $view->with('mailSignature', app(MailTemplateService::class)->renderSignature([
                'app_name' => $appName,
            ]));
        });
    }

    /**
     * In de testomgeving: leid alle uitgaande mail om naar één veilig adres,
     * zodat er nooit echte e-mails naar predikanten of gebruikers gaan.
     *
     * De config-check zit bewust in de listener (niet eromheen) zodat de
     * interceptor ook werkt wanneer config tijdens runtime/tests wijzigt.
     */
    private function registerMailInterceptor(): void
    {
        Event::listen(function (MessageSending $event): void {
            if (! config('mail.intercept.enabled')) {
                return;
            }

            $redirectTo = (string) config('mail.intercept.redirect_to');

            if ($redirectTo === '') {
                return;
            }

            $prefix = (string) config('mail.intercept.subject_prefix', '[TEST]');
            $message = $event->message;

            $origineleOntvangers = collect($message->getTo() ?: [])
                ->map(fn ($address) => $address->getAddress())
                ->implode(', ');

            $message->to($redirectTo);
            $message->cc();
            $message->bcc();

            $onderwerp = (string) $message->getSubject();
            $details = $origineleOntvangers !== '' ? " (oorspronkelijk: {$origineleOntvangers})" : '';
            $message->subject(trim("{$prefix} {$onderwerp}{$details}"));
        });
    }
}
