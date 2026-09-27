<?php

/**
 * Symmetric cryptography model.
 *
 * @package TFAuthLS
 */

// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.PHP.NoSilencedErrors.Discouraged -- Base64 is part of the encrypted payload format and OpenSSL failures are deliberately handled.

namespace TFAuthLS\Crypto;

use TFAuthLS\Model_Crypto;

/**
 * Encrypts and decrypts data with the shared symmetric key.
 */
abstract class Model_Symmetric
{

	/**
	 * Returns $data encrypted with the shared symmetric key or false if unable to do so.
	 *
	 * @param string $data Data to encrypt.
	 * @return bool|array
	 */
	public static function encrypt($data)
	{
		if (! Model_Crypto::has_required_crypto_functions()) {
			return false;
		}

		$symmetric_key = Model_Crypto::shared_symmetric_secret();
		$iv            = Model_Crypto::random_bytes(16);
		$encrypted     = @openssl_encrypt($data, 'aes-256-cbc', $symmetric_key, OPENSSL_RAW_DATA, $iv);
		if ($encrypted) {
			return array(
				'data' => base64_encode($encrypted),
				'iv'   => base64_encode($iv),
			);
		}
		return false;
	}

	/**
	 * Returns the decrypted value of a payload encrypted by Model_Symmetric::encrypt
	 *
	 * @param array $encrypted Encrypted payload to decrypt.
	 * @return bool|string
	 */
	public static function decrypt($encrypted)
	{
		if (! Model_Crypto::has_required_crypto_functions()) {
			return false;
		}

		if (! isset($encrypted['data']) || ! isset($encrypted['iv'])) {
			return false;
		}

		$symmetric_key = Model_Crypto::shared_symmetric_secret();
		$iv            = base64_decode($encrypted['iv']);
		$encrypted     = base64_decode($encrypted['data']);
		return @openssl_decrypt($encrypted, 'aes-256-cbc', $symmetric_key, OPENSSL_RAW_DATA, $iv);
	}
}
