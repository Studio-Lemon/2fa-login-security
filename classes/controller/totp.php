<?php

/**
 * Time-based one-time password controller.
 *
 * @package TFAuthLS
 */

namespace TFAuthLS;

/**
 * Activates and validates TOTP credentials.
 */
class Controller_TOTP
{


	const TIME_WINDOW_LENGTH = 30;

	/**
	 * Returns the singleton Controller_TOTP.
	 *
	 * @return Controller_TOTP
	 */
	public static function shared()
	{
		static $_shared = null;
		if (null === $_shared) {
			$_shared = new Controller_TOTP();
		}
		return $_shared;
	}

	/**
	 * Initializes the TOTP controller.
	 */
	public function init() {}

	/**
	 * Activates a user with the given TOTP parameters.
	 *
	 * @param \WP_User $user User to activate.
	 * @param string   $secret The secret as a hex string.
	 * @param string[] $recovery An array of recovery codes as hex strings.
	 * @param bool|int $vtime The timestamp of the verification code or false to use the current timestamp.
	 */
	public function activate_2fa($user, $secret, $recovery, $vtime = false): void
	{
		if (false === $vtime) {
			$vtime = Controller_Time::time();
		}

		global $wpdb;
		$table = Controller_DB::shared()->secrets;
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated from a fixed plugin constant.
				"INSERT INTO `{$table}` (`user_id`, `secret`, `recovery`, `ctime`, `vtime`, `mode`) VALUES (%d, %s, %s, UNIX_TIMESTAMP(), %d, 'authenticator')",
				$user->ID,
				Model_Compat::hex2bin($secret),
				implode(
					'',
					array_map(
						function ($r) {
							return Model_Compat::hex2bin($r);
						},
						$recovery
					)
				),
				$vtime
			)
		);

		/**
		 * Fires when 2FA is enabled for a user.
		 *
		 * @since 1.1.13
		 *
		 * @param \WP_User $user The user.
		 */
		// phpcs:ignore WordPress.NamingConventions.ValidHookName.NotLowercase -- Preserves the published hook name.
		do_action('TFA_LS_2fa_activated', $user);
	}

	/**
	 * Validates the 2FA (or recovery) code for the given user. This will return `null` if the user does not have 2FA
	 * enabled. This check will mark the code as used, preventing its use again.
	 *
	 * @param \WP_User $user   User whose code is being validated.
	 * @param string   $code   TOTP or recovery code.
	 * @param bool     $update Whether to persist a successfully used code.
	 * @return bool|null Returns null if the user does not have 2FA enabled, false if the code is invalid, and true if valid.
	 */
	public function validate_2fa($user, $code, $update = true): ?bool
	{
		global $wpdb;
		$table  = Controller_DB::shared()->secrets;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated from a fixed plugin constant.
		$record = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE `user_id` = %d FOR UPDATE", $user->ID), ARRAY_A);
		if (! $record) {
			return null;
		}

		if (preg_match('/^(?:[a-f0-9]{4}\s*){4}$/i', $code)) {
			// Recovery code
			$code          = strtolower(preg_replace('/\s/i', '', $code));
			$recovery_codes = str_split(strtolower(bin2hex($record['recovery'])), 16);
			$index          = array_search($code, $recovery_codes, true);
			if (false !== $index) {
				if ($update) {
					unset($recovery_codes[$index]);
					$updated_recovery_codes = implode('', $recovery_codes);
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated from a fixed plugin constant.
					$wpdb->query($wpdb->prepare("UPDATE `{$table}` SET `recovery` = X%s WHERE `id` = %d", $updated_recovery_codes, $record['id']));
				}
				$wpdb->query('COMMIT');
				return true;
			}
		} elseif (preg_match('/^(?:[0-9]{3}\s*){2}$/i', $code)) {
			// TOTP code
			$code    = preg_replace('/\s/i', '', $code);
			$secret  = bin2hex($record['secret']);
			$matches = $this->check_code($secret, $code, (int) floor($record['vtime'] / self::TIME_WINDOW_LENGTH));
			if (false !== $matches) {
				if ($update) {
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated from a fixed plugin constant.
					$wpdb->query($wpdb->prepare("UPDATE `{$table}` SET `vtime` = %d WHERE `id` = %d", $matches, $record['id']));
				}
				$wpdb->query('COMMIT');
				return true;
			}
		}

		$wpdb->query('ROLLBACK');
		return false;
	}

	/**
	 * Checks whether or not the code is valid for the given secret. If it is, it returns the time window (as a timestamp)
	 * that matched. If no time windows are provided, it checks the current and one on each side.
	 *
	 * @param string     $secret The secret as a hex string.
	 * @param string     $code The code.
	 * @param null|int   $previous The last-used time window (as a timestamp).
	 * @param null|array $windows An array of time windows or null to use the default.
	 * @return int|float|false The time window if matches, otherwise false.
	 */
	public function check_code($secret, $code, $previous = null, $windows = null): int|float|false
	{
		$time_code = floor(Controller_Time::time() / self::TIME_WINDOW_LENGTH);

		if (null === $windows) {
			$windows    = array();
			$valid_range = array(-1, 1); // 90 second range for authenticator

			$low_range  = $valid_range[0];
			$high_range = $valid_range[1];
			for ($i = 0; $i >= $low_range; $i--) {
				$windows[] = $time_code + $i;
			}
			for ($i = 1; $i <= $high_range; $i++) {
				$windows[] = $time_code + $i;
			}
		}

		foreach ($windows as $w) {
			if (null !== $previous && $previous >= $w) {
				continue;
			}

			$expected_code = $this->_generate_totp($secret, dechex($w));
			if (hash_equals($expected_code, $code)) {
				return $w * self::TIME_WINDOW_LENGTH;
			}
		}

		return false;
	}

	/**
	 * Generates a TOTP value using the provided parameters.
	 *
	 * @param string $key    Key in hexadecimal format.
	 * @param string $time   Desired time code in hexadecimal format.
	 * @param int    $digits Number of digits.
	 * @return string The TOTP value.
	 */
	private function _generate_totp($key, string $time, $digits = 6): string
	{
		$time = Model_Compat::hex2bin(str_pad($time, 16, '0', STR_PAD_LEFT));
		$key  = Model_Compat::hex2bin($key);
		$hash = hash_hmac('sha1', $time, $key);

		$offset       = hexdec(substr($hash, -2)) & 0xf;
		$intermediate = (((hexdec(substr($hash, $offset * 2, 2)) & 0x7f) << 24) |
			((hexdec(substr($hash, ($offset + 1) * 2, 2)) & 0xff) << 16) |
			((hexdec(substr($hash, ($offset + 2) * 2, 2)) & 0xff) << 8) |
			((hexdec(substr($hash, ($offset + 3) * 2, 2)) & 0xff))
		);
		$otp          = $intermediate % pow(10, $digits);

		return str_pad("{$otp}", $digits, '0', STR_PAD_LEFT);
	}
}
