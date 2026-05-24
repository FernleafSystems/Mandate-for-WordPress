# Changelog

## 0.2.0 - Unreleased

Saved scopes are easier to audit, storage is versioned, and tagged releases build automatically.

### Added

- Versioned scope storage with schema, plugin version, and timestamps.
- Saved-role snapshots for Application Password scopes.
- Admin scope audit details: last saved, saved roles, current roles.
- Role-change warning when current roles differ from saved roles.
- Optional per-Application-Password expiration dates.
- Daily WordPress cron revocation for expired Application Passwords.

### Changed

- Scope persistence moved to a centralized Mandate options document.
- Unrestricted saves delete the stored scope.
- Expiration-only saves keep capabilities unrestricted until the saved date passes.
- Browser fixtures reset scopes through the options repository.

### Security

- Scopes only narrow access; they never grant capabilities.
- Expired Application Password requests lose all capabilities before cron revocation runs.
- Malformed or unsupported option documents are ignored.
- Deleted Application Passwords prune only matching scopes.

### Tooling

- Release tags build `mandate-{tag}.zip` in CI.
- Release workflow covers Composer, Node, caching, and release notes.
- Unit tests cover options, malformed storage, role snapshots, reset behavior, and deletion hooks.
- Unit, integration, and browser tests cover expiration storage, enforcement, UI persistence, and cron revocation.
- Test bootstrap supports WordPress-style actions.
- `.gitattributes` normalizes line endings.

### Compatibility

- Existing `mandate_scopes` records are not migrated.
- Legacy scopes without role snapshots still load.
- Unscoped Application Passwords keep normal WordPress behavior.

## 0.1.0 - 2026-05-22

First public release.

### Added

- Application Password capability scoping.
- Tools > Mandate admin page.
- Role-derived, grouped capability list.
- Per-password scope storage by UUID.
- Primitive and registered meta-capability enforcement.
- Scope cleanup when Application Passwords are deleted.
- Unsupported PHP and WordPress version handling.
- Built admin assets.
- WordPress.org `readme.txt`, GPL license, static product page.

### Security

- Scopes only narrow access; they never grant capabilities.
- Browser and wp-admin sessions are unchanged.
- Unscoped Application Passwords keep normal WordPress behavior.
- Save/reset requires admin permission, nonce checks, ownership validation, and sanitized input.
- Saved scopes are limited to role-derived capabilities.

### Tooling

- Composer autoloading and package tooling.
- Production zip build staged outside the repository.
- Local WordPress Plugin Check lane.
- Unit tests for scope, enforcement, admin, and tooling.
- Playwright smoke tests for admin and REST enforcement.
- Testing docs for unit, browser, Plugin Check, and manual smoke paths.

### Limitations

- No Application Password management.
- No role editing or new capabilities.
- No permission grants.
- No per-route REST API allowlists.
- No per-object permissions.
- No multisite super-admin password scoping.
