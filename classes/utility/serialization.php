<?php

/**
 * Serialization helpers.
 *
 * @package TFAuthLS
 */

// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize, WordPress.PHP.NoSilencedErrors.Discouraged -- This validated compatibility utility intentionally handles PHP-serialized legacy data and catches malformed input.

namespace TFAuthLS;

use RuntimeException;

/**
 * Provides validated PHP deserialization.
 */
class Utility_Serialization
{




	/**
	 * Deserializes validated PHP-serialized data.
	 *
	 * @param string        $data Serialized data.
	 * @param array         $options Options passed to unserialize().
	 * @param callable|null $validator Optional value validator.
	 * @return mixed Unserialized value.
	 * @throws RuntimeException When the data is invalid or fails validation.
	 */
	public static function unserialize($data, $options = array(), $validator = null)
	{
		static $serialized_false;
		if (null === $serialized_false) {
			$serialized_false = serialize(false);
		}
		if ($serialized_false === $data) {
			return false;
		}
		if (! is_serialized($data)) {
			throw new RuntimeException('Input data is not serialized');
		}
		$unserialized = version_compare(PHP_VERSION, '5.6', '<=') ? @unserialize($data) : @unserialize($data, $options);
		if (false === $unserialized) {
			throw new RuntimeException('Deserialization failed');
		}
		if (null !== $validator && ! $validator($unserialized)) {
			throw new RuntimeException('Validation of unserialized data failed');
		}
		return $unserialized;
	}
}
