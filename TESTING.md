# Mandate Testing

`TESTING.md` is the source of truth for this plugin's local verification commands.

## Setup

From a fresh checkout, install Composer dependencies before running tests, build tooling, or activating the plugin:

```powershell
composer install
```

The plugin runtime, unit test bootstrap, and build tooling require `vendor/autoload.php`. `vendor/` remains ignored; `composer.lock` is committed so Composer tooling dependencies stay reproducible.

## Public Commands

| Goal | Command | Notes |
| --- | --- | --- |
| Admin assets | `npm run build` | Builds the committed Vite admin JS/CSS assets in `assets/dist`. |
| Build zip | `composer build-zip` | Builds assets, creates a production-shaped package, and writes `build/mandate-YYYYmmdd-HHMMSS.zip`. |
| Unit tests | `composer test:unit` | Runs the PHPUnit 11 unit suite in `tests/Unit`. |
| WordPress integration tests | `composer test:integration` | Starts a minimal MySQL Docker sidecar, prepares WordPress core, and runs `tests/Integration` through `wp-phpunit`. |
| WordPress test install | `composer test:integration:install` | Prepares the WordPress core checkout used by the integration bootstrap without running tests. |
| Default PHP test gate | `composer test` | Alias for `composer test:unit`. |
| Plugin Check | `composer test:plugin-check -- --clean` | Builds a production-shaped plugin package and runs WordPress.org Plugin Check through WP-CLI. Omit `-- --clean` for a warm repeat. |
| Browser smoke | `composer test:browser -- --clean -- --workers=1` | Provisions a Docker WordPress site, activates the plugin, and runs Playwright UI/API checks. |

The unit lane uses the latest PHPUnit line compatible with the Composer PHP 8.2 platform (`>=11.5 <12`). WordPress integration tests use the Composer-installed `wp-phpunit` library and `yoast/phpunit-polyfills`, with a small local bootstrap shim for WordPress' current PHPUnit 11 compatibility gaps.

`composer test:integration` accepts `--clean` before PHPUnit arguments to recreate the database sidecar, and passes PHPUnit arguments after `--`:

```powershell
composer test:integration -- --clean -- --filter MandateIntegrationTest
```

Set `WP_CORE_DIR` to use a specific WordPress checkout. If unset, the installer prefers the local reference checkout at `D:\Work\Dev\Libraries\wordpress` when present, then falls back to a downloaded copy under the system temp directory. The default integration database connection is `root:testpass@127.0.0.1:3312/wordpress_test_integration`; override it with `WPM_INTEGRATION_DB_NAME`, `WPM_INTEGRATION_DB_USER`, `WPM_INTEGRATION_DB_PASS`, and `WPM_INTEGRATION_DB_PORT`, or set `WPM_INTEGRATION_DB_HOST` directly.

Before browser tests, install the Node test dependency and browser:

```powershell
npm install --no-audit --no-fund
npm run build
npx playwright install chromium
```

## Validation

Run these before reporting a completed implementation:

```powershell
composer validate --no-check-publish
composer install
npm run build
composer build-zip
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
composer test:unit
composer test:integration
composer test:plugin-check -- --clean
composer test:browser -- --clean -- --workers=1
```

## Build Zip

`composer build-zip` follows the simple production packaging contract for this plugin:

- runs `npm ci --no-audit --no-fund` and `npm run build` in the source checkout
- stages the package under the system temp directory and removes it after the zip is built
- copies only `plugin.php`, `init.php`, `unsupported.php`, `readme.txt`, `LICENSE`, `src`, and `assets/dist`
- creates production Composer autoload files in the staged package with `composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader`
- removes the staged Composer lock file before zipping, while keeping the generated package `composer.json` alongside `vendor/`
- writes the zip under `build/` with the archive root folder `mandate/`

Use `composer build-zip -- --output=mandate-test.zip` to choose a filename inside `build/`. Output paths outside `build/` are rejected. Use `--keep-package` when you need to inspect the temp package directory after a build; the retained path is printed in the command output.

`composer test:plugin-check` installs the WordPress.org Plugin Check plugin in a disposable Docker WordPress site and fails on Plugin Check `ERROR` findings. `WARNING` findings are printed for convergence cleanup, but they do not fail the command.

## Plugin Check Coverage

The Plugin Check lane uses `tests/docker/docker-compose.plugin-check.yml`, which starts MySQL and a WP-CLI container only. It does not expose a WordPress web server or port. The runner seeds WordPress from the local reference checkout, provisions the site through WP-CLI, installs Plugin Check, activates this plugin, and runs `wp plugin check mandate --format=json`.

Plugin Check inspects a release-shaped package under the system temp directory, not the raw repo root. The temp package is mounted read-only into Docker, removed after the check finishes, and built with the same runtime package builder as `composer build-zip`. It skips the source asset rebuild and expects `assets/dist` to already exist. Run `composer install` and `npm run build` before the gate.

Use `composer test:plugin-check -- --keep-package` only when you need to inspect the temp package directory after a run; the retained path is printed in the command output.

The default Plugin Check version is pinned in `tests/plugin-check/run-plugin-check.php`. To test a future Plugin Check release locally:

```powershell
$env:WPM_PLUGIN_CHECK_VERSION = '1.10.0'
composer test:plugin-check -- --clean
Remove-Item Env:\WPM_PLUGIN_CHECK_VERSION
```

## Browser Coverage

The browser lane follows the lightweight shape of Shield's Docker/Playwright tests without copying its full CLI stack. It seeds Docker's WordPress volume from the local WordPress reference checkout at `D:\Work\Dev\Libraries\wordpress`, which currently reports `7.1-alpha-62408`; this keeps the smoke test aligned with the plugin's literal `Requires at least: 7.0` header.

- `tests/docker/docker-compose.browser.yml` starts MySQL, WordPress, and WP-CLI.
- `tests/docker/provision-browser-site.sh` installs WordPress, activates the plugin, creates fixture roles/users/application passwords, and installs a test-only mu-plugin fixture endpoint.
- `tests/browser/mandate-admin.spec.js` verifies the real wp-admin selection flow, user/password auto-reload behavior, grouped capability controls, save/reset behavior, and REST capability enforcement through WordPress filters.

## Manual Smoke Test

1. Activate the plugin.
2. Open Tools > Mandate.
3. Select a user with an application password.
4. Save a restricted allowlist.
5. Call a REST endpoint with that application password.
6. Confirm allowed actions work and disallowed actions fail.
7. Confirm the same user's normal admin session is unaffected.
