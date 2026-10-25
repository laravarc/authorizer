# Changelog

All notable changes to `laravarc/authorizer` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [3.0.0] - 2026-10-25

### Added

- `larc_user_abilities.tenant_id` (nullable) and `effect` (`allow` / `deny`, required);
  unique on `(user_id, ability_id, tenant_id)`. `AuthorizationService::denyUser()`,
  `setUserAbilityEffect()`, and tenant-aware resolution in `HasAuthorizerAbilities`.
- `Support\Enums\UserAbilityEffect`.
- `Support\KeyIdentifier` (`fromClass`, `fromPath`) with unit tests; policy discovery uses
  the same convention as `laravarc/core`.
- Tests: `AuthorizerCommandTest` (core-authored cache CLI), legacy schema v0 rejection,
  `AbilitySyncService` diff against core fixture, mtime unchanged on skip-write.

### Changed

- **BREAKING:** Fresh install uses a single create migration (`2026_07_18_000001`) with
  `uuid` on `larc_abilities` / `larc_roles`; removed upgrade migration
  `2026_09_10_000001_add_uuid_to_authorizer_tables`.
- **BREAKING:** Policy keys match `laravarc/core` `KeyIdentifier::fromClass()` (basename
  keeps `Policy`, e.g. `app.policies.customer_policy`). Re-run `laravarc:authorizer cache`
  and update `larc_abilities.policy` / grants that reference dotted policy prefixes.
- Ability index cache path is locked to
  `bootstrap/cache/laravarc/authorizer-abilities.php` via `CachePath`; the
  `authorizer.cache_path` config key was removed.

## [2.0.0] - 2026-10-12

### Changed

- **BREAKING:** Ability keys are now the policy FQCN in dotted snake-case with the trailing
  `Policy` removed (e.g. `App\Modules\Admin\User\Policies\UserPolicy` →
  `app.modules.admin.user.policies.user`), replacing the previous short basename key
  (`user`). FQCNs are globally unique, so two policies sharing a basename — for example
  `Admin\User\UserPolicy` and `User\User\UserPolicy` — no longer collide.
  `AbilityIndexBuilder::abilityKeyFromPolicy()` returns this key, and the duplicate-key
  `AbilityIndexBuildException` throw has been removed.
- `larc_roles` and `larc_abilities` include a unique `uuid` column next to `id` in the default create migration.
- Existing 1.0.0 installs get `uuid` via an idempotent upgrade migration (skipped when the column already exists).

### Migration

- Re-run `laravarc:authorizer cache` and migrate existing `larc_abilities.policy` values to
  the new FQCN form. Update the `policy` column in place — role/user grants reference the
  ability `id`, so pivots stay valid.

## [1.0.0] - 2026-09-05

### Added

- Gate::before RBAC with ability discovery from policy classes.
- Roles, tenant-scoped grants, and `hasAbility()` tenant filtering via `TenantResolver`.
- `laravarc.authorize.super` middleware for super-role routes.
- Artisan toolkit: `laravarc:authorizer {install|sync|cache}` (alias `larc:authorizer`).

[2.0.0]: https://github.com/laravarc/authorizer/releases/tag/v2.0.0
[1.0.0]: https://github.com/laravarc/authorizer/releases/tag/v1.0.0
