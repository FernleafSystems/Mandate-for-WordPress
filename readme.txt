=== Mandate App Security ===
Contributors: paultgoodchild
Tags: application passwords, rest api, access control, security, capabilities
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.2
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Scoping AI access for WordPress by controlling what each Application Password is allowed to do.

== Description ==

Mandate App Security helps administrators control WordPress API access for external tools, REST API clients, automations, Model Context Protocol (MCP) servers, and AI agents that authenticate with Application Passwords instead of a user's normal login password.

The tradeoff is that an Application Password normally inherits the user's broad WordPress access. If the user can edit posts, upload files, manage options, or perform other privileged actions, an API client using that Application Password can usually attempt those actions too.

Mandate App Security adds a small least-privilege guardrail inside WordPress itself: per-password access control for Application Passwords.

Instead of treating every Application Password for a user as equally trusted, Mandate App Security lets an administrator control each password's authorisation by saving a capability allowlist.

An administrator can choose:

* a WordPress user
* one of that user's Application Passwords
* the capabilities that password should be allowed to use
* an optional expiration date for that password

When a request is authenticated with that Application Password, Mandate App Security checks the saved allowlist and removes capabilities that are not allowed for that password.

Mandate App Security never grants new permissions. It only narrows an Application Password to capabilities the selected user already receives from assigned roles. If the selected Application Password is past its saved expiration date, Mandate App Security removes all capabilities for that request. Normal browser and wp-admin sessions for the same user are not changed.

This is especially useful when WordPress is connected to lower-level API tooling, automation systems, MCP layers, or AI/agent workflows. Even if a tool calls the WordPress REST API or other WordPress API endpoints directly, WordPress capability checks still run, so the password itself becomes more constrained.

= Current scope =

This release focuses on the core safety mechanism:

* Tools admin page for selecting a user and Application Password
* user role summary and selected password summary
* role-derived capability allowlist
* grouped WordPress and Everything Else capability lists
* per-Application-Password scope storage
* optional per-Application-Password expiration dates
* primitive capability enforcement
* registered meta-capability enforcement
* automatic revocation of expired Application Passwords
* cleanup when an Application Password is deleted

It does not provide Application Password management screens, edit roles, define per-object permissions, provide route-specific REST allowlists, or scope multisite super-admin passwords. The only automatic Application Password deletion it performs is revocation of passwords that are past a saved Mandate App Security expiration date.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/mandate-app-security` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the Plugins screen in WordPress.
3. Open Tools > Mandate App Security to select an application password and save its allowed capabilities.

== Frequently Asked Questions ==

= Does this change the user's normal role capabilities? =

No. Scope enforcement only applies to requests authenticated by a scoped application password.

= What happens when no scope is saved for an application password? =

The application password keeps its normal WordPress behavior until an administrator saves a scope or expiration date for it.

= How do expiration dates work? =

Expiration dates use the site's calendar date. A password remains valid through the selected date, expires on the following day, and is then revoked by a daily WordPress cron task.

= Can this grant new permissions to an application password? =

No. Mandate App Security can only remove capabilities from an authenticated application-password request. It does not grant capabilities the selected user does not already receive from assigned roles.

= Does this replace careful roles and integration security? =

No. It is an extra layer for reducing the blast radius of broad Application Password access. You should still use appropriate user roles, secure integrations, and normal operational controls.

= Does this scope multisite super-admin passwords? =

No. Scopes for multisite super admins are not supported.

== Source Code ==

Mandate App Security is available at https://wpmandate.com.

The public development repository is available at https://github.com/FernleafSystems/Mandate-for-WordPress.

The distributed plugin includes built admin assets in `assets/dist`. To rebuild those assets from source, install the development dependencies with `composer install` and `npm ci`, run `npm run build`, and then run `composer build-zip` to create the release package.

== Changelog ==

= 0.2.0 =
* Added optional per-Application-Password expiration dates and daily revocation of expired passwords.
* Added saved-role snapshots, scope audit details, and role-change warnings.
* Added capability descriptions and admin tooltips for common WordPress capabilities.
* Moved scope storage into a versioned Mandate App Security options document.
* Improved the admin layout, selected password summary, and capability tab labels.
* Added PHPUnit unit tests, WordPress integration tests, package verification, and GitHub release package support.

= 0.1.0 =
* Initial release.
