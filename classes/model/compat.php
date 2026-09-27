<?php
/**
 * Compatibility helpers.
 *
 * @package TFAuthLS
 */

namespace TFAuthLS;

/**
 * Provides compatibility helpers for older PHP environments.
 */
class Model_Compat {


	/**
	 * Converts a hexadecimal string to its binary representation.
	 *
	 * @param string $value Hexadecimal value to convert.
	 * @return false|string Binary value or false when the input is invalid.
	 */
	public static function hex2bin( $value ): false|string {
		// Polyfill for PHP < 5.4
		if (! is_string($value)) {
			return false;
		}
		if (1 === strlen($value) % 2) {
			return false;
		}
		return pack('H*', $value);
	}
}
