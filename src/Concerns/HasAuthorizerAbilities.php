<?php

declare(strict_types=1);

namespace Laravarc\Authorizer\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravarc\Authorizer\Contracts\TenantResolver;
use Laravarc\Authorizer\Models\Ability;
use Laravarc\Authorizer\Models\Role;
use Laravarc\Authorizer\Models\UserRole;

/**
 * Optional trait for the application's User model.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasAuthorizerAbilities
{
    /**
     * @return BelongsToMany<Role, $this>
     */
    public function authorizerRoles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'larc_user_roles',
            'user_id',
            'role_id',
        );
    }

    /**
     * @return BelongsToMany<Ability, $this>
     */
    public function authorizerAbilities(): BelongsToMany
    {
        return $this->belongsToMany(
            Ability::class,
            'larc_user_abilities',
            'user_id',
            'ability_id',
        );
    }

    public function isSuper(): bool
    {
        return $this->authorizerRoles()
            ->where('is_super', true)
            ->exists();
    }

    /**
     * @param  string  $abilityKey  Dotted key, e.g. "customer.delete"
     */
    public function hasAbility(string $abilityKey): bool
    {
        if ($this->isSuper()) {
            return true;
        }

        [$policy, $ability] = $this->splitAbilityKey($abilityKey);

        if ($policy === null || $ability === null) {
            return false;
        }

        $abilityId = Ability::query()
            ->where('policy', $policy)
            ->where('ability', $ability)
            ->value('id');

        if ($abilityId === null) {
            return false;
        }

        $userId = $this->getKey();

        if ($this->authorizerAbilities()->whereKey($abilityId)->exists()) {
            return true;
        }

        $query = UserRole::query()
            ->where('larc_user_roles.user_id', $userId)
            ->joinRole()
            ->join('larc_role_abilities', 'larc_user_roles.role_id', '=', 'larc_role_abilities.role_id')
            ->where('larc_role_abilities.ability_id', $abilityId);

        $tenantId = $this->resolveAuthorizerTenantId();

        // Only when a TenantResolver returns a non-null context do we scope role
        // abilities. Null (default NullTenantResolver) preserves pre-patch union-all.
        if ($tenantId !== null) {
            $query->where('larc_roles.tenant_id', (string) $tenantId);
        }

        return $query->exists();
    }

    /**
     * Current tenant from the bound TenantResolver, or null when inactive.
     */
    private function resolveAuthorizerTenantId(): int|string|null
    {
        if (! app()->bound(TenantResolver::class)) {
            return null;
        }

        return app(TenantResolver::class)->current();
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function splitAbilityKey(string $abilityKey): array
    {
        $pos = strrpos($abilityKey, '.');

        if ($pos === false) {
            return [null, null];
        }

        return [
            substr($abilityKey, 0, $pos),
            substr($abilityKey, $pos + 1),
        ];
    }
}
