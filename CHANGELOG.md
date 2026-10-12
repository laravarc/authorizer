# Changelog

All notable changes to `laravarc/authorizer` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
