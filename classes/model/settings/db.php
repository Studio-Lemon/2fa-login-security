<?php

/**
 * Database-backed settings model.
 *
 * @package TFAuthLS\Settings
 */

// phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore, Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound, Universal.Operators.StrictComparisons.LooseNotEqual, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Legacy method names and named parameters plus the settings cache's direct database persistence are retained for compatibility.

namespace TFAuthLS\Settings;

use TFAuthLS\Controller_DB;
use TFAuthLS\Model_Settings;

/**
 * Stores plugin settings in the database.
 */
class Model_DB extends Model_Settings
{

	const AUTOLOAD_NO  = 'no';
	const AUTOLOAD_YES = 'yes';

	/**
	 * Cached autoloaded settings.
	 *
	 * @var array
	 */
	private static array $_cache = array();

	/**
	 * Stores a setting value.
	 *
	 * @param string $key             Setting key.
	 * @param mixed  $value           Setting value.
	 * @param string $autoload        Whether WordPress should autoload the setting.
	 * @param bool   $allow_overwrite Whether existing values may be overwritten.
	 * @return void
	 */
	public function set($key, $value, $autoload = self::AUTOLOAD_YES, $allow_overwrite = true): void
	{
		global $wpdb;
		$table = Controller_DB::shared()->settings;
		if (! $allow_overwrite) {
			if ($this->has_cached($key)) {
				return;
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name is internal and the value is prepared.
			$row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE `name` = %s", $key), ARRAY_A);
			if (is_array($row)) {
				return;
			}
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name is internal and values are prepared.
		if (false !== $wpdb->query($wpdb->prepare("INSERT INTO `{$table}` (`name`, `value`, `autoload`) VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `autoload` = VALUES(`autoload`)", $key, $value, $autoload)) && self::AUTOLOAD_NO != $autoload) {
			$this->update_cached($key, $value);
			do_action('wfls_settings_set', $key, $value);
		}
	}

	/**
	 * Stores multiple setting values.
	 *
	 * @param array $values Values keyed by setting name.
	 * @return void
	 */
	public function set_multiple($values): void
	{
		foreach ($values as $key => $value) {
			if (is_array($value)) {
				$this->set($key, $value['value'], $value['autoload'], $value['allowOverwrite']);
			} else {
				$this->set($key, $value);
			}
		}
	}

	/**
	 * Gets a setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Value to return when absent.
	 * @return mixed Setting value.
	 */
	public function get($key, $default = false)
	{
		$results = $this->get_multiple(array($key => $default));
		return $results[$key];
	}

	/**
	 * Gets multiple setting values.
	 *
	 * @param array $keys_defaults Default values keyed by setting name.
	 * @return array Settings keyed by name.
	 */
	public function get_multiple($keys_defaults): array
	{
		global $wpdb;

		$result    = array();
		$remaining = array();
		foreach ($keys_defaults as $key => $default) {
			if ($this->has_cached($key)) {
				$result[$key] = $this->cached_value($key);
			} else {
				$remaining[$key] = $default;
			}
		}

		if (array() !== $remaining) {
			$sanitized_keys = esc_sql(array_keys($remaining));
			$keys_i_n_clause  = "'" . implode("','", $sanitized_keys) . "'";

			$table = Controller_DB::shared()->settings;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name is internal and IDs are escaped with esc_sql().
			$rows  = $wpdb->get_results("SELECT `name`, `value`, `autoload` FROM `{$table}` WHERE `name` IN ({$keys_i_n_clause})", ARRAY_A);
			foreach ($rows as $r) {
				$name  = $r['name'];
				$value = $r['value'];

				$result[$name] = $value;
				unset($remaining[$name]);
				if (self::AUTOLOAD_NO != $r['autoload']) {
					$this->update_cached($name, $value);
				}
			}
		}

		return array_merge($remaining, $result);
	}

	/**
	 * Removes a setting value.
	 *
	 * @param string $key Setting key.
	 * @return void
	 */
	public function remove($key): void
	{
		global $wpdb;
		$table = Controller_DB::shared()->settings;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name is internal and the value is prepared.
		$wpdb->query($wpdb->prepare("DELETE FROM `{$table}` WHERE `name` = %s", $key));
		$this->remove_cached($key);
	}

	/**
	 * Returns cached autoloaded settings.
	 *
	 * @return array Cached settings.
	 */
	private function cached()
	{
		global $wpdb;

		if (empty(self::$_cache)) {
			$table    = Controller_DB::shared()->settings;
			$suppress = $wpdb->suppress_errors();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name is internal.
			$raw      = $wpdb->get_results("SELECT `name`, `value` FROM `{$table}` WHERE `autoload` = 'yes'");
			$wpdb->suppress_errors($suppress);
			$settings = array();
			foreach ((array) $raw as $o) {
				$settings[$o->name] = $o->value;
			}

			self::$_cache = $settings;
		}

		return self::$_cache;
	}

	/**
	 * Updates a cached setting.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Setting value.
	 * @return void
	 */
	private function update_cached($key, $value): void
	{
		$settings         = $this->cached();
		$settings[$key] = $value;
		self::$_cache     = $settings;
	}

	/**
	 * Removes a cached setting.
	 *
	 * @param string $key Setting key.
	 * @return void
	 */
	private function remove_cached($key): void
	{
		$settings = $this->cached();
		if (isset($settings[$key])) {
			unset($settings[$key]);
			self::$_cache = $settings;
		}
	}

	/**
	 * Gets a cached or stored setting value.
	 *
	 * @param string $key Setting key.
	 * @return mixed Setting value.
	 */
	private function cached_value($key)
	{
		global $wpdb;

		$settings = $this->cached();
		if (isset($settings[$key])) {
			return $settings[$key];
		}

		$table = Controller_DB::shared()->settings;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name is internal and the value is prepared.
		$value = $wpdb->get_var($wpdb->prepare("SELECT `value` FROM `{$table}` WHERE name = %s", $key));
		if (null !== $value) {
			$settings[$key] = $value;
			self::$_cache     = $settings;
		}
		return $value;
	}

	/**
	 * Determines whether a setting is cached.
	 *
	 * @param string $key Setting key.
	 * @return bool Whether the setting is cached.
	 */
	private function has_cached($key): bool
	{
		$settings = $this->cached();
		return isset($settings[$key]);
	}
}
