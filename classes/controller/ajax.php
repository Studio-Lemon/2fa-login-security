<?php

/**
 * AJAX request handling for the 2FA Login Security plugin.
 *
 * @package TFAuthLS
 */

// phpcs:disable Generic.Formatting.MultipleStatementAlignment, Squiz.Commenting.ClassComment, Squiz.Commenting.FileComment, Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment, Universal.Operators.StrictComparisons.LooseEqual, Universal.Operators.StrictComparisons.LooseNotEqual, WordPress.PHP.YodaConditions, WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput, WordPress.WP.AlternativeFunctions.strip_tags_strip_tags, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.WP.I18n.NoHtmlWrappedStrings

namespace TFAuthLS;

use TFAuthLS\Crypto\Model_JWT;
use TFAuthLS\Crypto\Model_Symmetric;
use TFAuthLS\Utility_Number;

class Controller_AJAX
{



	const MAX_USERS_TO_NOTIFY = 100;

	protected $actions;

	/**
	 * Returns the singleton Controller_AJAX.
	 *
	 * @return Controller_AJAX
	 */
	public static function shared()
	{
		static $shared = null;
		if (null === $shared) {
			$shared = new self();
		}
		return $shared;
	}

	public function init(): void
	{
		$this->actions = array(
			'authenticate'                   => array(
				'handler'             => array($this, 'ajax_authenticate_callback'),
				'nopriv'              => true,
				'nonce'               => false,
				'permissions'         => array(), // Format is 'permission' => 'error message'
				'required_parameters' => array(),
			),
			'register_support'               => array(
				'handler'             => array($this, 'ajax_register_support_callback'),
				'nopriv'              => true,
				'nonce'               => false,
				'permissions'         => array(),
				'required_parameters' => array('wfls-message-nonce', 'wfls-message'),
			),
			'activate'                       => array(
				'handler'             => array($this, 'ajax_activate_callback'),
				'permissions'         => array(),
				'required_parameters' => array('nonce', 'secret', 'recovery', 'code', 'user'),
			),
			'deactivate'                     => array(
				'handler'             => array($this, 'ajax_deactivate_callback'),
				'permissions'         => array(),
				'required_parameters' => array('nonce', 'user'),
			),
			'regenerate'                     => array(
				'handler'             => array($this, 'ajax_regenerate_callback'),
				'permissions'         => array(),
				'required_parameters' => array('nonce', 'user'),
			),
			'save_options'                   => array(
				'handler'             => array($this, 'ajax_save_options_callback'),
				'permissions'         => array(
					Controller_Permissions::CAP_MANAGE_SETTINGS => function () {
						return __('You do not have permission to change options.', '2fa-login-security');
					},
				), // These are deliberately written as closures to be executed later so that WP doesn't load the translations too early, which can cause it not to pick up user-specific language settings
				'required_parameters' => array('nonce', 'changes'),
			),
			'send_grace_period_notification' => array(
				'handler'             => array($this, 'ajax_send_grace_period_notification_callback'),
				'permissions'         => array(
					Controller_Permissions::CAP_MANAGE_SETTINGS => function () {
						return __('You do not have permission to send notifications.', '2fa-login-security');
					},
				),
				'required_parameters' => array('nonce', 'role', 'url'),
			),
			'update_ip_preview'              => array(
				'handler'             => array($this, 'ajax_update_ip_preview_callback'),
				'permissions'         => array(
					Controller_Permissions::CAP_MANAGE_SETTINGS => function () {
						return __('You do not have permission to change options.', '2fa-login-security');
					},
				),
				'required_parameters' => array('nonce', 'ip_source', 'ip_source_trusted_proxies'),
			),
			'dismiss_notice'                 => array(
				'handler'             => array($this, 'ajax_dismiss_notice_callback'),
				'permissions'         => array(),
				'required_parameters' => array('nonce', 'id'),
			),
			'reset_2fa_grace_period'         => array(
				'handler'             => array($this, 'ajax_reset_2fa_grace_period_callback'),
				'permissions'         => array(
					Controller_Permissions::CAP_MANAGE_SETTINGS => function () {
						return __('You do not have permission to reset the 2FA grace period.', '2fa-login-security');
					},
				),
				'required_parameters' => array('nonce', 'user_id'),
			),
			'revoke_2fa_grace_period'        => array(
				'handler'             => array($this, 'ajax_revoke_2fa_grace_period_callback'),
				'permissions'         => array(
					Controller_Permissions::CAP_MANAGE_SETTINGS => function () {
						return __('You do not have permission to revoke the 2FA grace period.', '2fa-login-security');
					},
				),
				'required_parameters' => array('nonce', 'user_id'),
			),
			'reset_ntp_failure_count'        => array(
				'handler'             => array($this, 'ajax_reset_ntp_failure_count_callback'),
				'permissions'         => array(
					Controller_Permissions::CAP_MANAGE_SETTINGS => function () {
						return __('You do not have permission to reset the NTP failure count.', '2fa-login-security');
					},
				),
				'required_parameters' => array(),
			),
			'disable_ntp'                    => array(
				'handler'             => array($this, 'ajax_disable_ntp_callback'),
				'permissions'         => array(
					Controller_Permissions::CAP_MANAGE_SETTINGS => function () {
						return __('You do not have permission to disable NTP.', '2fa-login-security');
					},
				),
				'required_parameters' => array(),
			),
		);

		$this->init_actions();
	}

	public function init_actions(): void
	{
		foreach ($this->actions as $action => $parameters) {
			if (isset($parameters['nopriv']) && $parameters['nopriv']) {
				add_action('wp_ajax_nopriv_TFA_LS_' . $action, array($this, 'ajax_handler'));
			}
			add_action('wp_ajax_TFA_LS_' . $action, array($this, 'ajax_handler'));
		}
	}

	/**
	 * This is a convenience function for sending a JSON response and ensuring that execution stops after sending
	 * since wp_die() can be interrupted.
	 *
	 * @param $response
	 * @param int|null $status_code
	 */
	public static function send_json($response, $status_code = null): void
	{
		wp_send_json($response, $status_code);
		die();
	}

	public function ajax_handler(): void
	{
		$action = (isset($_POST['action']) && is_string($_POST['action']) && $_POST['action']) ? $_POST['action'] : $_GET['action'];
		if (preg_match('~TFA_LS_([a-zA-Z_0-9]+)$~', $action, $matches)) {
			$action = $matches[1];
			if (!isset($this->actions[$action])) {
				self::send_json(array('error' => esc_html__('An unknown action was provided.', '2fa-login-security')));
			}

			$parameters = $this->actions[$action];
			if (!empty($parameters['required_parameters'])) {
				foreach ($parameters['required_parameters'] as $k) {
					if (!isset($_POST[$k])) {
						self::send_json(array('error' => esc_html__('An expected parameter was not provided.', '2fa-login-security')));
					}
				}
			}

			if (!isset($parameters['nonce']) || $parameters['nonce']) {
				$nonce = (isset($_POST['nonce']) && is_string($_POST['nonce']) && $_POST['nonce']) ? $_POST['nonce'] : $_GET['nonce'];
				if (!is_string($nonce) || !wp_verify_nonce($nonce, 'wp-ajax')) {
					self::send_json(
						array(
							'error'        => esc_html__('Your browser sent an invalid security token. Please try reloading this page.', '2fa-login-security'),
							'tokenInvalid' => 1,
						)
					);
				}
			}

			if (!empty($parameters['permissions'])) {
				$user = wp_get_current_user();
				foreach ($parameters['permissions'] as $permission => $error) {
					if (!user_can($user, $permission)) {
						self::send_json(array('error' => $error()));
					}
				}
			}

			call_user_func($parameters['handler']);
		}
	}

	public function ajax_authenticate_callback(): void
	{
		$credential_keys = array(
			'log'      => 'pwd',
			'username' => 'password',
		);
		$username       = null;
		$password       = null;
		foreach ($credential_keys as $username_key => $password_key) {
			if (array_key_exists($username_key, $_POST) && array_key_exists($password_key, $_POST) && is_string($_POST[$username_key]) && is_string($_POST[$password_key])) {
				$username = $_POST[$username_key];
				$password = $_POST[$password_key];
				break;
			}
		}
		if (null === $username || '' === $username || '0' === $username || (null === $password || '' === $password || '0' === $password)) {
			self::send_json(
				array(
					'error' => wp_kses(
						sprintf(/* translators: Forgot password URL */__('<strong>ERROR</strong>: A username and password must be provided. <a href="%s" title="Password Lost and Found">Lost your password</a>?', '2fa-login-security'), wp_lostpassword_url()),
						array(
							'strong' => array(),
							'a'      => array(
								'href'  => array(),
								'title' => array(),
							),
						)
					),
				)
			);
		}

		do_action_ref_array('wp_authenticate', array(&$username, &$password));

		define('TFA_LS_AUTHENTICATION_CHECK', true); // Prevents our auth filter from recursing
		$user = wp_authenticate($username, $password);
		if ($user instanceof \WP_User) {
			if (!Controller_Users::shared()->has_2fa_active($user) || Controller_Users::shared()->has_remembered_2fa($user) || defined('TFA_LS_COMBINED_IS_VALID')) { // Not enabled for this user, has a valid remembered cookie, or has already provided a 2FA code via the password field pass the credentials on to the normal login flow
				self::send_json(array('login' => 1));
			}
			self::send_json(
				array(
					'login'               => 1,
					'two_factor_required' => true,
				)
			);
		} elseif (is_wp_error($user)) {
			$errors   = array();
			$messages = array();
			$reset    = false;
			foreach ($user->get_error_codes() as $code) {
				if ('invalid_username' === $code || 'invalid_email' === $code || 'incorrect_password' === $code || 'authentication_failed' === $code) {
					$errors[] = wp_kses(
						sprintf(/* translators: Forgot password URL */__('<strong>ERROR</strong>: The username or password you entered is incorrect. <a href="%s" title="Password Lost and Found">Lost your password</a>?', '2fa-login-security'), wp_lostpassword_url()),
						array(
							'strong' => array(),
							'a'      => array(
								'href'  => array(),
								'title' => array(),
							),
						)
					);
				} else {
					if ('wfls_twofactor_invalid' === $code) {
						$reset = true;
					}

					$severity = $user->get_error_data($code);
					foreach ($user->get_error_messages($code) as $error_message) {
						if ('message' === $severity) {
							$messages[] = $error_message;
						} else {
							$errors[] = $error_message;
						}
					}
				}
			}
			if ([] !== $errors) {
				$errors = implode('<br>', $errors);
				$errors = apply_filters('login_errors', $errors);
				self::send_json(
					array(
						'error' => $errors,
						'reset' => $reset,
					)
				);
			}
			if ([] !== $messages) {
				$messages = implode('<br>', $messages);
				$messages = apply_filters('login_errors', $messages);
				self::send_json(
					array(
						'message' => $messages,
						'reset'   => $reset,
					)
				);
			}
		}

		self::send_json(
			array(
				'error' => wp_kses(
					sprintf(/* translators: Forgot password URL */__('<strong>ERROR</strong>: The username or password you entered is incorrect. <a href="%s" title="Password Lost and Found">Lost your password</a>?', '2fa-login-security'), wp_lostpassword_url()),
					array(
						'strong' => array(),
						'a'      => array(
							'href'  => array(),
							'title' => array(),
						),
					)
				),
			)
		);
	}

	public function ajax_register_support_callback(): void
	{
		$email = null;
		if (array_key_exists('email', $_POST) && is_string($_POST['email'])) {
			$email = $_POST['email'];
		} elseif (array_key_exists('user_email', $_POST) && is_string($_POST['user_email'])) {
			$email = $_POST['user_email'];
		}
		if (
			$email === null ||
			!isset($_POST['wfls-message']) || !is_string($_POST['wfls-message']) ||
			!isset($_POST['wfls-message-nonce']) || !is_string($_POST['wfls-message-nonce'])
		) {
			self::send_json(array('error' => wp_kses(__('<strong>ERROR</strong>: Unable to send message. Please refresh the page and try again.', '2fa-login-security'), array('strong' => array()))));
		}

		$email = sanitize_email($email);
		$login = '';
		if (array_key_exists('user_login', $_POST) && is_string($_POST['user_login'])) {
			$login = sanitize_user($_POST['user_login']);
		}
		$message = strip_tags($_POST['wfls-message']);

		if ((isset($_POST['user_login']) && empty($login)) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || ($message === '' || $message === '0')) {
			self::send_json(array('error' => wp_kses(__('<strong>ERROR</strong>: Unable to send message. Please refresh the page and try again.', '2fa-login-security'), array('strong' => array()))));
		}

		$jwt = Model_JWT::decode_jwt($_POST['wfls-message-nonce']);
		if ($jwt && isset($jwt->payload['ip']) && isset($jwt->payload['score'])) {
			$decrypted_ip    = Model_Symmetric::decrypt($jwt->payload['ip']);
			$decrypted_score = Model_Symmetric::decrypt($jwt->payload['score']);
			if ($decrypted_ip === false || $decrypted_score === false || Model_IP::inet_pton($decrypted_ip) !== Model_IP::inet_pton(Model_Request::current()->get_ip())) { // JWT IP and the current request's IP don't match, refuse the message
				self::send_json(array('error' => wp_kses(__('<strong>ERROR</strong>: Unable to send message. Please refresh the page and try again.', '2fa-login-security'), array('strong' => array()))));
			}

			$identifier  = bin2hex(Model_IP::inet_pton($decrypted_ip));
			$token_bucket = new Model_TokenBucket('rate:' . $identifier, 2, 1 / (6 * Model_TokenBucket::HOUR)); // Maximum of two requests, refilling at a rate of one per six hours
			if (!$token_bucket->consume(1)) {
				self::send_json(array('error' => wp_kses(__('<strong>ERROR</strong>: Unable to send message. You have exceeded the maximum number of messages that may be sent at this time. Please try again later.', '2fa-login-security'), array('strong' => array()))));
			}

			$email   = array(
				'to'      => get_site_option('admin_email'),
				'subject' => __('Blocked User Registration Contact Form', '2fa-login-security'),
				'body'    => sprintf(/* translators: 1. IP address; 2. Username; 3. Email address; 4. Score; 5. Message */__("A visitor blocked from registration sent the following message.\n\n----------------------------------------\n\nIP: %1\$s\nUsername: %2\$s\nEmail: %3\$s\nreCAPTCHA Score: %4\$f\n\n----------------------------------------\n\n%5\$s", '2fa-login-security'), $decrypted_ip, $login, $email, $decrypted_score, $message),
				'headers' => '',
			);
			$success = wp_mail($email['to'], $email['subject'], $email['body'], $email['headers']);
			if ($success) {
				self::send_json(array('message' => wp_kses(__('<strong>MESSAGE SENT</strong>: Your message was sent to the site owner.', '2fa-login-security'), array('strong' => array()))));
			}

			self::send_json(array('error' => wp_kses(__('<strong>ERROR</strong>: An error occurred while sending the message. Please try again.', '2fa-login-security'), array('strong' => array()))));
		}

		self::send_json(array('error' => wp_kses(__('<strong>ERROR</strong>: Unable to send message. Please refresh the page and try again.', '2fa-login-security'), array('strong' => array()))));
	}

	public function ajax_activate_callback(): void
	{
		$user_id = (int) Utility_Array::array_get($_POST, 'user', 0);
		$user   = wp_get_current_user();
		if ($user->ID != $user_id) {
			if (!user_can($user, Controller_Permissions::CAP_ACTIVATE_2FA_OTHERS)) {
				self::send_json(array('error' => __('You do not have permission to activate the given user.', '2fa-login-security')));
			} else {
				$user = new \WP_User($user_id);
				if (!$user->exists()) {
					self::send_json(array('error' => __('The given user does not exist.', '2fa-login-security')));
				}
			}
		} elseif (!user_can($user, Controller_Permissions::CAP_ACTIVATE_2FA_SELF)) {
			self::send_json(array('error' => __('You do not have permission to activate 2FA.', '2fa-login-security')));
		}

		if (Controller_Users::shared()->has_2fa_active($user)) {
			self::send_json(array('error' => __('The given user already has two-factor authentication active.', '2fa-login-security')));
		}

		$matches = (isset($_POST['secret']) && isset($_POST['code']) && is_string($_POST['secret']) && is_string($_POST['code']) && Controller_TOTP::shared()->check_code($_POST['secret'], $_POST['code']));
		if ($matches === false) {
			self::send_json(array('error' => __('The code provided does not match the expected value. Please verify that the time on your authenticator device is correct and that this server\'s time is correct.', '2fa-login-security')));
		}

		Controller_TOTP::shared()->activate_2fa($user, $_POST['secret'], $_POST['recovery'], $matches);
		Controller_Notices::shared()->remove_notice(false, 'wfls-will-be-required', $user);
		self::send_json(
			array(
				'activated' => 1,
				'text'      => sprintf(/* translators: count */_n('%d unused recovery code remains. You may generate a new set by clicking below.', '%d unused recovery codes remain. You may generate a new set by clicking below.', count($_POST['recovery']), '2fa-login-security'), count($_POST['recovery'])),
			)
		);
	}

	public function ajax_deactivate_callback(): void
	{
		$user_id = (int) Utility_Array::array_get($_POST, 'user', 0);
		$user   = wp_get_current_user();
		if ($user->ID != $user_id) {
			if (!user_can($user, Controller_Permissions::CAP_ACTIVATE_2FA_OTHERS)) {
				self::send_json(array('error' => __('You do not have permission to deactivate the given user.', '2fa-login-security')));
			} else {
				$user = new \WP_User($user_id);
				if (!$user->exists()) {
					self::send_json(array('error' => __('The user does not exist.', '2fa-login-security')));
				}
			}
		} elseif (!user_can($user, Controller_Permissions::CAP_ACTIVATE_2FA_SELF)) {
			self::send_json(array('error' => __('You do not have permission to deactivate 2FA.', '2fa-login-security')));
		}

		if (!Controller_Users::shared()->has_2fa_active($user)) {
			self::send_json(array('error' => __('The user specified does not have two-factor authentication active.', '2fa-login-security')));
		}

		Controller_Users::shared()->deactivate_2fa($user);
		self::send_json(array('deactivated' => 1));
	}

	public function ajax_regenerate_callback(): void
	{
		$user_id = (int) Utility_Array::array_get($_POST, 'user', 0);
		$user   = wp_get_current_user();
		if ($user->ID != $user_id) {
			if (!user_can($user, Controller_Permissions::CAP_ACTIVATE_2FA_OTHERS)) {
				self::send_json(array('error' => __('You do not have permission to generate new recovery codes for the given user.', '2fa-login-security')));
			} else {
				$user = new \WP_User($user_id);
				if (!$user->exists()) {
					self::send_json(array('error' => __('The user does not exist.', '2fa-login-security')));
				}
			}
		} elseif (!user_can($user, Controller_Permissions::CAP_ACTIVATE_2FA_SELF)) {
			self::send_json(array('error' => __('You do not have permission to generate new recovery codes.', '2fa-login-security')));
		}

		if (!Controller_Users::shared()->has_2fa_active($user)) {
			self::send_json(array('error' => __('The user specified does not have two-factor authentication active.', '2fa-login-security')));
		}

		$codes = Controller_Users::shared()->regenerate_recovery_codes($user);
		self::send_json(
			array(
				'regenerated' => 1,
				'recovery'    => array_map(
					function ($r): string {
						return implode(' ', str_split(bin2hex($r), 4));
					},
					$codes
				),
				'text'        => sprintf(
					/* translators: count */
					_n(
						'%d unused recovery code remains. You may generate a new set by clicking below.',
						'%d unused recovery codes remain. You may generate a new set by clicking below.',
						count($codes),
						'2fa-login-security'
					),
					count($codes)
				),
			)
		);
	}

	public function ajax_save_options_callback(): void
	{
		if (!empty($_POST['changes']) && is_string($_POST['changes'])) {
			$changes = json_decode(stripslashes($_POST['changes']), true);
			if (is_array($changes)) {
				try {
					$errors = Controller_Settings::shared()->validate_multiple($changes);
					if ($errors !== true) {
						if (1 === count($errors)) {
							$e = array_shift($errors);
							self::send_json(array('error' => esc_html(sprintf(/* translators: Error message. */__('An error occurred while saving the configuration: %s', '2fa-login-security'), $e))));
						} elseif (count($errors) > 1) {
							$compound_message = array();
							foreach ($errors as $e) {
								$compound_message[] = esc_html($e);
							}
							self::send_json(
								array(
									'error' => wp_kses(
										sprintf(__('Errors occurred while saving the configuration: %s', '2fa-login-security'), '<ul><li>' . implode('</li><li>', $compound_message) . '</li></ul>'),
										array(
											'ul' => array(),
											'li' => array(),
										)
									),
									'html'  => true,
								)
							);
						}

						self::send_json(
							array(
								'error' => esc_html__('Errors occurred while saving the configuration.', '2fa-login-security'),
							)
						);
					}

					Controller_Settings::shared()->set_multiple($changes);

					$response = array('success' => true);
					self::send_json($response);
					return;
				} catch (\Exception $e) {
					self::send_json(
						array(
							'error' => $e->getMessage(),
						)
					);
				}
			}
		}

		self::send_json(
			array(
				'error' => esc_html__('No configuration changes were provided to save.', '2fa-login-security'),
			)
		);
	}

	public function ajax_send_grace_period_notification_callback(): void
	{
		$notify_all = isset($_POST['notify_all']) && Utility_Number::truthy_to_bool($_POST['notify_all']);
		$users     = Controller_Users::shared()->get_users_by_role($_POST['role'], $notify_all ? null : self::MAX_USERS_TO_NOTIFY + 1);
		$url       = $_POST['url'];
		if (!empty($url)) {
			$url = get_site_url(null, $url);
			if (filter_var($url, FILTER_VALIDATE_URL) === false) {
				self::send_json(array('error' => __('The specified URL is invalid.', '2fa-login-security')));
			}
		}
		$user_count = count($users);
		if (!$notify_all && $user_count > self::MAX_USERS_TO_NOTIFY) {
			self::send_json(
				array(
					'error'          => sprintf(/* translators: user count */__('More than %d users exist for the selected role. This notification is not designed to handle large groups of users. In such instances, using a different solution for notifying users of upcoming 2FA requirements is recommended.', '2fa-login-security'), self::MAX_USERS_TO_NOTIFY),
					'limit_exceeded' => true,
				)
			);
		}
		$sent   = 0;
		$failed = 0;
		foreach ($users as $user) {
			Controller_Users::shared()->requires_2fa($user, $in_grace_period, $required_at);
			if ($in_grace_period && !Controller_Users::shared()->has_2fa_active($user)) {
				$subject      = sprintf(/* translators: site url */__('2FA will soon be required on %s', '2fa-login-security'), home_url());
				$required_date = Controller_Time::format_site_datetime($required_at);
				if (empty($url)) {
					$user_url = (is_multisite() && is_super_admin($user->ID)) ? network_admin_url('admin.php?page=WFLS') : admin_url('admin.php?page=WFLS');
				} else {
					$user_url = $url;
				}

				$message = sprintf(
					/* translators: 1. Date; 2. Configuration URL */
					__('<html><body><p>You do not currently have two-factor authentication active on your account, which will be required beginning %1$s.</p><p><a href="%2$s">Configure 2FA</a></p></body></html>', '2fa-login-security'),
					$required_date,
					htmlentities($user_url)
				);

				if (wp_mail($user->user_email, $subject, $message, array('Content-Type: text/html'))) {
					++$sent;
				} else {
					++$failed;
				}
			}
		}

		if (0 === $user_count) {
			self::send_json(array('error' => __('No users currently exist with the selected role.', '2fa-login-security')));
		} elseif (0 === $sent && 0 === $failed) {
			self::send_json(array('confirmation' => __('All users with the selected role already have two-factor authentication activated or have been locked out.', '2fa-login-security')));
		} elseif ($sent > 0 && $failed > 0) {
			self::send_json(
				array(
					'confirmation' =>
					sprintf(/* translators: number of users */_n('A reminder to activate two-factor authentication was sent to %d user.', 'A reminder to activate two-factor authentication was sent to %d users.', $sent, '2fa-login-security'), $sent)
						. ' ' .
						sprintf(/* translators: number of users */_n('It failed sending to %d user. Failures typically occur because of a missing or invalid email address.', 'It failed sending to %d users. Failures typically occur because of a missing or invalid email address.', $failed, '2fa-login-security'), $failed),
				)
			);
		} elseif ($sent > 0) {
			self::send_json(array('confirmation' => sprintf(/* translators: number of users */_n('A reminder to activate two-factor authentication was sent to %d user.', 'A reminder to activate two-factor authentication was sent to %d users.', $sent, '2fa-login-security'), $sent)));
		}
		self::send_json(array('confirmation' => sprintf(/* translators: number of users */_n('A reminder to activate two-factor authentication failed sending to %d user.', 'A reminder to activate two-factor authentication failed sending to %d users.', $failed, '2fa-login-security'), $failed)));
	}

	public function ajax_update_ip_preview_callback(): void
	{
		$source      = $_POST['ip_source'];
		$raw_proxies = $_POST['ip_source_trusted_proxies'];
		if (!is_string($source) || !is_string($raw_proxies)) {
			die();
		}

		$valid   = array();
		$invalid = array();
		$test    = preg_split('/[\r\n,]+/', $raw_proxies);
		foreach ($test as $value) {
			if (strlen($value) > 0) {
				if (Model_IP::is_valid_ip($value) || Model_IP::is_valid_cidr_range($value)) {
					$valid[] = $value;
				} else {
					$invalid[] = $value;
				}
			}
		}
		$trusted_proxies = $valid;

		$preview = Model_Request::current()->detected_ip_preview($source, $trusted_proxies);
		$ip      = Model_Request::current()->ip_for_field($source, $trusted_proxies);
		self::send_json(
			array(
				'ip'      => $ip[0],
				'preview' => $preview,
			)
		);
	}

	public function ajax_dismiss_notice_callback(): void
	{
		Controller_Notices::shared()->remove_notice($_POST['id'], false, wp_get_current_user());
	}

	public function ajax_reset_2fa_grace_period_callback(): void
	{
		$user_id              = (int) $_POST['user_id'];
		$grace_period_override = array_key_exists('grace_period_override', $_POST) ? (int) $_POST['grace_period_override'] : null;
		$user                = get_userdata($user_id);
		if (false === $user) {
			self::send_json(array('error' => esc_html__('Invalid user specified', '2fa-login-security')));
		}
		if ($grace_period_override < 0 || $grace_period_override > Controller_Settings::MAX_REQUIRE_2FA_USER_GRACE_PERIOD) {
			self::send_json(array('error' => esc_html__('Invalid grace period override', '2fa-login-security')));
		}
		$grace_period_allowed = Controller_Users::shared()->get_grace_period_allowed_flag($user_id);
		if (!$grace_period_allowed) {
			Controller_Users::shared()->allow_grace_period($user_id);
		}
		if (!Controller_Users::shared()->reset_2fa_grace_period($user, $grace_period_override)) {
			self::send_json(array('error' => esc_html__('Failed to reset grace period', '2fa-login-security')));
		}
		self::send_json(array('success' => true));
	}

	public function ajax_revoke_2fa_grace_period_callback(): void
	{
		$user = get_userdata((int) $_POST['user_id']);
		if (false === $user) {
			self::send_json(array('error' => esc_html__('Invalid user specified', '2fa-login-security')));
		}
		Controller_Users::shared()->revoke_grace_period($user);
		self::send_json(array('success' => true));
	}

	public function ajax_reset_ntp_failure_count_callback(): void
	{
		Controller_Settings::shared()->reset_ntp_failure_count();
	}

	public function ajax_disable_ntp_callback(): void
	{
		Controller_Settings::shared()->disable_ntp_cron();
	}
}
