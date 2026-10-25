<?php

declare(strict_types=1);

namespace Laravarc\Authorizer\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Laravarc\Authorizer\Contracts\AbilityRegistry;
use Laravarc\Authorizer\Contracts\TenantResolver;
use Laravarc\Authorizer\Models\Ability;
use Laravarc\Authorizer\Models\Role;
use Laravarc\Authorizer\Support\Enums\UserAbilityEffect;
use RuntimeException;

final class AuthorizationService
{
    public function __construct(
        private readonly TenantResolver $tenants,
        private readonly AbilityRegistry $registry,
    ) {}

    /**
     * Create a role. Uniqueness within the same tenant scope (including NULL) is enforced here.
     */
    public function createRole(
        string $name,
        ?string $description = null,
        int|string|null $tenantId = null,
        bool $isSystem = false,
        bool $isSuper = false,
    ): Role {
        $tenantId ??= $this->tenants->current();

        $this->assertRoleNameUnique($name, $tenantId);

        return Role::query()->create([
            'name' => $name,
            'description' => $description,
            'tenant_id' => $tenantId,
            'is_system' => $isSystem,
            'is_super' => $isSuper,
        ]);
    }

    public function findRoleOrFail(string $name, int|string|null $tenantId = null): Role
    {
        $tenantId ??= $this->tenants->current();

        $query = Role::query()->where('name', $name);

        if ($tenantId === null) {
            $query->whereNull('tenant_id');
        } else {
            $query->where('tenant_id', $tenantId);
        }

        $role = $query->first();

        if ($role === null) {
            throw (new ModelNotFoundException)->setModel(
                Role::class,
                [sprintf('name=%s tenant_id=%s', $name, var_export($tenantId, true))],
            );
        }

        return $role;
    }

    /**
     * @param  class-string  $policyClass
     * @param  list<string>|null  $only
     * @param  list<string>|null  $except
     */
    public function grantToRole(Role $role, string $policyClass, ?array $only = null, ?array $except = null): void
    {
        DB::transaction(function () use ($role, $policyClass, $only, $except): void {
            $abilityIds = $this->resolveAbilityIds($policyClass, $only, $except);

            foreach ($abilityIds as $abilityId) {
                DB::table('larc_role_abilities')->insertOrIgnore([
                    'role_id' => $role->id,
                    'ability_id' => $abilityId,
                ]);
            }
        });
    }

    /**
     * Assign a role by name, scoped to TenantResolver::current()
     * (same NULL/tenant resolution as createRole / findRoleOrFail — intentional).
     *
     * @param  object{getKey(): mixed}  $user
     */
    public function assignRole(object $user, string $roleName): void
    {
        $role = $this->findRoleOrFail($roleName);

        DB::table('larc_user_roles')->insertOrIgnore([
            'user_id' => $user->getKey(),
            'role_id' => $role->id,
        ]);
    }

    /**
     * @param  object{getKey(): mixed}  $user
     * @param  class-string  $policyClass
     * @param  list<string>|null  $only
     * @param  list<string>|null  $except
     */
    public function allowUser(
        object $user,
        string $policyClass,
        ?array $only = null,
        ?array $except = null,
        int|string|null $tenantId = null,
    ): void {
        $this->setUserPolicyAbilities(
            user: $user,
            policyClass: $policyClass,
            effect: UserAbilityEffect::Allow,
            only: $only,
            except: $except,
            tenantId: $tenantId,
        );
    }

    public function denyUser(
        object $user,
        string $policyClass,
        ?array $only = null,
        ?array $except = null,
        int|string|null $tenantId = null,
    ): void {
        $this->setUserPolicyAbilities(
            user: $user,
            policyClass: $policyClass,
            effect: UserAbilityEffect::Deny,
            only: $only,
            except: $except,
            tenantId: $tenantId,
        );
    }

    /**
     * @param  object{getKey(): mixed}  $user
     */
    public function setUserAbilityEffect(
        object $user,
        int $abilityId,
        UserAbilityEffect $effect,
        int|string|null $tenantId = null,
    ): void {
        $tenantId = $this->normalizeUserAbilityTenantId($tenantId);

        if ($tenantId === null) {
            $this->upsertGlobalUserAbilityEffect($user->getKey(), $abilityId, $effect);

            return;
        }

        DB::table('larc_user_abilities')->upsert(
            [[
                'user_id' => $user->getKey(),
                'ability_id' => $abilityId,
                'tenant_id' => $tenantId,
                'effect' => $effect->value,
            ]],
            ['user_id', 'ability_id', 'tenant_id'],
            ['effect'],
        );
    }

    /**
     * UNIQUE (user_id, ability_id, tenant_id) treats NULL as distinct in MySQL/SQLite,
     * so upsert cannot collapse duplicate global rows — update-or-insert instead.
     */
    private function upsertGlobalUserAbilityEffect(
        int|string $userId,
        int $abilityId,
        UserAbilityEffect $effect,
    ): void {
        DB::transaction(function () use ($userId, $abilityId, $effect): void {
            DB::table('larc_user_abilities')
                ->where('user_id', $userId)
                ->where('ability_id', $abilityId)
                ->whereNull('tenant_id')
                ->delete();

            DB::table('larc_user_abilities')->insert([
                'user_id' => $userId,
                'ability_id' => $abilityId,
                'tenant_id' => null,
                'effect' => $effect->value,
            ]);
        });
    }

    /**
     * @param  object{getKey(): mixed}  $user
     * @param  class-string  $policyClass
     * @param  list<string>|null  $only
     * @param  list<string>|null  $except
     */
    private function setUserPolicyAbilities(
        object $user,
        string $policyClass,
        UserAbilityEffect $effect,
        ?array $only,
        ?array $except,
        int|string|null $tenantId,
    ): void {
        DB::transaction(function () use ($user, $policyClass, $effect, $only, $except, $tenantId): void {
            $abilityIds = $this->resolveAbilityIds($policyClass, $only, $except);
            $tenantId = $this->normalizeUserAbilityTenantId($tenantId);

            foreach ($abilityIds as $abilityId) {
                $this->setUserAbilityEffect($user, $abilityId, $effect, $tenantId);
            }
        });
    }

    private function normalizeUserAbilityTenantId(int|string|null $tenantId): ?string
    {
        $tenantId ??= $this->tenants->current();

        if ($tenantId === null) {
            return null;
        }

        return (string) $tenantId;
    }

    /**
     * @param  class-string  $policyClass
     * @param  list<string>|null  $only
     * @param  list<string>|null  $except
     * @return list<int>
     */
    private function resolveAbilityIds(string $policyClass, ?array $only, ?array $except): array
    {
        $policyKey = $this->registry->keyFor($policyClass);

        if ($policyKey === null) {
            throw new InvalidArgumentException(sprintf(
                'Policy [%s] is not in the ability registry. Run laravarc:authorizer cache / laravarc:authorizer sync first.',
                $policyClass,
            ));
        }

        $methods = $this->registry->all()[$policyKey] ?? [];

        if ($only !== null) {
            $methods = array_values(array_intersect($methods, $only));
        }

        if ($except !== null) {
            $methods = array_values(array_diff($methods, $except));
        }

        if ($methods === []) {
            return [];
        }

        $found = Ability::query()
            ->where('policy', $policyKey)
            ->whereIn('ability', $methods)
            ->get(['id', 'ability'])
            ->keyBy('ability');

        $ids = [];

        foreach ($methods as $method) {
            $ability = $found->get($method);

            if ($ability === null) {
                throw new RuntimeException(sprintf(
                    'Ability [%s.%s] is not in the database. Run php artisan laravarc:authorizer sync first.',
                    $policyKey,
                    $method,
                ));
            }

            $ids[] = (int) $ability->id;
        }

        return $ids;
    }

    private function assertRoleNameUnique(string $name, int|string|null $tenantId): void
    {
        $query = Role::query()->where('name', $name);

        if ($tenantId === null) {
            $query->whereNull('tenant_id');
        } else {
            $query->where('tenant_id', $tenantId);
        }

        if ($query->exists()) {
            throw new InvalidArgumentException(sprintf(
                'Role [%s] already exists for tenant_id=%s.',
                $name,
                var_export($tenantId, true),
            ));
        }
    }
}
