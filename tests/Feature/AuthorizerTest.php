<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Laravarc\Authorizer\Contracts\AbilityRegistry;
use Laravarc\Authorizer\Contracts\TenantResolver;
use Laravarc\Authorizer\Facades\Authorization;
use Laravarc\Authorizer\Facades\Role as RoleFacade;
use Laravarc\Authorizer\Models\Ability;
use Laravarc\Authorizer\Models\Role;
use Laravarc\Authorizer\Providers\AuthorizerServiceProvider;
use Laravarc\Authorizer\Resolvers\ClassMapAbilityRegistry;
use Laravarc\Authorizer\Services\AbilitySyncService;
use Laravarc\Authorizer\Services\AuthorizationService;
use Laravarc\Authorizer\Support\AbilityIndexBuildException;
use Laravarc\Authorizer\Support\AbilityIndexBuilder;
use Laravarc\Authorizer\Support\NullTenantResolver;
use Laravarc\Authorizer\Tests\Fixtures\Customer;
use Laravarc\Authorizer\Tests\Fixtures\Duplicate\CustomerPolicy as DuplicateCustomerPolicy;
use Laravarc\Authorizer\Tests\Fixtures\Policies\CustomerPolicy;
use Laravarc\Authorizer\Tests\Fixtures\User;

describe('Native ability discovery', function () {
    it('derives customer.* abilities from CustomerPolicy without Core', function () {
        /** @var ClassMapAbilityRegistry $registry */
        $registry = app(AbilityRegistry::class);

        $all = $registry->all();

        expect($all)->toHaveKey('customer')
            ->and($all['customer'])->toContain('view', 'update', 'delete', 'export')
            ->and($registry->keyFor(CustomerPolicy::class))->toBe('customer')
            ->and($registry->keyFor(Customer::class))->toBe('customer');
    });

    it('fails laravarc:authorizer cache when two policies share an ability key', function () {
        $builder = new AbilityIndexBuilder(includeClasses: [
            CustomerPolicy::class,
            DuplicateCustomerPolicy::class,
        ]);

        expect(fn () => $builder->buildFrom([
            CustomerPolicy::class,
            DuplicateCustomerPolicy::class,
        ]))->toThrow(AbilityIndexBuildException::class, 'Duplicate ability key [customer]');
    });
});

describe('Gate::before', function () {
    beforeEach(function () {
        CustomerPolicy::reset();
        Gate::policy(Customer::class, CustomerPolicy::class);

        /** @var ClassMapAbilityRegistry $registry */
        $registry = app(ClassMapAbilityRegistry::class);
        $registry->refresh();

        app(AbilitySyncService::class)->insertNew();
    });

    it('returns true for is_super and never needs policy', function () {
        $user = User::query()->create(['name' => 'Owner', 'email' => 'o@t.test']);
        app(AuthorizationService::class)->createRole('Boss', isSystem: true, isSuper: true);
        app(AuthorizationService::class)->assignRole($user, 'Boss');

        expect(Gate::forUser($user)->allows('delete', new Customer))->toBeTrue()
            ->and(CustomerPolicy::$deleteCalled)->toBeFalse();
    });

    it('returns null for unknown abilities so Policy still runs', function () {
        $user = User::query()->create(['name' => 'U', 'email' => 'u@t.test']);

        // Register a Gate ability that is NOT in the authorizer registry
        Gate::define('custom-untracked', fn () => true);

        expect(Gate::forUser($user)->allows('custom-untracked'))->toBeTrue();
    });

    it('short-circuits with false when RBAC denies — Policy method is never called', function () {
        $user = User::query()->create(['name' => 'U', 'email' => 'u@t.test']);

        expect(Gate::forUser($user)->denies('delete', new Customer))->toBeTrue()
            ->and(CustomerPolicy::$deleteCalled)->toBeFalse();
    });

    it('passes RBAC then runs Policy business rule when user has ability', function () {
        $user = User::query()->create(['name' => 'U', 'email' => 'u@t.test']);

        RoleFacade::create('Editor')->grant(CustomerPolicy::class)->only(['update', 'delete']);
        Authorization::user($user)->assignRole('Editor');

        expect(Gate::forUser($user)->allows('update', new Customer))->toBeTrue()
            ->and(CustomerPolicy::$updateCalled)->toBeTrue();
    });
});

describe('Tenant-scoped assignRole', function () {
    it('resolves the role for the current tenant only', function () {
        $svc = app(AuthorizationService::class);
        $svc->createRole('Admin', tenantId: 1);
        $svc->createRole('Admin', tenantId: 2);

        $user = User::query()->create(['name' => 'U', 'email' => 'u@t.test']);

        app()->instance(TenantResolver::class, new class implements TenantResolver
        {
            public function current(): int|string|null
            {
                return 2;
            }
        });

        app()->forgetInstance(AuthorizationService::class);
        app()->singleton(AuthorizationService::class, fn ($app) => new AuthorizationService(
            $app->make(TenantResolver::class),
            $app->make(AbilityRegistry::class),
        ));

        app(AuthorizationService::class)->assignRole($user, 'Admin');

        $roleId = Role::query()->where('name', 'Admin')->where('tenant_id', 2)->value('id');
        $assigned = \Illuminate\Support\Facades\DB::table('larc_user_roles')
            ->where('user_id', $user->id)
            ->value('role_id');

        expect((int) $assigned)->toBe((int) $roleId);
    });

    it('throws when the role name is not found in the current tenant scope', function () {
        app(AuthorizationService::class)->createRole('Admin', tenantId: 1);

        app()->instance(TenantResolver::class, new class implements TenantResolver
        {
            public function current(): int|string|null
            {
                return 99;
            }
        });
        app()->forgetInstance(AuthorizationService::class);
        app()->singleton(AuthorizationService::class, fn ($app) => new AuthorizationService(
            $app->make(TenantResolver::class),
            $app->make(AbilityRegistry::class),
        ));

        $user = User::query()->create(['name' => 'U', 'email' => 'u@t.test']);

        expect(fn () => app(AuthorizationService::class)->assignRole($user, 'Admin'))
            ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });
});

describe('NullTenantResolver default', function () {
    it('binds NullTenantResolver and works with tenant_id null', function () {
        expect(app(TenantResolver::class))->toBeInstanceOf(NullTenantResolver::class);

        $svc = app(AuthorizationService::class);
        $role = $svc->createRole('Accounting');

        expect($role->tenant_id)->toBeNull();

        $registry = app(ClassMapAbilityRegistry::class);
        $registry->refresh();
        app(AbilitySyncService::class)->insertNew();

        RoleFacade::create('Clerk')->grant(CustomerPolicy::class)->only(['view']);

        $user = User::query()->create(['name' => 'U', 'email' => 'u@t.test']);
        Authorization::user($user)->assignRole('Clerk');

        expect($user->hasAbility('customer.view'))->toBeTrue();
    });
});

describe('hasAbility tenant scoping (Decision 031)', function () {
    beforeEach(function () {
        $registry = app(ClassMapAbilityRegistry::class);
        $registry->refresh();
        app(AbilitySyncService::class)->insertNew();
    });

    it('unions all roles when TenantResolver returns null (backward compatible)', function () {
        $svc = app(AuthorizationService::class);
        $svc->createRole('A1', tenantId: 1);
        $svc->createRole('A2', tenantId: 2);

        $role1 = $svc->findRoleOrFail('A1', 1);
        $role2 = $svc->findRoleOrFail('A2', 2);
        $svc->grantToRole($role1, CustomerPolicy::class, only: ['view']);
        $svc->grantToRole($role2, CustomerPolicy::class, only: ['export']);

        $user = User::query()->create(['name' => 'U', 'email' => 'union@t.test']);
        \Illuminate\Support\Facades\DB::table('larc_user_roles')->insert([
            ['user_id' => $user->id, 'role_id' => $role1->id],
            ['user_id' => $user->id, 'role_id' => $role2->id],
        ]);

        expect(app(TenantResolver::class))->toBeInstanceOf(NullTenantResolver::class)
            ->and($user->hasAbility('customer.view'))->toBeTrue()
            ->and($user->hasAbility('customer.export'))->toBeTrue();
    });

    it('filters role abilities to TenantResolver::current when non-null', function () {
        $svc = app(AuthorizationService::class);
        $svc->createRole('A1', tenantId: 1);
        $svc->createRole('A2', tenantId: 2);

        $role1 = $svc->findRoleOrFail('A1', 1);
        $role2 = $svc->findRoleOrFail('A2', 2);
        $svc->grantToRole($role1, CustomerPolicy::class, only: ['view']);
        $svc->grantToRole($role2, CustomerPolicy::class, only: ['export']);

        $user = User::query()->create(['name' => 'U', 'email' => 'scoped@t.test']);
        \Illuminate\Support\Facades\DB::table('larc_user_roles')->insert([
            ['user_id' => $user->id, 'role_id' => $role1->id],
            ['user_id' => $user->id, 'role_id' => $role2->id],
        ]);

        app()->instance(TenantResolver::class, new class implements TenantResolver
        {
            public function current(): int|string|null
            {
                return 1;
            }
        });

        expect($user->hasAbility('customer.view'))->toBeTrue()
            ->and($user->hasAbility('customer.export'))->toBeFalse();
    });

    it('excludes NULL-tenant non-super roles when resolver is active; is_super still bypasses', function () {
        $svc = app(AuthorizationService::class);
        $svc->createRole('CompanyClerk', tenantId: 1);
        $svc->createRole('GlobalClerk', tenantId: null);
        $svc->createRole('PlatformBoss', tenantId: null, isSystem: true, isSuper: true);

        $company = $svc->findRoleOrFail('CompanyClerk', 1);
        $global = $svc->findRoleOrFail('GlobalClerk', null);
        $boss = $svc->findRoleOrFail('PlatformBoss', null);

        $svc->grantToRole($company, CustomerPolicy::class, only: ['view']);
        $svc->grantToRole($global, CustomerPolicy::class, only: ['export']);

        $staff = User::query()->create(['name' => 'Staff', 'email' => 'staff@t.test']);
        \Illuminate\Support\Facades\DB::table('larc_user_roles')->insert([
            ['user_id' => $staff->id, 'role_id' => $company->id],
            ['user_id' => $staff->id, 'role_id' => $global->id],
        ]);

        $owner = User::query()->create(['name' => 'Boss', 'email' => 'boss@t.test']);
        \Illuminate\Support\Facades\DB::table('larc_user_roles')->insert([
            ['user_id' => $owner->id, 'role_id' => $boss->id],
        ]);

        app()->instance(TenantResolver::class, new class implements TenantResolver
        {
            public function current(): int|string|null
            {
                return 1;
            }
        });

        expect($staff->hasAbility('customer.view'))->toBeTrue()
            ->and($staff->hasAbility('customer.export'))->toBeFalse()
            ->and($owner->isSuper())->toBeTrue()
            ->and($owner->hasAbility('customer.delete'))->toBeTrue();
    });
});

describe('Ability sync uuid', function () {
    it('assigns a non-null uuid on insertKeys', function () {
        $sync = app(AbilitySyncService::class);

        $inserted = $sync->insertKeys(['customer.view']);

        expect($inserted)->toBe(1);

        $ability = Ability::query()
            ->where('policy', 'customer')
            ->where('ability', 'view')
            ->firstOrFail();

        expect($ability->uuid)->not->toBeEmpty()
            ->and(strlen($ability->uuid))->toBe(36);
    });

    it('assigns uuid on insertNew for all discovered abilities', function () {
        $registry = app(ClassMapAbilityRegistry::class);
        $registry->refresh();

        app(AbilitySyncService::class)->insertNew();

        $missingUuid = Ability::query()->whereNull('uuid')->orWhere('uuid', '')->count();

        expect($missingUuid)->toBe(0);
    });
});

describe('laravarc:authorizer sync replace', function () {
    it('keeps pivot ids valid after replace', function () {
        $registry = app(ClassMapAbilityRegistry::class);
        $registry->refresh();
        app(AbilitySyncService::class)->insertNew();

        $ability = Ability::query()
            ->where('policy', 'customer')
            ->where('ability', 'export')
            ->firstOrFail();

        $originalId = $ability->id;

        $role = app(AuthorizationService::class)->createRole('Exporter');
        \Illuminate\Support\Facades\DB::table('larc_role_abilities')->insert([
            'role_id' => $role->id,
            'ability_id' => $originalId,
        ]);

        $user = User::query()->create(['name' => 'U', 'email' => 'u@t.test']);
        \Illuminate\Support\Facades\DB::table('larc_user_abilities')->insert([
            'user_id' => $user->id,
            'ability_id' => $originalId,
        ]);

        app(AbilitySyncService::class)->replaceMissing('customer.export', 'customer.renamed_export');

        $updated = Ability::query()->findOrFail($originalId);

        expect($updated->policy)->toBe('customer')
            ->and($updated->ability)->toBe('renamed_export')
            ->and(\Illuminate\Support\Facades\DB::table('larc_role_abilities')->where('ability_id', $originalId)->exists())->toBeTrue()
            ->and(\Illuminate\Support\Facades\DB::table('larc_user_abilities')->where('ability_id', $originalId)->exists())->toBeTrue();
    });
});

describe('Authorizer without Core', function () {
    it('does not require laravarc/core in composer dependencies', function () {
        $composer = json_decode(
            (string) file_get_contents(dirname(__DIR__, 2).'/composer.json'),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        $require = array_merge(
            $composer['require'] ?? [],
            $composer['require-dev'] ?? [],
        );

        expect(array_keys($require))->not->toContain('laravarc/core');
    });

    it('boots only AuthorizerServiceProvider in the test harness', function () {
        expect(app()->getProviders(AuthorizerServiceProvider::class))->not->toBeEmpty();
    });

    it('runs without Core package code present', function () {
        $coreProvider = implode('\\', ['Laravarc', 'Core', 'Providers', 'CoreServiceProvider']);

        expect(class_exists($coreProvider))->toBeFalse();
    });

    it('binds NullTenantResolver by default', function () {
        expect(app(TenantResolver::class))->toBeInstanceOf(NullTenantResolver::class);
    });
});
