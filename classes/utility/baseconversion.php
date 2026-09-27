<?php
/**
 * Base-conversion helpers.
 *
 * @package TFAuthLS
 */

namespace TFAuthLS;

use TFAuthLS\Crypto\Model_Base2n;

/**
 * Provides Base32 conversion helpers.
 */
class Utility_BaseConversion {



	/**
	 * Returns the shared Base32 codec.
	 *
	 * @return Model_Base2n Base32 codec.
	 */
	public static function get_base32()
	{
		static $base32 = null;
		if (null === $base32) {
			$base32 = new Model_Base2n(5, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', false, true, true);
		}
		return $base32;
	}

	/**
	 * Encodes data with the Base32 codec.
	 *
	 * @param string $data Data to encode.
	 * @return string Base32-encoded data.
	 */
	public static function base32_encode($data)
	{
		$base32 = self::get_base32();
		return $base32->encode($data);
	}
}
