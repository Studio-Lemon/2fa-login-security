<?php

/**
 * JSON Web Token model.
 *
 * @package LS2FA\Crypto
 */

// phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.PHP.NoSilencedErrors.Discouraged -- JWT's encoded wire format, legacy private property names, and guarded cryptographic calls are retained for compatibility.

namespace TFAuthLS\Crypto;

use TFAuthLS\Controller_Time;
use TFAuthLS\Model_Crypto;

/**
 * Class Model_JWT
 *
 * @package LS2FA\Crypto
 * @property array $payload
 * @property int $expiration
 */
class Model_JWT
{




	/**
	 * Decoded JWT payload.
	 *
	 * @var array
	 */
	private $_payload;

	/**
	 * JWT expiration timestamp.
	 *
	 * @var bool|int
	 */
	private $_expiration;

	/**
	 * Decodes and returns the payload of a JWT. This also validates the signature and expiration. Currently assumes HS256 JWTs.
	 *
	 * @param string $token Token to decode.
	 * @return Model_JWT|false The decoded JWT or false if the token is invalid or fails validation.
	 */
	public static function decode_jwt($token): false|\TFAuthLS\Crypto\Model_JWT
	{
		$components = explode('.', $token);
		if (3 !== count($components)) {
			return false;
		}

		$key            = Model_Crypto::shared_hash_secret();
		$body           = $components[0] . '.' . $components[1];
		$signature      = hash_hmac('sha256', $body, $key, true);
		$test_signature = self::base64url_decode($components[2]);
		if (! hash_equals($signature, $test_signature)) {
			return false;
		}

		$json       = self::base64url_decode($components[1]);
		$payload    = @json_decode($json, true);
		$expiration = false;
		if (! is_array($payload)) {
			return false;
		}
		if (isset($payload['_exp'])) {
			$expiration = $payload['_exp'];
			if ($payload['_exp'] < Controller_Time::time()) {
				return false;
			}
			unset($payload['_exp']);
		}

		return new self($payload, $expiration);
	}

	/**
	 * Model_JWT constructor.
	 *
	 * @param array    $payload JWT payload.
	 * @param bool|int $expiration JWT expiration timestamp.
	 */
	public function __construct($payload, $expiration = false)
	{
		$this->_payload    = $payload;
		$this->_expiration = $expiration;
	}

	/**
	 * Returns the encoded JSON Web Token.
	 *
	 * @return string Encoded JSON Web Token.
	 */
	public function __toString(): string
	{
		$payload = $this->_payload;
		if (false !== $this->_expiration) {
			$payload['_exp'] = $this->_expiration;
		}
		$key       = Model_Crypto::shared_hash_secret();
		$header    = '{"alg":"HS256","typ":"JWT"}';
		$body      = self::base64url_encode($header) . '.' . self::base64url_encode(wp_json_encode($payload));
		$signature = hash_hmac('sha256', $body, $key, true);
		return $body . '.' . self::base64url_encode($signature);
	}

	/**
	 * Determines whether a JWT property is available.
	 *
	 * @param string $key Property name.
	 * @return bool Whether the property is available.
	 * @throws \OutOfBoundsException When the property name is invalid.
	 */
	public function __isset(string $key)
	{
		switch ($key) {
			case 'payload':
			case 'expiration':
				return true;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not rendered output.
		throw new \OutOfBoundsException('Invalid key: ' . $key);
	}

	/**
	 * Gets a JWT property.
	 *
	 * @param string $key Property name.
	 * @return array|bool|int JWT property value.
	 * @throws \OutOfBoundsException When the property name is invalid.
	 */
	public function __get(string $key)
	{
		switch ($key) {
			case 'payload':
				return $this->_payload;
			case 'expiration':
				return $this->_expiration;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not rendered output.
		throw new \OutOfBoundsException('Invalid key: ' . $key);
	}

	/**
	 * Utility
	 */

	/**
	 * Base64URL-encodes the given payload. This is identical to base64_encode except it substitutes characters
	 * not safe for use in URLs.
	 *
	 * @param string $payload Payload to encode.
	 * @return string Base64URL-encoded payload.
	 */
	public static function base64url_encode($payload)
	{
		return self::base64url_convert_to(base64_encode($payload));
	}

	/**
	 * Converts Base64 text to its URL-safe representation.
	 *
	 * @param string $base64 Base64 text to convert.
	 * @return string URL-safe Base64 text.
	 */
	public static function base64url_convert_to($base64): string
	{
		$intermediate = rtrim($base64, '=');
		$intermediate = str_replace('+', '-', $intermediate);
		return str_replace('/', '_', $intermediate);
	}

	/**
	 * Base64URL-decodes the given payload. This is identical to base64_encode except it allows for the characters
	 * substituted by base64url_encode.
	 *
	 * @param string $payload Payload to decode.
	 * @return string Decoded payload.
	 */
	public static function base64url_decode($payload): string
	{
		return base64_decode(self::base64url_convert_from($payload));
	}

	/**
	 * Converts URL-safe Base64 text to its standard representation.
	 *
	 * @param string $base64url URL-safe Base64 text to convert.
	 * @return string Standard Base64 text.
	 */
	public static function base64url_convert_from($base64url): array|string
	{
		$intermediate = str_replace('_', '/', $base64url);
		return str_replace('-', '+', $intermediate);
	}
}
