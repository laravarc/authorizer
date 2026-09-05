# Laravarc Authorizer

RBAC for Laravel — roles, abilities, and `Gate::before()` checks.

Works in any Laravel 10–13 application, with or without `laravarc/core`.

## Design

- **Policy = business rule** — policy classes stay plain; they never call Authorizer.
- **Authorizer = RBAC** — ability checks run in `Gate::before()` *before* policy methods.
- **Source of truth is code** — abilities are discovered from policy classes (or Core metadata), never created ad-hoc via a public `Ability::create()` API.
- **Tenant-ready** — `tenant_id` on roles is a plain nullable id (no foreign key). Swap `TenantResolver` for multi-tenant apps.

## Installation

```bash
composer require laravarc/authorizer
php artisan vendor:publish --tag=authorizer-config
php artisan migrate
php artisan laravarc:authorizer install
php artisan laravarc:authorizer cache
php artisan laravarc:authorizer sync
```

Add the trait to your user model:

```php
use Laravarc\Authorizer\Concerns\HasAuthorizerAbilities;

class User extends Authenticatable
{
    use HasAuthorizerAbilities;
}
```

## Route middleware

| Alias | Purpose |
|-------|---------|
| `laravarc.authorize.super` | Require an authenticated user with an Authorizer super role (`isSuper()`) |

```php
Route::middleware(['auth:sanctum', 'laravarc.authorize.super'])->group(function (): void {
    // privileged routes
});
```

When using `laravarc/core`, ability-based route checks use Core's `laravarc.authorize` middleware (same `laravarc.authorize*` family).

## How authorization works

```
Gate::authorize('delete', $customer)
  → Gate::before (Authorizer)
       is_super?     → true  (bypass, policy skipped)
       known + deny? → false (403, policy skipped)
       known + allow → null  (continue)
       unknown?      → null  (continue)
  → CustomerPolicy::delete()   // business rule only
```

## Fluent API

```php
use Laravarc\Authorizer\Facades\Authorization;
use Laravarc\Authorizer\Facades\Role;
use App\Policies\CustomerPolicy;

Role::create('Accounting')->tenant($companyId)
    ->grant(CustomerPolicy::class)->only(['view', 'update']);

Role::create('Admin')->grant(CustomerPolicy::class); // all abilities

Authorization::role('Accounting')->grant(CustomerPolicy::class)->except(['delete']);
Authorization::user($user)->assignRole('Accounting');
Authorization::user($user)->allow(CustomerPolicy::class)->only(['export']);
```

### Grant expansion

`grant(Policy::class)` stores **expanded** rows in `larc_role_abilities` (one per ability), not a live `policy.*` rule.

When `laravarc:authorizer sync` later discovers a **new** method on that policy, existing roles that previously granted the policy do **not** automatically receive the new ability. Re-grant the policy (or grant the new method) after sync.

## Commands

| Command | Alias | Purpose |
|---------|-------|---------|
| `laravarc:authorizer cache` | `larc:authorizer cache` | Build ability index (PSR-4 policy scan) |
| `laravarc:authorizer cache --clear` | same | Clear cached index |
| `laravarc:authorizer sync` | `larc:authorizer sync` | Sync abilities to the database |
| `laravarc:authorizer sync --dry-run` | same | Preview only |
| `laravarc:authorizer sync --auto-delete-missing` | same | Delete missing abilities without prompts |
| `laravarc:authorizer install` | `larc:authorizer install` | Seed the system super role (`is_super` + `is_system`) |

## Multi-tenant

```php
use Laravarc\Authorizer\Contracts\TenantResolver;

$this->app->singleton(TenantResolver::class, CompanyTenantResolver::class);
```

`null` from `current()` means global (single-tenant) roles and leaves `hasAbility()` on the union-all path. When `current()` returns a non-null id, `hasAbility()` only counts role grants for roles with that `tenant_id` (`is_super` still bypasses; NULL-tenant non-super roles do not match). Role name uniqueness is enforced in the service layer (SQL `UNIQUE` alone is unsafe with `NULL` tenant_id).

## Core integration (optional)

```php
// config/laravarc.php
'extensions' => [
    \Laravarc\Core\Authorizer\AuthorizerCoreExtension::class,
],
```

Core rebinds `AbilityRegistry` to `MetadataAbilityRegistry`. Authorizer never imports Core.

## License

MIT — see [LICENSE](LICENSE).
