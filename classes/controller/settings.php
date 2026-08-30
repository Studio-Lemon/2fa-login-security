<?php

namespace TFAuthLS;

use TFAuthLS\Settings\Model_DB;
use TFAuthLS\Settings\Model_WPOptions;
use TFAuthLS\Utility_Number;

class Controller_Settings
{

	// Configurable
	const OPTION_IP_SOURCE                        = 'ip-source';
	const OPTION_IP_TRUSTED_PROXIES               = 'ip-trusted-proxies';
	const OPTION_REQUIRE_2FA_ADMIN                = 'require-2fa.administrator';
	const OPTION_REQUIRE_2FA_GRACE_PERIOD_ENABLED = 'require-2fa-grace-period-enabled';
	const OPTION_REQUIRE_2FA_GRACE_PERIOD         = 'require-2fa-grace-period';
	const OPTION_REQUIRE_2FA_USER_GRACE_PERIOD    = '2fa-user-grace-period';
	const OPTION_REMEMBER_DEVICE_ENABLED          = 'remember-device';
	const OPTION_REMEMBER_DEVICE_DURATION         = 'remember-device-duration';
	const OPTION_DELETE_ON_DEACTIVATION           = 'delete-deactivation';
	const OPTION_PREFIX_REQUIRED_2FA_ROLE         = 'required-2fa-role';
	const OPTION_ENABLE_LOGIN_HISTORY_COLUMNS     = 'enable-login-history-columns';

	// Internal
	const OPTION_GLOBAL_NOTICES                = 'global-notices';
	const OPTION_LAST_SECRET_REFRESH           = 'last-secret-refresh';
	const OPTION_USE_NTP                       = 'use-ntp';
	const OPTION_ALLOW_DISABLING_NTP           = 'allow-disabling-ntp';
	const OPTION_NTP_FAILURE_COUNT             = 'ntp-failure-count';
	const OPTION_NTP_OFFSET                    = 'ntp-offset';
	const OPTION_SHARED_HASH_SECRET_KEY        = 'shared-hash-secret';
	const OPTION_SHARED_SYMMETRIC_SECRET_KEY   = 'shared-symmetric-secret';
	const OPTION_DISMISSED_FRESH_INSTALL_MODAL = 'dismissed-fresh-install-modal';
	const OPTION_SCHEMA_VERSION                = 'schema-version';
	const OPTION_USER_COUNT_QUERY_STATE        = 'user-count-query-state';
	const OPTION_DISABLE_TEMPORARY_TABLES      = 'disable-temporary-tables';

	const DEFAULT_REQUIRE_2FA_USER_GRACE_PERIOD = 10;
	const MAX_REQUIRE_2FA_USER_GRACE_PERIOD     = 99;

	const STATE_2FA_DISABLED = 'disabled';
	const STATE_2FA_OPTIONAL = 'optional';
	const STATE_2FA_REQUIRED = 'required';

	protected $_settingsStorage;

	/**
	 * Returns the singleton Controller_Settings.
	 *
	 * @return Controller_Settings
	 */
	public static function shared()
	{
		static $_shared = null;
		if ($_shared === null) {
			$_shared = new Controller_Settings();
		}
		return $_shared;
	}

	public function __construct($settingsStorage = false)
	{
		if (! $settingsStorage) {
			$settingsStorage = new Model_DB();
		}
		$this->_settingsStorage = $settingsStorage;
		$this->_migrate_admin_2fa_requirements_to_roles();
	}

	/**
	 * Returns a key/value array of all defaults. The value is the storage-ready value (e.g., a JSON string for array
	 * settings).
	 */
	protected function _defaults(): array
	{
		return array(
			self::OPTION_IP_SOURCE                        => Model_Request::IP_SOURCE_AUTOMATIC,
			self::OPTION_IP_TRUSTED_PROXIES               => '',
			self::OPTION_REQUIRE_2FA_ADMIN                => false,
			self::OPTION_REQUIRE_2FA_GRACE_PERIOD_ENABLED => false,
			self::OPTION_REQUIRE_2FA_USER_GRACE_PERIOD    => self::DEFAULT_REQUIRE_2FA_USER_GRACE_PERIOD,
			self::OPTION_GLOBAL_NOTICES                   => '[]',
			self::OPTION_REMEMBER_DEVICE_ENABLED          => false,
			self::OPTION_REMEMBER_DEVICE_DURATION         => 30 * 86400,
			self::OPTION_LAST_SECRET_REFRESH              => 0,
			self::OPTION_DELETE_ON_DEACTIVATION           => false,
			self::OPTION_ENABLE_LOGIN_HISTORY_COLUMNS     => true,
			self::OPTION_SCHEMA_VERSION                   => false,
			self::OPTION_USER_COUNT_QUERY_STATE           => false,
			self::OPTION_DISABLE_TEMPORARY_TABLES         => false,
			self::OPTION_USE_NTP                          => true,
			self::OPTION_ALLOW_DISABLING_NTP              => false,
			self::OPTION_NTP_FAILURE_COUNT                => 0,
			self::OPTION_NTP_OFFSET                       => 0,
			self::OPTION_DISMISSED_FRESH_INSTALL_MODAL    => false,
		);
	}

	public function set_defaults(): void
	{
		$defaults = $this->_defaults();
		$defaults = array_column(
			array_map(
				function ($k, $v): array {
					return array(
						'k' => $k,
						'v' => array(
							'value'          => $v,
							'autoload'       => Model_Settings::AUTOLOAD_YES,
							'allowOverwrite' => false,
						),
					);
				},
				array_keys($defaults),
				array_values($defaults)
			),
			'v',
			'k'
		);
		$this->_settingsStorage->set_multiple($defaults);
	}

	public function set($key, $value, $already_validated = false)
	{
		return $this->set_multiple(array($key => $value), $already_validated);
	}

	public function set_multiple($changes, $already_validated = false): bool
	{
		if (! $already_validated && $this->validate_multiple($changes) !== true) {
			return false;
		}
		$changes = $this->clean_multiple($changes);
		$changes = $this->preprocess_multiple($changes);
		$this->_settingsStorage->set_multiple($changes);
		return true;
	}

	public function get($key, $default = false)
	{
		return $this->_settingsStorage->get($key, $default);
	}

	public function get_bool($key, $default = false)
	{
		return Utility_Number::truthyToBool($this->get($key, $default));
	}

	public function get_int($key, $default = 0): int
	{
		return intval($this->get($key, $default));
	}

	public function get_float($key, $default = 0.0): float
	{
		return (float) $this->get($key, $default);
	}

	public function get_array($key, $default = array())
	{
		$value = $this->get($key, null);
		$value = is_string($value) ? @json_decode($value, true) : null;
		return is_array($value) ? $value : $default;
	}

	public function remove($key): void
	{
		$this->_settingsStorage->remove($key);
	}

	public function all()
	{
		$result = $this->_settingsStorage->get_multiple($this->_defaults());
		foreach ($result as $key => &$value) {
			$value = $this->inflate($key, $value);
		}
		return $result;
	}

	/**
	 * Validates whether a user-entered setting value is acceptable. Returns true if valid or an error message if not.
	 *
	 * @param string $key
	 * @param mixed  $value
	 * @return bool|string
	 */
	public function validate($key, $value)
	{
		switch ($key) {
			// Boolean
			case self::OPTION_REQUIRE_2FA_ADMIN:
			case self::OPTION_REQUIRE_2FA_GRACE_PERIOD_ENABLED:
			case self::OPTION_REMEMBER_DEVICE_ENABLED:
			case self::OPTION_DISMISSED_FRESH_INSTALL_MODAL:
			case self::OPTION_DELETE_ON_DEACTIVATION:
			case self::OPTION_ENABLE_LOGIN_HISTORY_COLUMNS:
			case self::OPTION_USER_COUNT_QUERY_STATE:
			case self::OPTION_DISABLE_TEMPORARY_TABLES:
				return true;

				// Int
			case self::OPTION_LAST_SECRET_REFRESH:
				return is_numeric($value); // Left using is_numeric to prevent issues with existing values
			case self::OPTION_SCHEMA_VERSION:
				return Utility_Number::isInteger($value, 0);

				// Array
			case self::OPTION_GLOBAL_NOTICES:
				return is_array($value);

				// Special
			case self::OPTION_IP_TRUSTED_PROXIES:
				$value  = is_string($value) ? $value : '';
				$parsed = array_filter(
					array_map(
						function ($s): string {
							return trim($s);
						},
						preg_split('/[\r\n]/', $value)
					)
				);
				foreach ($parsed as $entry) {
					if (! Controller_Whitelist::shared()->is_valid_range($entry)) {
						return sprintf( /* translators: IP or range */__('The IP/range %s is invalid.', '2fa-login-security'), esc_html($entry));
					}
				}
				return true;
			case self::OPTION_IP_SOURCE:
				if (! in_array($value, array(Model_Request::IP_SOURCE_AUTOMATIC, Model_Request::IP_SOURCE_REMOTE_ADDR, Model_Request::IP_SOURCE_X_FORWARDED_FOR, Model_Request::IP_SOURCE_X_REAL_IP))) {
					return __('An invalid IP source was provided.', '2fa-login-security');
				}
				return true;
			case self::OPTION_REQUIRE_2FA_GRACE_PERIOD:
				$gracePeriodEnd = strtotime($value);
				if ($gracePeriodEnd <= \TFAuthLS\Controller_Time::time()) {
					return __('The grace period end time must be in the future.', '2fa-login-security');
				}
				return true;
			case self::OPTION_REMEMBER_DEVICE_DURATION:
				return is_numeric($value) && $value > 0;
			case self::OPTION_REQUIRE_2FA_USER_GRACE_PERIOD:
				if (! is_numeric($value) || $value < 0 || $value > self::MAX_REQUIRE_2FA_USER_GRACE_PERIOD) {
					return sprintf( /* translators: 1. Minimum number of days. 2. Maximum number of days. */__('The grace period day limit must be between %1$d and %2$d.', '2fa-login-security'), 0, self::MAX_REQUIRE_2FA_USER_GRACE_PERIOD);
				}
				return true;
		}
		return true;
	}

	public function validate_multiple($values)
	{
		$errors = array();
		foreach ($values as $key => $value) {
			$status = $this->validate($key, $value);
			if ($status !== true) {
				$errors[$key] = $status;
			}
		}

		if ($errors !== array()) {
			return $errors;
		}

		return true;
	}

	/**
	 * Cleans and normalizes a setting value for use in saving.
	 *
	 * @param string $key
	 * @param mixed  $value
	 * @return mixed
	 */
	public function clean($key, $value)
	{
		switch ($key) {
			// Boolean
			case self::OPTION_REQUIRE_2FA_ADMIN:
			case self::OPTION_REQUIRE_2FA_GRACE_PERIOD_ENABLED:
			case self::OPTION_REMEMBER_DEVICE_ENABLED:
			case self::OPTION_DISMISSED_FRESH_INSTALL_MODAL:
			case self::OPTION_DELETE_ON_DEACTIVATION:
			case self::OPTION_ENABLE_LOGIN_HISTORY_COLUMNS:
			case self::OPTION_USER_COUNT_QUERY_STATE:
			case self::OPTION_DISABLE_TEMPORARY_TABLES:
				return Utility_Number::truthyToBool($value);

				// Int
			case self::OPTION_REMEMBER_DEVICE_DURATION:
			case self::OPTION_LAST_SECRET_REFRESH:
			case self::OPTION_REQUIRE_2FA_USER_GRACE_PERIOD:
			case self::OPTION_SCHEMA_VERSION:
				return (int) $value;

				// Array
			case self::OPTION_GLOBAL_NOTICES:
				return json_encode($value);

				// Special
			case self::OPTION_IP_TRUSTED_PROXIES:
				$value   = is_string($value) ? $value : '';
				$parsed  = array_filter(
					array_map(
						function ($s): string {
							return trim($s);
						},
						preg_split('/[\r\n]/', $value)
					)
				);
				$cleaned = array();
				foreach ($parsed as $item) {
					$cleaned[] = $this->_sanitize_ip_range($item);
				}
				return implode("\n", $cleaned);
			case self::OPTION_REQUIRE_2FA_GRACE_PERIOD:
				$dt = $this->_parse_local_time($value);
				return $dt->format('U');
		}
		return $value;
	}

	/**
	 * Normalizes a setting value from its saved state into the desired type.
	 *
	 * @param string $key
	 * @param mixed  $value
	 * @return mixed
	 */
	public function inflate($key, $value)
	{
		switch ($key) {
			// Boolean
			case self::OPTION_REQUIRE_2FA_ADMIN:
			case self::OPTION_REQUIRE_2FA_GRACE_PERIOD_ENABLED:
			case self::OPTION_REMEMBER_DEVICE_ENABLED:
			case self::OPTION_DISMISSED_FRESH_INSTALL_MODAL:
			case self::OPTION_DELETE_ON_DEACTIVATION:
			case self::OPTION_ENABLE_LOGIN_HISTORY_COLUMNS:
			case self::OPTION_USER_COUNT_QUERY_STATE:
			case self::OPTION_DISABLE_TEMPORARY_TABLES:
				return Utility_Number::truthyToBool($value);

				// Int
			case self::OPTION_REMEMBER_DEVICE_DURATION:
			case self::OPTION_LAST_SECRET_REFRESH:
			case self::OPTION_REQUIRE_2FA_USER_GRACE_PERIOD:
			case self::OPTION_SCHEMA_VERSION:
				return (int) $value;

				// Array
			case self::OPTION_GLOBAL_NOTICES:
				return json_decode($value, true);

				// Special
			case self::OPTION_IP_TRUSTED_PROXIES:
				$value = is_string($value) ? $value : '';
				return implode(
					"\n",
					array_filter(
						array_map(
							function ($s): string {
								return trim($s);
							},
							preg_split('/[\r\n]/', $value)
						)
					)
				);
		}
		return $value;
	}

	/**
	 * @return mixed[]
	 */
	public function clean_multiple($changes): array
	{
		$cleaned = array();
		foreach ($changes as $key => $value) {
			$cleaned[$key] = $this->clean($key, $value);
		}
		return $cleaned;
	}

	private function get_required_2fa_role_key($role): string
	{
		return implode('.', array(self::OPTION_PREFIX_REQUIRED_2FA_ROLE, $role));
	}

	public function get_required_2fa_role_activation_time($role)
	{
		$time = $this->get_int($this->get_required_2fa_role_key($role), -1);
		if ($time < 0) {
			return false;
		}
		return $time;
	}

	public function get_user_2fa_grace_period()
	{
		return $this->get_int(self::OPTION_REQUIRE_2FA_USER_GRACE_PERIOD, self::DEFAULT_REQUIRE_2FA_USER_GRACE_PERIOD);
	}

	/**
	 * Preprocesses the value, returning true if it was saved here (e.g., saved 2fa enabled by assigning a role
	 * capability) or false if it is to be saved by the backing storage.
	 *
	 * @param string $key
	 * @param mixed  $value
	 * @param array  &$settings the array of settings to process, this function may append additional values from preprocessing
	 */
	public function preprocess($key, $value, array &$settings): bool
	{
		if (preg_match('/^enabled-roles\.(.+)$/', $key, $matches)) { // Enabled roles are stored as capabilities rather than in the settings storage
			$role = $matches[1];
			if ($role === 'super-admin') {
				$roleValid = true;
			} elseif (in_array($value, array(self::STATE_2FA_OPTIONAL, self::STATE_2FA_REQUIRED))) {
				$roleValid = Controller_Permissions::shared()->allow_2fa_self($role);
			} else {
				$roleValid = Controller_Permissions::shared()->disallow_2fa_self($role);
			}

			if (! in_array($value, array(self::STATE_2FA_OPTIONAL, self::STATE_2FA_REQUIRED))) {
				$value = self::STATE_2FA_DISABLED;
			}

			if ($roleValid) {
				$settings[$this->get_required_2fa_role_key($role)] = ($value === self::STATE_2FA_REQUIRED ? time() : -1);
			}

			/**
			 * Fires when 2FA availability/required on a role changes.
			 *
			 * @since 1.1.13
			 *
			 * @param string $role The name of the role.
			 * @param string $state The state of 2FA on the role.
			 */
			do_action('TFA_LS_changed_2fa_required', $role, $value);

			return true;
		}

		// Settings that will dispatch actions
		switch ($key) {
			case self::OPTION_IP_SOURCE:
				$before = $this->get($key);
				$after  = $value;

				if ($before != $after) {
					/**
					 * Fires when the IP source changes.
					 *
					 * @since 1.1.13
					 *
					 * @param string $before The previous value.
					 * @param string $after The new value.
					 */
					do_action('TFA_LS_changed_ip_source', $before, $after);
				}
				break;
			case self::OPTION_IP_TRUSTED_PROXIES:
				$before = $this->trusted_proxies();
				$after  = explode("\n", $value); // Already cleaned here so just re-split

				if (count($before) === count($after) && array_diff($before, $after) === array()) {
					/**
					 * Fires when the trusted proxy list changes.
					 *
					 * @since 1.1.13
					 *
					 * @param string[] $before The previous value.
					 * @param string[] $after The new value.
					 */
					do_action('TFA_LS_updated_trusted_proxies', $before, $after);
				}
				break;
			case self::OPTION_REQUIRE_2FA_USER_GRACE_PERIOD:
				$before = $this->get($key);
				$after  = $value;

				if ($before != $after) {
					/**
					 * Fires when the grace period changes.
					 *
					 * @since 1.1.13
					 *
					 * @param int $before The previous value.
					 * @param int $after The new value.
					 */
					do_action('TFA_LS_changed_grace_period', $before, $after);
				}
				break;
		}

		return false;
	}

	public function preprocess_multiple($changes)
	{
		$remaining = array();
		foreach ($changes as $key => $value) {
			if (! $this->preprocess($key, $value, $remaining)) {
				$remaining[$key] = $value;
			}
		}
		return $remaining;
	}

	/**
	 * Convenience
	 */
	/**
	 * Returns a cleaned array containing the trusted proxy entries.
	 */
	public function trusted_proxies(): array
	{
		return array_filter(
			array_map(
				function ($s): string {
					return trim($s);
				},
				preg_split('/[\r\n]/', $this->get(self::OPTION_IP_TRUSTED_PROXIES, ''))
			)
		);
	}

	public function get_ntp_failure_count()
	{
		return $this->get_int(self::OPTION_NTP_FAILURE_COUNT, 0);
	}

	public function reset_ntp_failure_count(): void
	{
		$this->set(self::OPTION_NTP_FAILURE_COUNT, 0);
	}

	public function increment_ntp_failure_count(): false|int|float
	{
		$count = $this->get_ntp_failure_count();
		if ($count < 0) {
			return false;
		}
		++$count;
		$this->set(self::OPTION_NTP_FAILURE_COUNT, $count);
		return $count;
	}

	public function is_ntp_disabled_via_constant(): bool
	{
		return defined('TFA_LS_DISABLE_NTP') && TFA_LS_DISABLE_NTP;
	}

	public function is_ntp_enabled($requireOffset = true)
	{
		if ($this->is_ntp_cron_disabled()) {
			return false;
		}
		if ($this->get_bool(self::OPTION_USE_NTP, true)) {
			if ($requireOffset) {
				$offset = $this->get(self::OPTION_NTP_OFFSET, null);
				return $offset !== null && abs((int) $offset) <= Controller_TOTP::TIME_WINDOW_LENGTH;
			}
			return true;
		}
		return false;
	}

	public function is_ntp_cron_disabled(&$failureCount = null): bool
	{
		if ($this->is_ntp_disabled_via_constant()) {
			return true;
		}
		$failureCount = $this->get_ntp_failure_count();
		if ($failureCount >= Controller_Time::FAILURE_LIMIT) {
			return true;
		}
		if ($failureCount < 0) {
			$failureCount = 0;
			return true;
		}
		return false;
	}

	public function disable_ntp_cron(): void
	{
		$this->set(self::OPTION_NTP_FAILURE_COUNT, -1);
	}

	public function are_login_history_columns_enabled()
	{
		return self::shared()->get_bool(self::OPTION_ENABLE_LOGIN_HISTORY_COLUMNS, true);
	}

	/**
	 * Utility
	 */
	/**
	 * Parses the given time string and returns its DateTime with the server's configured time zone.
	 *
	 * @param string $timestring
	 */
	protected function _parse_local_time($timestring): \DateTime
	{
		new \DateTimeZone('UTC');
		$tz = get_option('timezone_string');
		if (! empty($tz)) {
			$tz = new \DateTimeZone($tz);
			return new \DateTime($timestring, $tz);
		}
		get_option('gmt_offset');
		return new \DateTime($timestring);
	}

	/**
	 * Cleans a user-entered IP range of unnecessary characters and normalizes some glyphs.
	 *
	 * @param string $range
	 */
	protected function _sanitize_ip_range($range): string
	{
		$range = preg_replace('/\s/', '', $range); // Strip whitespace
		$range = preg_replace('/[\\x{2013}-\\x{2015}]/u', '-', $range); // Non-hyphen dashes to hyphen
		$range = strtolower($range);

		if (preg_match('/^\d+-\d+$/', $range)) { // v5 32 bit int style format
			list($start, $end) = explode('-', $range);
			$start             = long2ip((int) $start);
			$end               = long2ip((int) $end);
			$range             = "{$start}-{$end}";
		}

		return $range;
	}

	private function _migrate_admin_2fa_requirements_to_roles(): void
	{
		if (! $this->get_bool(self::OPTION_REQUIRE_2FA_ADMIN)) {
			return;
		}
		$time = time();
		if (is_multisite()) {
			$this->set($this->get_required_2fa_role_key('super-admin'), $time, true);
		} else {
			$roles = new \WP_Roles();
			foreach ($roles->roles as $key => $data) {
				$role = $roles->get_role($key);
				if (Controller_Permissions::shared()->can_role_manage_settings($role) && Controller_Permissions::shared()->allow_2fa_self($role->name)) {
					$this->set($this->get_required_2fa_role_key($role->name), $time, true);
				}
			}
		}
		$this->remove(self::OPTION_REQUIRE_2FA_ADMIN);
		$this->remove(self::OPTION_REQUIRE_2FA_GRACE_PERIOD);
		$this->remove(self::OPTION_REQUIRE_2FA_GRACE_PERIOD_ENABLED);
	}

	public function reset_ntp_disabled_flag(): void
	{
		$this->remove(self::OPTION_USE_NTP);
		$this->remove(self::OPTION_NTP_OFFSET);
		$this->remove(self::OPTION_NTP_FAILURE_COUNT);
	}
}
