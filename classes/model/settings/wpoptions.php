<?php
/**
 * WordPress option storage helper.
 *
 * @package TFAuthLS\Settings
 */

namespace TFAuthLS\Settings;

use TFAuthLS\Model_Settings;

/**
 * Stores settings in WordPress options using a prefixed key scheme.
 */
class Model_WPOptions extends Model_Settings {


	/**
	 * Option key prefix.
	 *
	 * @var string
	 */
	protected $prefix;

	/**
	 * Creates a settings wrapper for a given option prefix.
	 *
	 * @param string $prefix Prefix for option keys.
	 */
	public function __construct($prefix = '')
	{
		$this->prefix = $prefix;
	}

	/**
	 * Normalizes a setting key to a WordPress-safe option key.
	 *
	 * @param string $key Setting key.
	 * @return string Normalized key.
	 */
	protected function translate_key($key): string
	{
		return strtolower(preg_replace('/[^a-z0-9]/i', '_', $key));
	}

	/**
	 * Stores a single setting.
	 *
	 * @param string $key Option key.
	 * @param mixed  $value Option value.
	 * @param bool   $autoload Whether to auto-load the option.
	 * @param bool   $allow_overwrite Whether overwriting is allowed.
	 * @return void
	 */
	public function set($key, $value, $autoload = self::AUTOLOAD_YES, $allow_overwrite = true): void
	{
		$key      = $this->translate_key($this->prefix . $key);
		$autoload = self::AUTOLOAD_NO !== $autoload;
		if (! $allow_overwrite) {
			if (is_multisite()) {
				add_network_option(null, $key, $value);
			} else {
				add_option($key, $value, '', $autoload);
			}
		} elseif (is_multisite()) {
			update_network_option(null, $key, $value);
		} else {
			update_option($key, $value, $autoload);
		}
	}

	/**
	 * Stores multiple settings.
	 *
	 * @param array $values Settings keyed by option name.
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
	 * Retrieves a setting.
	 *
	 * @param string $key Option key.
	 * @param mixed  $default_value Default value if option is not set.
	 * @return mixed
	 */
	public function get($key, $default_value = false)
	{
		$key = $this->translate_key($this->prefix . $key);
		if (is_multisite()) {
			return get_network_option(null, $key, $default_value);
		}
		return get_option($key, $default_value);
	}

	/**
	 * Retrieves multiple settings at once.
	 *
	 * @param array $keys_defaults Map of option keys to defaults.
	 * @return array
	 */
	public function get_multiple($keys_defaults): array
	{
		$results = array();
		foreach ($keys_defaults as $key => $default_value) {
			$results[ $key ] = $this->get($key, $default_value); // `get_options` exists in WP 6.4+ but can't use it at our supported version
		}
		return $results;
	}

	/**
	 * Removes a setting.
	 *
	 * @param string $key Option key.
	 * @return void
	 */
	public function remove($key): void
	{
		$key = $this->translate_key($this->prefix . $key);
		if (is_multisite()) {
			delete_network_option(null, $key);
		} else {
			delete_option($key);
		}
	}
}
