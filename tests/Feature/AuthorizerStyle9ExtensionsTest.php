<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Laravarc\Authorizer\Models\Ability;
use Laravarc\Authorizer\Models\Role;
use Laravarc\Authorizer\Models\UserRole;
use Laravarc\Authorizer\Services\AuthorizationService;
use Laravarc\Authorizer\Support\UserRoleSync;
use Laravarc\Authorizer\Tests\Fixtures\User;

describe('Role uuid on create', function () {
    it('auto-assigns uuid when omitted', function () {
        $role = Role::query()->global()->create([
            'name' => 'AutoUuid',
            'description' => 'Test role',
            'is_system' => false,
            'is_super' => false,
        ]);

        expect($role->uuid)->not->toBeEmpty()
            ->and(strlen($role->uuid))->toBe(36);
    });

    it('preserves an explicit uuid when provided', function () {
        $uuid = (string) Str::uuid();

        $role = Role::query()->global()->create([
            'uuid' => $uuid,
            'name' => 'ExplicitUuid',
            'description' => null,
            'is_system' => true,
            'is_super' => false,
        ]);

        expect($role->uuid)->toBe($uuid);
    });
});

describe('Role::abilities()->sync()', function () {
    it('replaces pivot rows for the role', function () {
        $svc = app(AuthorizationService::class);
        $role = $svc->createRole('SyncRole', tenantId: null);

        $first = Ability::query()->create([
            'policy' => 'sync.test',
            'ability' => 'first',
            'description' => null,
        ]);
        $second = Ability::query()->create([
            'policy' => 'sync.test',
            'ability' => 'second',
            'description' => null,
        ]);
        $third = Ability::query()->create([
            'policy' => 'sync.test',
            'ability' => 'third',
            'description' => null,
        ]);

        $role->abilities()->sync([$first->id, $second->id]);
        $role->abilities()->sync([$second->id, $third->id]);

        $abilityIds = $role->abilities()->orderBy('id')->pluck('larc_abilities.id')->map(fn ($id) => (int) $id)->all();

        expect($abilityIds)->toBe([(int) $second->id, (int) $third->id]);
    });
});

describe('UserRole tenant query helpers', function () {
    beforeEach(function () {
        $svc = app(AuthorizationService::class);
        $this->globalRole = $svc->createRole('Platform', tenantId: null);
        $this->tenantOneRole = $svc->createRole('CompanyOne', tenantId: 1);
        $this->tenantTwoRole = $svc->createRole('CompanyTwo', tenantId: 2);
    });

    it('tenantIdsForUser returns distinct tenant ids only', function () {
        $user = User::query()->create(['name' => 'U', 'email' => 'u@t.test']);

        UserRole::query()->insert([
            ['user_id' => $user->id, 'role_id' => $this->globalRole->id],
            ['user_id' => $user->id, 'role_id' => $this->tenantOneRole->id],
            ['user_id' => $user->id, 'role_id' => $this->tenantTwoRole->id],
        ]);

        expect(UserRole::tenantIdsForUser($user->id))->toBe([1, 2]);
    });

    it('userHasTenantRole returns true when user has a tenant role', function () {
        $user = User::query()->create(['name' => 'U', 'email' => 'u@t.test']);

        UserRole::query()->insert([
            ['user_id' => $user->id, 'role_id' => $this->tenantOneRole->id],
        ]);

        expect(UserRole::userHasTenantRole($user->id, 1))->toBeTrue()
            ->and(UserRole::userHasTenantRole($user->id, 2))->toBeFalse();
    });

    it('forUserAndTenant scope filters joined rows', function () {
        $user = User::query()->create(['name' => 'U', 'email' => 'u@t.test']);

        UserRole::query()->insert([
            ['user_id' => $user->id, 'role_id' => $this->tenantOneRole->id],
            ['user_id' => $user->id, 'role_id' => $this->tenantTwoRole->id],
        ]);

        $roleIds = UserRole::query()
            ->forUserAndTenant($user->id, 1)
            ->pluck('larc_user_roles.role_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        expect($roleIds)->toBe([(int) $this->tenantOneRole->id]);
    });

    it('whereTenantRole filters joined rows to tenant roles', function () {
        $staff = User::query()->create(['name' => 'Staff', 'email' => 'staff@t.test']);
        $tenantUser = User::query()->create(['name' => 'Tenant', 'email' => 'tenant@t.test']);

        UserRole::query()->insert([
            ['user_id' => $staff->id, 'role_id' => $this->globalRole->id],
            ['user_id' => $tenantUser->id, 'role_id' => $this->tenantOneRole->id],
        ]);

        $userIds = UserRole::query()
            ->joinRole()
            ->whereTenantRole()
            ->pluck('larc_user_roles.user_id')
            ->all();

        expect($userIds)->toBe([$tenantUser->id]);
    });
});

describe('UserRoleSync write helpers', function () {
    it('assign attaches a user to a role idempotently', function () {
        $svc = app(AuthorizationService::class);
        $role = $svc->createRole('Owner', tenantId: 99);

        $user = User::query()->create(['name' => 'Owner', 'email' => 'owner@t.test']);

        UserRoleSync::assign($user->id, $role->id);
        UserRoleSync::assign($user->id, $role->id);

        expect(UserRole::query()->where('user_id', $user->id)->count())->toBe(1)
            ->and(UserRole::userHasTenantRole($user->id, 99))->toBeTrue();
    });

    it('detachAllForUser removes every role assignment', function () {
        $svc = app(AuthorizationService::class);
        $global = $svc->createRole('Global', tenantId: null);
        $tenant = $svc->createRole('Tenant', tenantId: 5);

        $user = User::query()->create(['name' => 'U', 'email' => 'u@t.test']);
        UserRole::query()->insert([
            ['user_id' => $user->id, 'role_id' => $global->id],
            ['user_id' => $user->id, 'role_id' => $tenant->id],
        ]);

        UserRoleSync::detachAllForUser($user->id);

        expect(UserRole::query()->where('user_id', $user->id)->count())->toBe(0);
    });
});
