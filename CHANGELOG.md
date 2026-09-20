# Changelog

All notable changes to `laravarc/authorizer` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- `larc_roles` and `larc_abilities` include a unique `uuid` column next to `id` in the default create migration.
- Existing 1.0.0 installs get `uuid` via an idempotent upgrade migration (skipped when the column already exists).

## [1.0.0] - 2026-09-05

### Added

- Gate::before RBAC with ability discovery from policy classes.
- Roles, tenant-scoped grants, and `hasAbility()` tenant filtering via `TenantResolver`.
- `laravarc.authorize.super` middleware for super-role routes.
- Artisan toolkit: `laravarc:authorizer {install|sync|cache}` (alias `larc:authorizer`).

[1.0.0]: https://github.com/laravarc/authorizer/releases/tag/v1.0.0
