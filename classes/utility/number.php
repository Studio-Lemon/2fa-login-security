<?php
/**
 * Numeric value helpers.
 *
 * @package TFAuthLS
 */

namespace TFAuthLS;

/**
 * Provides numeric validation and conversion helpers.
 */
class Utility_Number {


	/**
	 * Determines whether a value is an integer within an optional range.
	 *
	 * @param mixed    $value Value to validate.
	 * @param int|null $min Minimum permitted value.
	 * @param int|null $max Maximum permitted value.
	 * @return bool Whether the value is a valid integer.
	 */
	public static function is_integer( $value, $min = null, $max = null ): bool {
		$options = array();
		if (null !== $min) {
			$options['min_range'] = $min;
		}
		if (null !== $max) {
			$options['max_range'] = $max;
		}
		return filter_var($value, FILTER_VALIDATE_INT, array( 'options' => $options )) !== false;
	}

	/**
	 * Determines whether a value is a Unix timestamp.
	 *
	 * @param mixed $value Value to validate.
	 * @return bool Whether the value is a Unix timestamp.
	 */
	public static function is_unix_timestamp( $value ) {
		return self::is_integer($value, 0);
	}

	/**
	 * Translates a value to a boolean, correctly interpreting various textual representations.
	 *
	 * @param mixed $value Value to convert.
	 * @return bool Boolean representation of the value.
	 */
	public static function truthy_to_bool( $value ) {
		if (true === $value || false === $value) {
			return $value;
		}

		if (is_null($value)) {
			return false;
		}

		if (is_numeric($value)) {
			return (bool) $value;
		}
		if (preg_match('/^(?:f(?:alse)?|no?|off)$/i', $value)) {
			return false;
		}

		if (preg_match('/^(?:t(?:rue)?|y(?:es)?|on)$/i', $value)) {
			return true;
		}

		return ! empty($value);
	}
}
