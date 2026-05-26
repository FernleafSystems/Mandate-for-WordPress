# Mandate App Security

Mandate App Security is a lightweight WordPress plugin for scoping AI access for WordPress by controlling what a specific WordPress Application Password can do.

## The Problem

WordPress Application Passwords are useful because they let external tools, WordPress API clients, automations, Model Context Protocol (MCP) servers, and agents authenticate without using a user's normal login password.

The tradeoff is that an Application Password normally inherits the user's broad WordPress access. If the user can edit posts, upload files, manage options, or perform other privileged actions, then an API client using that Application Password can usually attempt those actions too.

That is convenient, but it is not always least privilege.

For example, you might want an automation to read content, draft posts, or perform one narrow workflow, but not delete content, upload media, change settings, or exercise every permission the user has in wp-admin.

WordPress core does not currently provide a built-in way to scope one Application Password differently from another.

## The Solution

Mandate App Security adds a small guardrail inside WordPress itself: per-password access control through a capability allowlist.

In WordPress, capabilities are the lower-level permissions behind roles, such as `read`, `edit_posts`, or `upload_files`.

An admin can choose:

- a WordPress user
- one of that user's Application Passwords
- the capabilities that password should be allowed to use
- an optional expiration date for that password
- whether the scope is locked so the password owner can view it but not edit it

Users can also narrow their own Application Passwords when WordPress allows Application Passwords for their account. Only administrators can edit another user's Application Password scope or lock a scope against owner edits.

When a request is authenticated with that Application Password, Mandate App Security checks the saved allowlist and removes capabilities that are not allowed for that password.

Mandate App Security never grants new permissions. It only allows you to narrow an Application Password to capabilities the selected user already receives from their assigned role or roles. If the selected Application Password is past its saved expiration date, Mandate App Security removes all capabilities for that request.

Normal browser and wp-admin sessions for the same user are not changed.

## Why This Matters

This is especially useful when WordPress is connected to lower-level API tooling, automation systems, MCP layers, or AI/agent workflows that need authorisation boundaries closer to WordPress itself.

Even if a tool bypasses a higher-level integration layer and calls the WordPress REST API directly, WordPress capability checks still run. Mandate App Security uses those checks as the enforcement point, so the password itself becomes more constrained.

It is not a replacement for careful user roles, secure integrations, or good operational controls. It is a practical extra layer for reducing the blast radius of broad Application Password access.

## How It Works

The plugin adds a page under Tools where administrators can select any user and Application Password. Non-administrators can use the same page for their own Application Passwords when WordPress allows Application Passwords for their account, unless an administrator has locked the selected scope.

The capability list shown on the page is built from the user's assigned roles. Directly assigned user capabilities and capabilities from unrelated roles are not offered as scope options.

Saved scopes are stored by Application Password UUID. No raw Application Password secret is displayed or stored.

If no scope or expiration has been saved for an Application Password, the password behaves normally. Resetting a scope returns that password to normal WordPress behavior.

Expiration dates are calendar dates in the site's timezone. A password remains valid through the selected date and expires on the following day. Mandate App Security also runs a daily WordPress cron task that revokes expired Application Passwords through WordPress core.

## Current Scope

This release focuses on the core safety mechanism:

- Tools admin page for selecting a user and Application Password
- user role summary and selected password summary
- role-derived capability allowlist
- grouped WordPress and Everything Else capability lists
- per-Application-Password scope storage
- user self-scoping with administrator locks
- optional per-Application-Password expiration dates
- primitive capability enforcement
- registered meta-capability enforcement
- automatic revocation of expired Application Passwords
- cleanup when an Application Password is deleted
- lightweight unit and browser smoke test coverage

It does not provide Application Password management screens, edit roles, define per-object permissions, provide route-specific REST allowlists, or scope multisite super-admin passwords. The only automatic Application Password deletion it performs is revocation of passwords that are past a saved Mandate App Security expiration date.

## Development Notes

Runtime dependencies are intentionally minimal. Composer is used for PSR-4 autoloading, but no third-party runtime packages are required.

From a fresh checkout, generate Composer autoload files before running tests or activating the plugin:

```powershell
composer dump-autoload
```

See `TESTING.md` for validation commands and manual smoke test notes.

Product site: https://wpmandate.com
