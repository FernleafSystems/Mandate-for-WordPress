# Application Password Scoper Testing

`TESTING.md` is the source of truth for this MVP's local verification commands.

## Setup

From a fresh checkout, generate Composer's autoload files before running tests or activating the plugin:

```powershell
composer dump-autoload
```

The plugin runtime and unit test bootstrap require `vendor/autoload.php`. `vendor/` remains ignored for this cleanup, so packaging or committing generated Composer files is a separate decision.

## Public Commands

| Goal | Command | Notes |
| --- | --- | --- |
| Unit tests | `composer test:unit` | Runs the no-dependency PHP unit runner in `tests/Unit`. |
| Default PHP test gate | `composer test` | Alias for `composer test:unit`. |
| Browser smoke | `composer test:browser -- --clean -- --workers=1` | Provisions a Docker WordPress site, activates the plugin, and runs Playwright UI/API checks. |

`phpunit-unit.xml` is included as lightweight scaffolding if PHPUnit is added later, but PHPUnit is not required for the MVP test gate.

Before browser tests, install the Node test dependency and browser:

```powershell
npm install --no-audit --no-fund
npx playwright install chromium
```

## Validation

Run these before reporting a completed implementation:

```powershell
composer validate
composer dump-autoload
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
composer test:unit
composer test:browser -- --clean -- --workers=1
```

## Browser Coverage

The browser lane follows the lightweight shape of Shield's Docker/Playwright tests without copying its full CLI stack. It seeds Docker's WordPress volume from the local WordPress reference checkout at `D:\Work\Dev\Libraries\wordpress`, which currently reports `7.1-alpha-62408`; this keeps the smoke test aligned with the plugin's literal `Requires at least: 7.0` header.

- `tests/docker/docker-compose.browser.yml` starts MySQL, WordPress, and WP-CLI.
- `tests/docker/provision-browser-site.sh` installs WordPress, activates the plugin, creates fixture roles/users/application passwords, and installs a test-only mu-plugin fixture endpoint.
- `tests/browser/scoper-admin.spec.js` verifies the Tools menu item, page load, user/password selection, role-derived capability display, save/reload behavior, and REST capability enforcement through WordPress filters.

## Manual Smoke Test

1. Activate the plugin.
2. Open Tools > Application Password Scoper.
3. Select a user with an application password.
4. Save a restricted allowlist.
5. Call a REST endpoint with that application password.
6. Confirm allowed actions work and disallowed actions fail.
7. Confirm the same user's normal admin session is unaffected.
