<?php

namespace TFAuthLS\Settings;

use TFAuthLS\Model_Settings;

class Model_WPOptions extends Model_Settings
{
	protected $_prefix;

	public function __construct($prefix = '')
	{
		$this->_prefix = $prefix;
	}

	protected function _translate_key($key): string
	{
		return strtolower(preg_replace('/[^a-z0-9]/i', '_', $key));
	}

	public function set($key, $value, $autoload = self::AUTOLOAD_YES, $allowOverwrite = true): void
	{
		$key = $this->_translate_key($this->_prefix . $key);
		if (!$allowOverwrite) {
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

	public function get($key, $default = false)
	{
		$key = $this->_translate_key($this->_prefix . $key);
		if (is_multisite()) {
      return get_network_option($key, $default);
  }
		return get_option($key, $default);
	}

	/**
  * @return mixed[]
  */
 public function get_multiple($keysDefaults): array
	{
		$results = array();
		foreach ($keysDefaults as $key => $default) {
			$results[$key] = $this->get($key, $default); //`get_options` exists in WP 6.4+ but can't use it at our supported version
		}
		return $results;
	}

	public function remove($key): void
	{
		$key = $this->_translate_key($this->_prefix . $key);
		if (is_multisite()) {
			delete_network_option(null, $key);
		} else {
			delete_option($key);
		}
	}
}
