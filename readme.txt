=== Mandate App Security ===
Contributors: paultgoodchild
Tags: application passwords, rest api, access control, security, capabilities
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.2
Stable tag: 0.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WordPress Application Passwords carry the full access of their user. Mandate App Security adds per-credential policies to limit what each one may do.

== Description ==

WordPress Application Passwords prove identity. They do not limit what an authenticated request can do. If the user behind a password is an admin, every tool that authenticates as that user has admin-level access — with no native way to narrow it.

Today, REST clients, automation platforms, AI agents, management tools, and MCP connectors all authenticate with Application Passwords. Any of them, if misconfigured or compromised, can do anything that user can do.

Mandate App Security adds the missing layer: a capability policy per Application Password. You define what each credential is allowed to do. Mandate App Security enforces it on every request. Normal wp-admin sessions and user roles are unaffected.

Instead of treating every Application Password as equally trusted, Mandate App Security lets administrators and password owners save a capability allowlist per password.

An administrator can choose:

* a WordPress user
* one of that user's Application Passwords
* the capabilities that password should be allowed to use
* an optional expiration date for that password
* whether the scope is locked so the password owner can view it but not edit it

Users can scope their own Application Passwords when WordPress allows Application Passwords for their account. Only administrators can edit another user's scope or lock a scope against owner edits.

When a request is authenticated with that Application Password, Mandate App Security checks the saved allowlist and removes capabilities that are not allowed for that password.

Mandate App Security never grants new permissions. It only narrows an Application Password to capabilities the selected user already receives from assigned roles. If the selected Application Password is past its saved expiration date, Mandate App Security removes all capabilities for that request. Normal browser and wp-admin sessions for the same user are not changed.

= Example scopes =

A reporting dashboard that only needs to read posts and media should never be able to edit settings or manage users. A content automation tool that publishes posts has no reason to access WooCommerce orders. An AI writing assistant does not need plugin management access.

With Mandate App Security, each of those tools gets a dedicated Application Password scoped to exactly what it needs. Nothing more.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/mandate-app-security` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the Plugins screen in WordPress.
3. Open Tools > Mandate App Security to select an application password and save its allowed capabilities.

== Frequently Asked Questions ==

= Does this create or manage Application Passwords? =

No. Mandate App Security scopes existing Application Passwords. You create and manage Application Passwords from the WordPress user profile screen.

= What integrations does this work with? =

Any tool that authenticates using a WordPress Application Password: REST API clients, automation platforms, AI agents, management tools, and MCP connectors. If it uses an Application Password to authenticate, Mandate App Security can scope its access.

= Does this change the user's normal role capabilities? =

No. Scope enforcement only applies to requests authenticated by a scoped application password.

= What happens when no scope is saved for an application password? =

The application password keeps its normal WordPress behavior until an administrator or the password owner saves a scope or expiration date for it.

= Can users scope their own application passwords? =

Yes. Users can scope their own Application Passwords when WordPress allows Application Passwords for their account, unless an administrator has locked that scope. Administrators can edit any user's scope.

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

The public development repository, release packages, and build documentation are at https://github.com/FernleafSystems/Mandate-for-WordPress.

== Changelog ==

= 0.5.0 =
* Improves WordPress.org compatibility for plugin storage, hooks, nonces, admin selectors, and runtime identifiers.
* Hardens admin request handling, profile shortcuts, nonce generation, and template rendering.
* Uses the new `mdpsc_options` storage key; earlier pre-0.5.0 internal option data is not migrated.

= 0.4.1 =
* Publishes only the WordPress.org ZIP and current GitHub updater ZIP for releases.
* Keeps GitHub release asset naming in tooling code instead of runtime plugin identity.
* Removes plugin header author metadata for WordPress.org package compatibility.

= 0.4.0 =
* Allows users to scope their own Application Passwords when WordPress allows Application Passwords for their account, unless an administrator locks the scope.
* Adds administrator locks that make selected scopes read-only for password owners.
* Adds a Restrict Scope shortcut to Application Password profile tables when the current user can manage that password.
* Adds source tabs, area/action grouping, section select/deselect controls, and read/write/delete badges to the capability editor.
* Splits selected password details from Mandate rule status in the admin summary.
* Improves admin page output hardening.

= 0.3.1 =
* Adds a Plugins page Settings link that opens the Mandate admin tool.
* Adds favicon and sitemap metadata for the static product site.
* Keeps legacy GitHub updater installs on built release ZIPs by publishing and verifying the legacy package asset.

= 0.3.0 =
* Capability descriptions and tooltips explain what each WordPress capability does.
* Cleaner admin layout: user, password, and scope summary shown as aligned columns.
* Expiration date editing moved into the password summary.
* Capability tabs relabelled: WordPress Capabilities and Third-Party Capabilities.

= 0.2.0 =
* Optional expiration dates per Application Password. Expired passwords are automatically revoked daily.
* Scope audit details: last saved date and the roles the scope was based on.
* Warning when current roles differ from roles at the time the scope was saved.

= 0.1.0 =
* Initial release: capability scoping per Application Password, role-derived allowlists, and enforcement on every API request.
