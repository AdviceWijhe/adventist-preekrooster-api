<?php

declare(strict_types=1);

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'voornaam',
        'tussenvoegsel',
        'achternaam',
        'initialen',
        'geslacht',
        'email',
        'password',
        'taal',
        'gemeente_id',
        'functie',
        'spreekniveau',
        'photo',
        'telefoonnummer',
        'mobiel',
        // Alleen config('two_factor.enabled') bepaalt of 2FA actief is; deze kolom is legacy.
        'two_factor_enabled',
        'active',
        'statistieken_toegang',
        'landelijk_actief',
        'last_login_at',
        'inactiviteit_waarschuwing_at',
        'avg_consent_at',
        'avg_refused_at',
        'avg_deferred_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'agenda_token',
    ];

    protected $appends = [
        'photo_url',
    ];

    protected function casts(): array
    {
        return [
            'two_factor_enabled' => 'boolean',
            'active' => 'boolean',
            'statistieken_toegang' => 'boolean',
            'landelijk_actief' => 'boolean',
            'last_login_at' => 'datetime',
            'inactiviteit_waarschuwing_at' => 'datetime',
            'avg_consent_at' => 'datetime',
            'avg_refused_at' => 'datetime',
            'avg_deferred_at' => 'datetime',
            'agenda_token_created_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot('gemeente_id')
            ->withTimestamps();
    }

    public function gemeente(): BelongsTo
    {
        return $this->belongsTo(Gemeente::class);
    }

    public function functies(): BelongsToMany
    {
        return $this->belongsToMany(Functie::class, 'user_functies')->withTimestamps();
    }

    public function gemeentes(): BelongsToMany
    {
        return $this->belongsToMany(Gemeente::class, 'user_gemeentes')->withTimestamps();
    }

    public function talen(): BelongsToMany
    {
        return $this->belongsToMany(Taal::class, 'user_talen')->withTimestamps();
    }

    /**
     * Publieke URL van de profielfoto, of null wanneer er geen foto is.
     */
    public function getPhotoUrlAttribute(): ?string
    {
        $photo = $this->attributes['photo'] ?? null;

        if (! $photo) {
            return null;
        }

        if (str_starts_with((string) $photo, 'http://') || str_starts_with((string) $photo, 'https://')) {
            return (string) $photo;
        }

        return Storage::disk('public')->url((string) $photo);
    }

    public function spreekbeurten(): HasMany
    {
        return $this->hasMany(Spreekbeurt::class, 'spreker_id');
    }

    public function ingevoerdeSpreekbeurten(): HasMany
    {
        return $this->hasMany(Spreekbeurt::class, 'ingevoerd_door');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function beschikbaarheid(): HasMany
    {
        return $this->hasMany(UserBeschikbaarheid::class);
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles()->where('slug', $slug)->exists();
    }

    public function hasFunctie(string $slug): bool
    {
        return $this->functies()->where('slug', $slug)->exists();
    }

    /**
     * @param  array<int, string>  $slugs
     */
    public function hasAnyFunctie(array $slugs): bool
    {
        return $this->functies()->whereIn('slug', $slugs)->exists();
    }

    public function kanPreken(): bool
    {
        return $this->hasAnyFunctie(['spreker', 'predikant'])
            || $this->hasRole('predikant');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isBeheerder(): bool
    {
        return $this->hasRole('admin') || $this->hasRole('beheerder');
    }

    /**
     * Admin heeft altijd toegang tot statistieken; beheerder alleen wanneer
     * dit per gebruiker is ingeschakeld.
     */
    public function heeftStatistiekToegang(): bool
    {
        return $this->isAdmin() || ($this->isBeheerder() && (bool) $this->statistieken_toegang);
    }

    public function volledigeNaam(): string
    {
        return trim(implode(' ', array_filter([$this->voornaam, $this->tussenvoegsel, $this->achternaam], static fn ($part) => filled($part))));
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification((string) $token));
    }

    public function ensureAgendaToken(): string
    {
        if ($this->agenda_token === null) {
            $this->forceFill([
                'agenda_token' => bin2hex(random_bytes(32)),
                'agenda_token_created_at' => now(),
            ])->save();
        }

        return (string) $this->agenda_token;
    }

    public function regenerateAgendaToken(): string
    {
        $this->forceFill([
            'agenda_token' => bin2hex(random_bytes(32)),
            'agenda_token_created_at' => now(),
        ])->save();

        return (string) $this->agenda_token;
    }
}
