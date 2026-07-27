# 2FA Login Security

2FA Login Security is a standalone WordPress plugin focused on two-factor authentication. This repository started as a fork of Wordfence Login Security and is being pared down into a smaller plugin with fewer moving parts.

This README covers the plugin's purpose, installation, day-to-day usage, and local development workflow. The user-facing setup guide from [docs/how-to.md](docs/how-to.md) is also included here so the main README can stand on its own.

## Current Scope

The current codebase is centered on:

- TOTP-based two-factor authentication for WordPress users.
- Role-based 2FA activation and grace-period enforcement.
- Remember-device support.
- XML-RPC authentication controls.
- Multisite-aware settings and scheduling behavior.

The fork is moving away from earlier upstream integrations and bundled extras. In particular, this repository is being cleaned up to remove Wordfence-core coupling, WooCommerce integration, shortcode-specific flows, reCAPTCHA behavior, and other legacy paths that are not part of the core 2FA functionality.

## Requirements

- WordPress 6.0+
- PHP 8.1+
- A TOTP authenticator app such as Google Authenticator, FreeOTP, 1Password, Authy, Microsoft Authenticator, or another compatible app

## Installation

### Standard WordPress install

1. Upload the plugin to your site's `wp-content/plugins/` directory, or install it through the WordPress admin.
2. Activate the plugin in the WordPress Plugins screen.
3. Open the `Login Security` area in wp-admin.
4. Review the settings, configure any role requirements, and activate 2FA for the accounts that need it.

### Multisite install

1. Upload the plugin to the network's plugins directory.
2. Network activate it.
3. Super administrators can manage the plugin from Network Admin.
4. Users on individual sites can manage their own 2FA settings when their role and permissions allow it.

## Features

### Two-factor authentication

- Uses standard TOTP authenticator apps instead of SMS.
- Supports activation per user.
- Supports role-based enforcement.
- Includes one-time recovery codes.
- Supports remembering a trusted device for a limited period.

### XML-RPC controls

- Can require valid 2FA credentials for XML-RPC authentication.
- Can disable XML-RPC authentication entirely if your site does not need it.

### Admin and user management

- Grace-period notifications for roles that must enable 2FA.
- Admin-side controls to activate, deactivate, and regenerate recovery codes for users.
- Last-login and related user-management support in the plugin UI.

## How To

This section folds the user guide from [docs/how-to.md](docs/how-to.md) into the main README.

### What two-factor authentication means

Two-factor authentication adds a second proof of identity to the login process. In practice, that usually means you know your password and you also have access to an authenticator app on a phone, tablet, or another device. Both are required before access is granted.

This plugin uses Time-Based One-Time Passwords, or TOTP. Your authenticator app generates a short code that changes every 30 seconds. After entering your normal password, you enter the current code from the app.

### How to enable two-factor authentication

Before you begin, install an authenticator app if you do not already use one. Common options include:

- Google Authenticator
- FreeOTP Authenticator
- 1Password
- LastPass Authenticator
- Microsoft Authenticator
- Authy
- Any other app that supports TOTP

To enable 2FA:

1. Open the `Login Security` page in WordPress admin.
2. Open your authenticator app and create a new entry.
3. Scan the QR code shown on the page.
4. If you are on the same mobile device as the site, use the manual setup code shown below the QR code instead.
5. Download the recovery codes and store them somewhere safe.
6. Enter the six-digit code from your authenticator app.
7. Click `Activate`.

If this is your first 2FA setup on the site, test it in another browser or a private window before ending your current session.

### How to log in with two-factor authentication

The normal login flow is:

1. Enter your username and password.
2. Submit the login form.
3. Enter the six-digit code from your authenticator app when prompted.
4. Complete login.

If you use 2FA on multiple sites, make sure you are reading the code for the correct site entry in your app.

The plugin also supports the combined-password flow used by this codebase:

1. Enter your username.
2. Enter your password.
3. Immediately append the current TOTP code to the end of the password in the same field.
4. Submit the login form.

Example:

- Password: `w0rdf3nce#!`
- Current code: `233455`
- Combined entry: `w0rdf3nce#!233455`

### How to use recovery codes

Recovery codes are fallback login codes for when you lose access to your authenticator device or remove the stored account by mistake.

- Each code is single-use.
- Recovery codes are longer than the usual 6-digit TOTP code.
- You should save or print them when you first activate 2FA.

To log in with a recovery code:

1. Enter your username and password.
2. When prompted for a `2FA Code`, enter one recovery code.
3. Complete login.

Example recovery code:

- `5199 5c24 77dc 0ed7`

If you use most of your recovery codes or no longer trust the saved copy, generate a new set from the `Login Security` page. Generating a new set invalidates the old set.

### How to disable two-factor authentication

To disable 2FA on your own account:

1. Log in to WordPress.
2. Open the `Login Security` page.
3. Click `Deactivate`.

To disable 2FA for another user:

1. Open the `Users` screen in WordPress admin.
2. Find the user.
3. Click the `2FA` link below the username.
4. On the management screen for that user, click `Deactivate`.

## Development

### JavaScript and CSS assets

Source assets live in `src/` and build into plain filenames under `css/` and `js/`.

Available npm scripts:

- `npm run watch` runs webpack in watch mode.
- `npm run dev` runs a one-off development build.
- `npm run build` runs a production build.
- `npm run translate:make-pot` regenerates the POT file.
- `npm run translate:make-php` generates translation PHP files.

Asset cache busting is handled by the WordPress enqueue version parameter, not by hashed filenames.

### PHP tooling

Composer is used for development tools in this repository.

Available Composer scripts:

- `composer phpcs` runs PHP_CodeSniffer with the local ruleset.
- `composer phpcbf` runs the automatic fixer.
- `composer rector` runs Rector.
- `composer rector:dry-run` previews Rector changes.

### Repository notes

- The plugin bootstrap now loads PHP files directly with `require_once` in a fixed order instead of using the older autoloader.
- The codebase is still mid-cleanup in a few areas, so some docs and legacy internals are being updated incrementally.

## Project Structure

- `2fa-login-security.php`: plugin bootstrap
- `classes/controller/`: runtime controllers and integration logic
- `classes/model/`: domain models, settings storage, crypto, request helpers, and view helpers
- `classes/utility/`: lower-level helper classes
- `views/`: PHP-rendered admin and management templates
- `src/`: editable source assets
- `css/` and `js/`: built browser assets
- `docs/`: extra project documentation

## Documentation

- User guide: [docs/how-to.md](docs/how-to.md)
- WordPress.org-style plugin readme: [readme.txt](readme.txt)

## License

This repository is licensed under GPL-2.0-or-later.
