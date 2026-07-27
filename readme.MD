# 2FA Login Security Fork

This file tracks implementation changes made while creating the public fork.

## Step 0 - Repository baseline
- Created nested Git repository in this plugin directory.
- Committed untouched upstream snapshot from Wordfence Login Security 1.1.16.

## Step 1 - Rename to 2FA Login Security
- Replaced remaining PHP translation text domain usages from `wordfence-login-security` to `2fa-login-security`.

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

## Step 4 - Remove shortcode functionality
- Removed shortcode constants and handlers from the primary controller.
- Removed embedded 2FA management render path used by shortcode and deleted the associated view file.
- Removed shortcode option handling from settings and removed shortcode support metadata key.

## Step 5 - Remove reCAPTCHA functionality
- Removed login and registration CAPTCHA enforcement from primary authentication/registration flows.
- Removed reCAPTCHA settings keys and related validation/cleaning/inflation logic.
- Removed reCAPTCHA score tracking/caching and users-table CAPTCHA column handling.
- Removed reCAPTCHA stats AJAX endpoint and removed the dedicated CAPTCHA controller file.
- Removed chart asset wiring and deleted bundled chart runtime file used for reCAPTCHA statistics.

## Step 6 - Remove IP allowlist bypass functionality
- Removed allowlist bypass checks from the authentication flow.
- Removed allowlist settings key and settings-side preprocessing/event handling.
- Removed allowlist status display from the manage page and removed related Wordfence core option index wiring.
- Kept shared IP range parsing helpers for trusted proxy handling where still needed.

## Step 7 - Remove Font Awesome dependency
- Removed Font Awesome stylesheet enqueue from the management asset list.
- Replaced conditional Wordfence/core Font Awesome icon class usage in views with plugin-local icon classes.
- Added lightweight CSS glyph fallbacks for the icon classes used by the plugin UI.
- Removed bundled Font Awesome CSS and webfont assets.

## Step 8 - Remove Vue dependency and use native admin UI
- Replaced the Vue-mounted settings page with a server-rendered native WordPress admin form.
- Added a dedicated authenticated form handler (`admin_post_wfls_save_settings`) to validate and persist settings updates.
- Removed Vue-specific script loading, import-map injection, script tag mutation hooks, and Vue mount points.
- Removed bundled Vue runtime and Vue settings app assets from the plugin package.

## Step 9 - Start source CSS/JS files
- Added `src/css/` and `src/js/` directories as stable source locations for front-end assets.
- Seeded source files from currently shipped versioned assets to establish an editable baseline.
- Added `src/README.md` documenting source-to-bundle mapping and current workflow state.
