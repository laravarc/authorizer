<?php

declare(strict_types=1);

use Laravarc\Authorizer\Models\Role;
use Laravarc\Authorizer\Models\UserRole;
use Laravarc\Authorizer\Services\AuthorizationService;
use Laravarc\Authorizer\Support\UserRoleSync;
use Laravarc\Authorizer\Tests\Fixtures\User;

describe('Role query scopes', function () {
    it('global() returns only roles with null tenant_id', function () {
        $svc = app(AuthorizationService::class);
        $svc->createRole('GlobalAdmin', tenantId: null);
        $svc->createRole('TenantAdmin', tenantId: 1);

        $names = Role::query()->global()->orderBy('name')->pluck('name')->all();

        expect($names)->toBe(['GlobalAdmin']);
    });

    it('forTenant() returns roles for a specific tenant', function () {
        $svc = app(AuthorizationService::class);
        $svc->createRole('GlobalAdmin', tenantId: null);
        $svc->createRole('TenantOne', tenantId: 1);
        $svc->createRole('TenantTwo', tenantId: 2);

        $names = Role::query()->forTenant(1)->orderBy('name')->pluck('name')->all();

        expect($names)->toBe(['TenantOne']);
    });

    it('users() returns users assigned via the pivot', function () {
        $svc = app(AuthorizationService::class);
        $role = $svc->createRole('Staff', tenantId: null);

        $alice = User::query()->create(['name' => 'Alice', 'email' => 'alice@t.test']);
        $bob = User::query()->create(['name' => 'Bob', 'email' => 'bob@t.test']);

        UserRole::query()->insert([
            ['user_id' => $alice->id, 'role_id' => $role->id],
            ['user_id' => $bob->id, 'role_id' => $role->id],
        ]);

        $emails = $role->users()->orderBy('email')->pluck('email')->all();

        expect($emails)->toBe(['alice@t.test', 'bob@t.test']);
    });
});

describe('UserRole query scopes', function () {
    beforeEach(function () {
        $svc = app(AuthorizationService::class);
        $this->globalRole = $svc->createRole('Platform', tenantId: null);
        $this->tenantRole = $svc->createRole('Company', tenantId: 1);
    });

    it('globalUserIds() returns distinct user ids with global roles only', function () {
        $staff = User::query()->create(['name' => 'Staff', 'email' => 'staff@t.test']);
        $tenantUser = User::query()->create(['name' => 'Tenant', 'email' => 'tenant@t.test']);
        $plain = User::query()->create(['name' => 'Plain', 'email' => 'plain@t.test']);

        UserRole::query()->insert([
            ['user_id' => $staff->id, 'role_id' => $this->globalRole->id],
            ['user_id' => $tenantUser->id, 'role_id' => $this->tenantRole->id],
        ]);

        $ids = User::query()
            ->whereIn('id', UserRole::globalUserIds())
            ->orderBy('email')
            ->pluck('email')
            ->all();

        expect($ids)->toBe(['staff@t.test'])
            ->and(User::query()->whereNotIn('id', UserRole::globalUserIds())->orderBy('email')->pluck('email')->all())
            ->toBe(['plain@t.test', 'tenant@t.test']);
    });

    it('whereGlobalRole() filters joined rows to global roles', function () {
        $staff = User::query()->create(['name' => 'Staff', 'email' => 'staff@t.test']);
        $tenantUser = User::query()->create(['name' => 'Tenant', 'email' => 'tenant@t.test']);

        UserRole::query()->insert([
            ['user_id' => $staff->id, 'role_id' => $this->globalRole->id],
            ['user_id' => $tenantUser->id, 'role_id' => $this->tenantRole->id],
        ]);

        $userIds = UserRole::query()
            ->joinRole()
            ->whereGlobalRole()
            ->pluck('larc_user_roles.user_id')
            ->all();

        expect($userIds)->toBe([$staff->id]);
    });
});

describe('authorizerRoles()->global() on User', function () {
    it('returns only global roles for the user', function () {
        $svc = app(AuthorizationService::class);
        $global = $svc->createRole('Global', tenantId: null);
        $tenant = $svc->createRole('Tenant', tenantId: 1);

        $user = User::query()->create(['name' => 'U', 'email' => 'u@t.test']);
        UserRole::query()->insert([
            ['user_id' => $user->id, 'role_id' => $global->id],
            ['user_id' => $user->id, 'role_id' => $tenant->id],
        ]);

        $names = $user->authorizerRoles()->global()->orderBy('name')->pluck('name')->all();

        expect($names)->toBe(['Global']);
    });
});

describe('Role::global()->whereHas(users)', function () {
    it('finds global roles for a user', function () {
        $svc = app(AuthorizationService::class);
        $role = $svc->createRole('Editor', tenantId: null);
        $svc->createRole('Other', tenantId: null);

        $user = User::query()->create(['name' => 'U', 'email' => 'u@t.test']);
        UserRole::query()->insert([
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);

        $names = Role::query()
            ->global()
            ->whereHas('users', fn ($q) => $q->whereKey($user->id))
            ->orderBy('name')
            ->pluck('name')
            ->all();

        expect($names)->toBe(['Editor']);
    });
});

describe('UserRoleSync::replaceGlobal', function () {
    it('replaces global role assignments and preserves tenant roles', function () {
        $svc = app(AuthorizationService::class);
        $oldGlobal = $svc->createRole('OldGlobal', tenantId: null);
        $newGlobal = $svc->createRole('NewGlobal', tenantId: null);
        $tenantRole = $svc->createRole('Tenant', tenantId: 1);

        $user = User::query()->create(['name' => 'U', 'email' => 'u@t.test']);
        UserRole::query()->insert([
            ['user_id' => $user->id, 'role_id' => $oldGlobal->id],
            ['user_id' => $user->id, 'role_id' => $tenantRole->id],
        ]);

        UserRoleSync::replaceGlobal($user->id, $newGlobal->id);

        $roleIds = UserRole::query()
            ->where('user_id', $user->id)
            ->orderBy('role_id')
            ->pluck('role_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        expect($roleIds)->toBe([(int) $newGlobal->id, (int) $tenantRole->id]);
    });
});
