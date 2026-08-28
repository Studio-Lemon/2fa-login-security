Legacy Plugin Database Inventory (pre-fork state)
I saved the full inventory to legacy-db-schema-inventory.md (repo memory) so it persists for when we build the migration path. Summary:

User meta (wp*usermeta, mostly wfls-\* / wfls*\* prefixed):

wfls_notices, wfls-grace-period-reset, wfls-grace-period-override, wfls-allow-grace-period, wfls-verification-tokens, wfls-captcha-scores, wfls-last-captcha-score, wfls-last-login, wfls-captcha-nonce, wfls-dismiss-{noticeId} (+ two known notice ids)
Options (wp_options): wordfence_ls_version, wfls_last_role_sync
Network options (multisite): wfls_last_role_change, wordfence_ls_version

Custom tables (via db.php):

wfls*2fa_secrets — id, user_id, secret (encrypted), recovery (encrypted), ctime, vtime, mode — the critical per-user TOTP data
wfls_settings — key/value store (30+ named settings, listed in the memory file), abstracted through Model_DB/Model_WPOptions
wfls_role_counts / wfls_role_counts_temporary — cached admin-UI counts, rebuildable, not needed for migration
Transients/locks: wfls_verify*{hash}, wfls*captcha*{hash}, wflsbucket:{id}, wfls\_<name>.lock — ephemeral, safe to skip.

Cron: wordfence_ls_role_sync_cron, wordfence_ls_ntp_cron

Capabilities: wf2fa_activate_2fa_self, wf2fa_activate_2fa_others, wf2fa_manage_settings

Migration priority noted in the memory file: wfls_2fa_secrets and wfls_settings are must-migrate; user meta grace-period/login fields are secondary; role-count caches and transients can be excluded entirely.

This is recorded as reference data, not an implementation plan — let me know if you'd like me to turn it into an actual migration plan (old key → new key mapping) once the new fork's schema/field names are decided.

---

## Old vs New key comparison (fork as of FORK-PROCESS.md step 9)

Headline finding: the fork kept the **same table names and the same `wfls_`/`wf2fa_` string keys** for everything it didn't remove. There is no renaming migration to write — the real work is (a) bridging the version marker so the new code doesn't treat an existing install as fresh, (b) re-registering cron hooks whose names did change, and (c) optionally cleaning up now-orphaned rows left behind by removed features.

### Tables — unchanged, no migration needed

| Old table                         | New table                         | Notes                                        |
| --------------------------------- | --------------------------------- | -------------------------------------------- |
| `wfls_2fa_secrets`                | `wfls_2fa_secrets`                | identical schema (classes/controller/db.php) |
| `wfls_settings`                   | `wfls_settings`                   | identical schema                             |
| `wfls_role_counts` / `_temporary` | `wfls_role_counts` / `_temporary` | identical schema, cache only                 |

### User meta — unchanged keys (data carries over automatically)

| Meta key                     | Old | New | Notes |
| ---------------------------- | --- | --- | ----- |
| `wfls_notices`               | ✅  | ✅  | same  |
| `wfls-grace-period-reset`    | ✅  | ✅  | same  |
| `wfls-grace-period-override` | ✅  | ✅  | same  |
| `wfls-allow-grace-period`    | ✅  | ✅  | same  |
| `wfls-verification-tokens`   | ✅  | ✅  | same  |
| `wfls-last-login`            | ✅  | ✅  | same  |

### User meta — removed in new fork (orphaned data if present, safe to delete)

| Old meta key                                       | Reason removed                                                                                                 |
| -------------------------------------------------- | -------------------------------------------------------------------------------------------------------------- |
| `wfls-captcha-scores`                              | reCAPTCHA feature removed (FORK-PROCESS Step 5)                                                                |
| `wfls-last-captcha-score`                          | reCAPTCHA feature removed                                                                                      |
| `wfls-captcha-nonce`                               | reCAPTCHA feature removed                                                                                      |
| `wfls-woocommerce-integration-notice` (dismiss id) | WooCommerce integration removed (Step 3)                                                                       |
| `wfls-dismiss-{noticeId}`                          | Generic persistent-notice dismiss mechanism removed (Step 10) — no notices remained that used it               |
| `wfls-standalone-will-be-discontinued` (notice id) | Standalone-discontinuing notice removed (Step 10) — leftover Wordfence-specific messaging, unused in this fork |

### Options / network options

| Old key                           | New key                                               | Notes                                                                                      |
| --------------------------------- | ----------------------------------------------------- | ------------------------------------------------------------------------------------------ |
| `wordfence_ls_version`            | `TFA_LS_version` (`Controller_TFAuthLS::VERSION_KEY`) | **renamed** — must seed new key on migration or the plugin will think it's a fresh install |
| `wfls_last_role_sync`             | `wfls_last_role_sync`                                 | unchanged                                                                                  |
| `wfls_last_role_change` (network) | `wfls_last_role_change`                               | unchanged                                                                                  |

### `wfls_settings` table keys — unchanged (carry over automatically)

`ip-source`, `ip-trusted-proxies`, `require-2fa.administrator`, `require-2fa-grace-period-enabled`, `require-2fa-grace-period`, `2fa-user-grace-period`, `remember-device`, `remember-device-duration`, `delete-deactivation`, `required-2fa-role.{role}`, `enable-login-history-columns`, `global-notices`, `last-secret-refresh`, `use-ntp`, `allow-disabling-ntp`, `ntp-failure-count`, `ntp-offset`, `shared-hash-secret`, `shared-symmetric-secret`, `dismissed-fresh-install-modal`, `schema-version`, `user-count-query-state`, `disable-temporary-tables`

### `wfls_settings` table keys — removed in new fork (orphaned rows, safe to delete)

`xmlrpc-enabled`, `whitelisted`, `allow-xml-rpc`, `enable-auth-captcha`, `recaptcha-test-mode`, `recaptcha-site-key`, `recaptcha-secret`, `recaptcha-threshold`, `enable-woocommerce-integration`, `enable-woocommerce-account-integration`, `enable-shortcode`, `stack-ui-columns`, `captcha-stats`

### Cron hooks — renamed (must re-register)

| Old hook                      | New hook                |
| ----------------------------- | ----------------------- |
| `wordfence_ls_role_sync_cron` | `TFA_LS_role_sync_cron` |
| `wordfence_ls_ntp_cron`       | `TFA_LS_ntp_cron`       |

### Capabilities — unchanged

`wf2fa_activate_2fa_self`, `wf2fa_activate_2fa_others`, `wf2fa_manage_settings`

### Schema version

Old plugin's schema version tracking used `schema-version` in `wfls_settings`; new fork's `Controller_DB::SCHEMA_VERSION` is `2`. Need to confirm the value already present from the old install won't accidentally satisfy `require_schema_version()` and skip needed re-install/upgrade logic.

---

## Migration plan

### Goal

Detect an existing install of the old (pre-fork) plugin's data on activation of the new plugin, and let a site/network admin trigger a one-time migration from an admin notice, since almost no field renaming is required — this is mostly a **version-key bridge + cron re-registration + orphaned-data cleanup**.

### Detection

A migration is needed when:

- `wfls_2fa_secrets` and/or `wfls_settings` tables exist (created by either version, so this alone doesn't distinguish old vs new), **and**
- the old version marker option `wordfence_ls_version` (or network option) exists, **and**
- the new version marker `TFA_LS_version` does NOT exist yet.

This combination reliably identifies "old plugin data present, new plugin never run its own install/migration yet."

### Components to add

1. **`Controller_Migration`** (new class, `classes/controller/migration.php`)
   - `needs_migration(): bool` — implements the detection logic above.
   - `run(): void` — performs the actual migration steps (idempotent, safe to re-run):
     - Copy `wordfence_ls_version` value into `TFA_LS_version` (or just set current plugin version).
     - Clear old cron hooks (`wordfence_ls_role_sync_cron`, `wordfence_ls_ntp_cron`) via `wp_clear_scheduled_hook()` and let the existing `_validate_role_sync_cron` / NTP init logic reschedule under the new hook names.
     - Delete orphaned `wfls_settings` rows for removed keys (list above) — optional cleanup, not required for correctness.
     - Delete orphaned usermeta keys for removed features (`wfls-captcha-scores`, `wfls-last-captcha-score`, `wfls-captcha-nonce`) across all users — optional cleanup.
     - Force `Controller_DB::shared()->require_schema_version(Controller_DB::SCHEMA_VERSION)` to ensure any new-fork-only schema changes are applied.
     - Record a `migration-completed` flag/timestamp in `wfls_settings` so the notice never shows again and `run()` won't repeat automatically.
   - On multisite, must operate network-wide (network options) plus per-blog cleanup (settings/usermeta are per-blog via the settings storage abstraction already handles that).

2. **Admin notice** (`classes/controller/notices.php` or a new hook in `Controller_TFAuthLS`)
   - Register on `admin_notices` (and `network_admin_notices` for multisite), gated by `current_user_can('manage_options')` (or `manage_network_options` on multisite) — i.e., admins only, matching the request.
   - Only rendered when `Controller_Migration::shared()->needs_migration()` is true and not dismissed.
   - Contains a short explanation + a "Migrate now" button (form/link) and a "Not now"/dismiss option. Note: the `wfls-dismiss-*` persistent-notice mechanism was removed in Step 10, so dismissal for this new notice should use a dedicated settings flag instead (e.g. a `migration-notice-dismissed` key in `wfls_settings`) rather than reintroducing the old per-notice-id user-meta pattern.

3. **Migration trigger endpoint**
   - Reuse the existing `admin_post_wfls_*` pattern already used for the settings form (Step 8 in FORK-PROCESS.md) — add `admin_post_wfls_run_migration` handler.
   - Verify nonce + `current_user_can('manage_options')` before calling `Controller_Migration::shared()->run()`.
   - Redirect back to the referring admin page with a success/error transient message.

4. **Safety**
   - Wrap `run()` in a check that it hasn't already completed (idempotency flag) to prevent duplicate cron clearing/rescheduling if the button is clicked twice or the page is reloaded mid-flight.
   - No destructive action on `wfls_2fa_secrets` — that table's schema/data is untouched, so user TOTP enrollment is preserved automatically without explicit migration code.

### Suggested implementation order

1. Add `Controller_Migration` with `needs_migration()` + `run()` (start with version-key bridge + cron re-registration only — the essential correctness fix).
2. Wire up admin notice gated to admins, shown only when migration is needed.
3. Add `admin_post_wfls_run_migration` handler + nonce-protected button in the notice.
4. Add optional orphaned-data cleanup (settings rows + usermeta keys) as a secondary, non-blocking pass inside `run()`.
5. Test by seeding a database with old-style keys (`wordfence_ls_version`, old cron hooks) and confirming the notice appears, the migration runs once, and the notice disappears afterward.

Let me know if you want me to start implementing step 1 (`Controller_Migration` class) now.
