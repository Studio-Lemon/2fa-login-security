<?php

/**
 * Two-factor authentication initialization data.
 *
 * @package TFAuthLS
 */

namespace TFAuthLS;

// phpcs:disable PEAR.NamingConventions.ValidClassName.Invalid -- Maintains the established model class name.

/**
 * Stores generated values used while initializing a user's 2FA credentials.
 */
class Model_2fainitialization_data
{



	/**
	 * User being initialized.
	 *
	 * @var \WP_User
	 */
	private $user;
	/**
	 * Raw TOTP secret.
	 *
	 * @var string
	 */
	private $raw_secret;
	/**
	 * Base32-encoded TOTP secret.
	 *
	 * @var string|null
	 */
	private $base32_secret;
	/**
	 * OTP URL.
	 *
	 * @var string|null
	 */
	private $otp_url;
	/**
	 * Recovery codes.
	 *
	 * @var string[]|null
	 */
	private $recovery_codes;

	/**
	 * Creates initialization data for a user.
	 *
	 * @param \WP_User $user User being initialized.
	 */
	public function __construct($user)
	{
		$this->user       = $user;
		$this->raw_secret = Model_Crypto::random_bytes(20);
	}

	/**
	 * Returns the user being initialized.
	 *
	 * @return \WP_User
	 */
	public function get_user()
	{
		return $this->user;
	}

	/**
	 * Returns the raw TOTP secret.
	 *
	 * @return string
	 */
	public function get_raw_secret()
	{
		return $this->raw_secret;
	}

	/**
	 * Returns the Base32-encoded TOTP secret.
	 *
	 * @return string
	 */
	public function get_base32_secret()
	{
		if (null === $this->base32_secret) {
			$this->base32_secret = Utility_BaseConversion::base32_encode($this->raw_secret);
		}
		return $this->base32_secret;
	}

	/**
	 * Generates the OTP URL for the user's authenticator application.
	 *
	 * @return string
	 */
	private function generate_otp_url(): string
	{
		return 'otpauth://totp/' . rawurlencode(preg_replace('~^https?://(?:www\.)?~i', '', home_url()) . ':' . $this->user->user_login) . '?secret=' . $this->get_base32_secret() . '&algorithm=SHA1&digits=6&period=30&issuer=' . rawurlencode(preg_replace('~^https?://(?:www\.)?~i', '', home_url()));
	}

	/**
	 * Returns the OTP URL for the user's authenticator application.
	 *
	 * @return string
	 */
	public function get_otp_url()
	{
		if (null === $this->otp_url) {
			$this->otp_url = $this->generate_otp_url();
		}
		return $this->otp_url;
	}

	/**
	 * Returns the user's generated recovery codes.
	 *
	 * @return string[]
	 */
	public function get_recovery_codes()
	{
		if (null === $this->recovery_codes) {
			$this->recovery_codes = Controller_Users::shared()->regenerate_recovery_codes();
		}
		return $this->recovery_codes;
	}
}
// phpcs:enable PEAR.NamingConventions.ValidClassName.Invalid
