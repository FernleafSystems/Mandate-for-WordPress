# Changelog

## 0.4.1 - 2026-05-27

Mandate App Security tightens release packaging metadata after the 0.4.0 release.

### Changed

- Release packaging now publishes only the WordPress.org ZIP and current GitHub updater ZIP.
- GitHub release asset naming now lives in tooling-only identity code instead of runtime plugin identity.

### Compatibility

- Plugin header author metadata was removed for WordPress.org package compatibility.

## 0.4.0 - 2026-05-27

Mandate App Security now supports owner-managed scopes, administrator locks, and a clearer capability editor.

### Added

- Password owners can scope their own Application Passwords when WordPress allows it.
- Administrators can lock a saved scope so the password owner can view it but not edit it.
- Application Password profile tables now include a direct Restrict Scope shortcut.
- The capability editor now groups capabilities by source, area, and action, with section-level select/deselect controls.

### Changed

- The selected password summary now separates password details from Mandate rules.

### Security and Maintenance

- Admin output filtering and WordPress.org compatibility were tightened.
- Existing scope records load as unlocked unless they explicitly contain an administrator lock.

## 0.3.1 - 2026-05-26

Mandate keeps existing GitHub-updater installs on built release packages, adds a direct Settings link from the WordPress Plugins page, and improves static site metadata.

### Added

- Plugins page Settings link for opening the Mandate admin tool.
- Static product site favicon and one-URL sitemap for the wpmandate.com homepage.

### Compatibility

- This release temporarily published and verified a legacy GitHub updater release asset alongside the renamed GitHub package for existing updater installs.

## 0.3.0 - 2026-05-26

Application Password scope editing is easier to understand, package output is verified more thoroughly, and the public plugin identity is aligned for distribution.

### Added

- Capability descriptions for common WordPress primitive and meta capabilities.
- Keyboard- and pointer-accessible admin tooltips for described capabilities.
- Dedicated WordPress integration test lane using wp-phpunit and a Docker database sidecar.
- Package verification tooling for WordPress.org and GitHub release ZIP variants.
- GitHub updater package variant for GitHub-hosted releases.
- Static site intro video and refreshed product copy.

### Changed

- Public plugin identity and package naming now use Mandate App Security.
- Expiration editing moved into the selected password summary.
- Admin selection layout now shows user, Application Password, and selected password info as aligned summary columns.
- Capability tabs were renamed to WordPress Capabilities and Third-Party Capabilities.
- Unit tests now run through PHPUnit instead of the previous custom runner.
- Documentation now covers expiration behavior, integration tests, and the fuller release test gate.

### Security

- Saved capabilities are still re-intersected with current role-derived capabilities before enforcement.
- Scope user/context mismatches still fail closed.

### Tooling

- Release tags build and verify WordPress.org and GitHub ZIPs in CI.
- Release workflow covers Composer, Node, caching, package verification, and release notes.
- Browser tests cover admin layout, tooltip behavior, responsive selection columns, and expiration UI behavior.
- `.npmrc` delays new npm package adoption with a five-day minimum release age.

### Compatibility

- WordPress.org packages exclude the GitHub updater; GitHub packages include the updater bootstrap and dependency.

## 0.2.0 - 2026-05-25

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

- Release tags build `mandate-app-security-{tag}.zip` in CI.
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
