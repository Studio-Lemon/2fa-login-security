<?php
/**
 * String value with its byte length.
 *
 * @package TFAuthLS
 */

namespace TFAuthLS;

/**
 * Stores a string and its byte length.
 */
class Utility_MeasuredString {



	/**
	 * Stored string value.
	 *
	 * @var string
	 */
	public $string;

	/**
	 * Length of the stored string in bytes.
	 *
	 * @var int
	 */
	public $length;

	/**
	 * Creates a measured string.
	 *
	 * @param string $value String value to store.
	 */
	public function __construct( $value ) {
		$this->string = $value;
		$this->length = strlen($value);
	}

	/**
	 * Returns the stored string value.
	 *
	 * @return string Stored string value.
	 */
	public function __toString(): string {
		return $this->string;
	}
}
