=== Application Password Scoper ===
Contributors: fernleafsystems
Tags: application passwords, rest api, security, capabilities
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Restrict WordPress application passwords to a selected capability allowlist.

== Description ==

Application Password Scoper lets administrators save a capability allowlist for individual application passwords. Requests authenticated with a scoped application password are limited to the selected capabilities while the user's normal admin session remains unchanged.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/application-password-scoper` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the Plugins screen in WordPress.
3. Open Tools > Application Password Scoper to select an application password and save its allowed capabilities.

== Frequently Asked Questions ==

= Does this change the user's normal role capabilities? =

No. Scope enforcement only applies to requests authenticated by a scoped application password.

= What happens when no scope is saved for an application password? =

The application password keeps its normal WordPress behavior until an administrator saves a scope for it.

== Changelog ==

= 0.1.0 =
* Initial release.
