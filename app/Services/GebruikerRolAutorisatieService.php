<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

class GebruikerRolAutorisatieService
{
    public const ADMIN_ROLE = 'admin';

    public const BEHEERDER_ROLE = 'beheerder';

    /** @var list<string> */
    public const PRIVILEGED_ROLES = [self::ADMIN_ROLE, self::BEHEERDER_ROLE];

    public function primaryAccessRole(User $user): string
    {
        if ($user->hasRole(self::ADMIN_ROLE)) {
            return self::ADMIN_ROLE;
        }
        if ($user->hasRole(self::BEHEERDER_ROLE)) {
            return self::BEHEERDER_ROLE;
        }

        return 'gebruiker';
    }

    public function assertKanGebruikerBeheren(User $actor, User $target): void
    {
        if ($target->isAdmin() && ! $actor->isAdmin()) {
            throw new HttpException(403, __('api.beheer.admin_account_admin_only'));
        }
    }

    /**
     * Beheerders mogen peers niet deactiveren of een nieuw wachtwoord zetten;
     * alleen admins (of de gebruiker zelf) mogen die velden wijzigen.
     *
     * @param  array<string, mixed>  $validated
     */
    public function assertKanGevoeligeVeldenWijzigen(User $actor, User $target, array $validated): void
    {
        $wijzigtWachtwoord = array_key_exists('password', $validated) && filled($validated['password'] ?? null);
        $wijzigtActive = array_key_exists('active', $validated)
            && (bool) $validated['active'] !== (bool) $target->active;

        if (! $wijzigtWachtwoord && ! $wijzigtActive) {
            return;
        }

        if ($actor->isAdmin() || $actor->is($target)) {
            return;
        }

        if ($target->isBeheerder()) {
            throw new HttpException(403, __('api.beheer.beheerder_sensitive_admin_only'));
        }
    }

    public function assertKanRolToewijzen(User $actor, ?User $target, string $requestedRoleSlug): void
    {
        $currentRoleSlug = $target !== null ? $this->primaryAccessRole($target) : 'gebruiker';

        if ($requestedRoleSlug === $currentRoleSlug) {
            return;
        }

        if ($requestedRoleSlug === self::ADMIN_ROLE || $currentRoleSlug === self::ADMIN_ROLE) {
            $this->assertAlleenAdminMagAdminRol($actor, $target, $requestedRoleSlug, $currentRoleSlug);

            return;
        }

        if ($requestedRoleSlug === self::BEHEERDER_ROLE || $currentRoleSlug === self::BEHEERDER_ROLE) {
            $this->assertAlleenAdminMagBeheerderRol($actor, $target, $requestedRoleSlug, $currentRoleSlug);

            return;
        }
    }

    private function assertAlleenAdminMagAdminRol(
        User $actor,
        ?User $target,
        string $requestedRoleSlug,
        string $currentRoleSlug,
    ): void {
        if (! $actor->isAdmin()) {
            throw new HttpException(403, __('api.beheer.role_assign_admin_forbidden'));
        }

        $this->assertGeenLaatsteAdminDegradatie($target, $requestedRoleSlug, $currentRoleSlug);
        $this->assertGeenEigenRolWijziging($actor, $target, $requestedRoleSlug, $currentRoleSlug);
    }

    private function assertAlleenAdminMagBeheerderRol(
        User $actor,
        ?User $target,
        string $requestedRoleSlug,
        string $currentRoleSlug,
    ): void {
        if (! $actor->isAdmin()) {
            throw new HttpException(403, __('api.beheer.role_change_beheerder_admin_only'));
        }

        $this->assertGeenEigenRolWijziging($actor, $target, $requestedRoleSlug, $currentRoleSlug);
    }

    private function assertGeenLaatsteAdminDegradatie(
        ?User $target,
        string $requestedRoleSlug,
        string $currentRoleSlug,
    ): void {
        if ($currentRoleSlug !== self::ADMIN_ROLE || $requestedRoleSlug === self::ADMIN_ROLE) {
            return;
        }

        $remainingAdmins = User::query()
            ->whereHas('roles', static fn ($query) => $query->where('slug', self::ADMIN_ROLE))
            ->when($target !== null, static fn ($query) => $query->whereKeyNot($target->id))
            ->count();

        if ($remainingAdmins === 0) {
            throw new HttpException(403, __('api.beheer.cannot_demote_last_admin'));
        }
    }

    private function assertGeenEigenRolWijziging(
        User $actor,
        ?User $target,
        string $requestedRoleSlug,
        string $currentRoleSlug,
    ): void {
        if ($target !== null && $actor->is($target) && $requestedRoleSlug !== $currentRoleSlug) {
            throw new HttpException(403, __('api.beheer.role_change_self_forbidden'));
        }
    }
}
