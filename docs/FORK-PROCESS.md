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
- Continued: removed standalone dependencies on Wordfence core bridge logic (`TFA_LS_FROM_CORE`, `wfModuleController`, `wfUtils`, legacy paid-plugin checks).
- Continued: updated support URLs/onboarding wording and user-facing branding references away from Wordfence plugin messaging.
- Continued: refreshed `readme.txt` plugin metadata and descriptions to match this fork.

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

## Step 10 - Remove standalone-discontinuing persistent notice and dismiss mechanism

- Removed the unused `wfls-standalone-will-be-discontinued` persistent notice identifier, which referenced the original Wordfence standalone-plugin messaging and had no active caller in this fork.
- Removed the generic persistent-notice dismiss mechanism (`wfls-dismiss-{noticeId}` user meta key, `register_persistent_notice()`/`has_persistent_notices()`/`dismiss_persistent_notice()`, and the `dismiss_persistent_notice` AJAX action) since it had no remaining registered notices to serve.
- Removed the matching front-end dismiss handler for `.wfls-persistent-notice` in `admin-global.js` and rebuilt bundled assets.

## Step 11 - Remove unused assets, classes, and code paths. General cleanup.

- `1d6bc35` - Removed undocumented actions from the management and page views and simplified the related view state and navigation.
- `c393e9a` - Renamed the login translation object consistently in the controller and login scripts.
- `0bdb275` - Removed a redundant WooCommerce customer-role translation string from the JavaScript translation map.
- `63ac034` - Renamed `wordfence-ls` asset handles/classes to `2fa-ls` across the controller, styles, and page markup.
- `7ed0e4e` - Removed the unused `.wordfenceTopTab.active` CSS rule.
- `c066ded` - Removed additional unused admin CSS rules from both shipped and source stylesheets.
- `c3effac` - Renamed the primary controller file from `wordfencels.php` to `2fa-login-security.php` without changing its implementation.
- `8c7cf38` - Added distribution `export-ignore` rules, moved fork documentation into `docs/`, refreshed the translation catalog, and removed the CSS-pruning script from the repository.
- `8041c73` - Removed unused image assets, including legacy menu, header, loading, checkbox, and jQuery UI icon files.
- `c3d90a0` - Removed the unused Ionicons font asset.
- `c91c37f` - Corrected the translation object name used by the login scripts so it works consistently.
- `fe6cfe8` - Updated the main plugin controller file reference and removed the inherited copyright notice.
- `c4f0ec7` - Updated package names in docblocks and applied formatting improvements to the JWT, HTML, JavaScript, tab, and title model classes.
- `b66a3e7` - Removed the unused `Controller_Javascript` class, its registration, and the shared `wflsi18n.js` files.
- `7dfb4e8` - Updated the translation catalog creation date and corrected its source line references.
- `6815e2e` - Removed the remaining unused JavaScript controller file and its include from the main plugin file.
