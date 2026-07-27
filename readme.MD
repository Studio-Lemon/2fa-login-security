# 2FA Login Security Fork

This file tracks implementation changes made while creating the public fork.

## Step 0 - Repository baseline
- Created nested Git repository in this plugin directory.
- Committed untouched upstream snapshot from Wordfence Login Security 1.1.16.

## Step 1 - Rename to 2FA Login Security
- Renamed plugin bootstrap file from `wordfence-login-security.php` to `2fa-login-security.php`.
- Updated plugin metadata and standalone bootstrap identity constants/functions.
- Removed main Wordfence plugin runtime coupling from bootstrap loading flow.
- Updated plugin text domain loading call to `2fa-login-security`.
- Removed the standalone-discontinuation admin notice that instructed users to install the main Wordfence plugin.

## Ongoing
- Additional changes will be appended here as implementation continues.

## Step 2 - Initial feature deactivation pass
- Disabled WooCommerce hook initialization from main controller action setup.
- Disabled shortcode registration and shortcode prerequisite enqueue hook.
- Disabled reCAPTCHA script enqueue path in login flow.
- Removed IP allowlist bypass check from authentication flow.
- Hard-disabled captcha controller by forcing `Controller_CAPTCHA::enabled()` to return false.

## Step 3 - Remove WooCommerce integration code paths
- Removed WooCommerce endpoint and menu integration methods from the primary controller.
- Removed WooCommerce-specific login and registration handling branches from authentication flow.
- Removed WooCommerce persistent admin-notice identifier and related settings-save rewrite hook trigger.
- Stopped passing WooCommerce availability into the settings view state.
