<?php

/**
 * Settings storage contract.
 *
 * @package TFAuthLS
 */

// phpcs:disable Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound -- The public storage contract retains its $default named parameter for compatibility.

namespace TFAuthLS;

/**
 * Defines the settings storage API.
 */
abstract class Model_Settings
{


	const AUTOLOAD_YES = 'yes';
	const AUTOLOAD_NO  = 'no';

	/**
	 * Sets $value to $key.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $value Value to store.
	 * @param string $autoload Whether or not the key/value pair should autoload in storages that do that.
	 * @param bool   $allow_overwrite If false, only sets the value if key does not already exist.
	 */
	abstract public function set($key, $value, $autoload = self::AUTOLOAD_YES, $allow_overwrite = true);
	/**
	 * Sets multiple values.
	 *
	 * @param array $values Values to store.
	 */
	abstract public function set_multiple($values);

	/**
	 * Gets one value.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed Stored value or default.
	 */
	abstract public function get($key, $default);

	/**
	 * Gets multiple values.
	 *
	 * @param array $keys_defaults Keys mapped to default values.
	 * @return array Stored values.
	 */
	abstract public function get_multiple($keys_defaults);

	/**
	 * Removes one value.
	 *
	 * @param string $key Setting key.
	 */
	abstract public function remove($key);
}
