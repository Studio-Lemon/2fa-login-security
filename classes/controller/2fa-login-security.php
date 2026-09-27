<?php

/**
 * Two-factor authentication controller.
 *
 * @package TFAuthLS
 */

// phpcs:disable Squiz.Commenting.FileComment, Squiz.Commenting.ClassComment, Squiz.Commenting.VariableComment, Squiz.Commenting.FunctionComment, Generic.Commenting.DocComment, WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- Legacy controller API and variable names are retained for compatibility.

namespace TFAuthLS;

use TFAuthLS\Text\Model_HTML;
use TFAuthLS\Utility_URL;
use TFAuthLS\View\Model_Tab;
use TFAuthLS\View\Model_Title;

class Controller_TFAuthLS
{
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Remaining request reads are sanitized routing or login-hook values; state changes verify their nonce in the target handler.

	const VERSION_KEY                        = 'TFA_LS_version';
	const USERS_PER_PAGE                     = 25;
	private bool $management_assets_enqueued = false;

	/**
	 * Returns the singleton Controller_TFAuthLS.
	 *
	 * @return Controller_TFAuthLS
	 */
	public static function shared()
	{
		static $_shared = null;
		if (null === $_shared) {
			$_shared = new Controller_TFAuthLS();
		}
		return $_shared;
	}

	public function init(): void
	{
		$this->init_actions();
		Controller_AJAX::shared()->init();
		Controller_Users::shared()->init();
		Controller_Time::shared()->init();
		Controller_Permissions::shared()->init();
	}

	protected function init_actions()
	{
		register_activation_hook(TFA_LS_FCPATH, $this->install_plugin(...));
		register_deactivation_hook(TFA_LS_FCPATH, $this->uninstall_plugin(...));

		$versionInOptions = ((is_multisite() && function_exists('get_network_option')) ? get_network_option(null, self::VERSION_KEY, false) : get_option(self::VERSION_KEY, false));
		if (! $versionInOptions || version_compare(TFA_LS_VERSION, $versionInOptions, '>')) { // Either there is no version in options or the version in options is greater and we need to run the upgrade
			$this->install();
		}

		add_action('login_enqueue_scripts', $this->login_enqueue_scripts(...));
		add_filter('authenticate', $this->authenticate(...), 25, 3);
		add_action('set_logged_in_cookie', $this->set_logged_in_cookie(...), 25, 4);
		add_action('wp_login', $this->record_login(...), 999, 1);
		add_action('register_post', $this->register_post(...), 25, 3);
		add_filter('wp_login_errors', $this->wp_login_errors(...), 25, 2);
		add_action('user_new_form', $this->user_new_form(...));
		add_action('user_register', $this->user_register(...));

		add_action('admin_menu', $this->admin_menu(...), 10);
		if (is_multisite()) {
			add_action('network_admin_menu', $this->admin_menu(...), 10);
		}
		add_action('admin_enqueue_scripts', $this->admin_enqueue_scripts(...));
		add_action('admin_post_wfls_save_settings', $this->save_settings_form(...));

		add_action('show_user_profile', $this->edit_user_profile(...), 0); // We can't add it to the password section directly -- priority 0 is as close as we can get
		add_action('edit_user_profile', $this->edit_user_profile(...), 0);

		Controller_Permissions::init_actions();
	}

	/**
	 * Installation/Uninstallation
	 */
	private function install_plugin(): void
	{
		$this->install();
	}

	private function uninstall_plugin(): void
	{
		Controller_Time::shared()->uninstall();
		Controller_Permissions::shared()->uninstall();

		foreach ([self::VERSION_KEY] as $opt) {
			if (is_multisite() && function_exists('delete_network_option')) {
				delete_network_option(null, $opt);
			}
			delete_option($opt);
		}

		if (Controller_Settings::shared()->get_bool(Controller_Settings::OPTION_DELETE_ON_DEACTIVATION)) {
			Controller_DB::shared()->uninstall();
		}

		$this->purge_rewrite_rules();
	}

	protected function install()
	{
		static $_runInstallCalled = false;
		if ($_runInstallCalled) {
			return;
		}
		$_runInstallCalled = true;

		if (function_exists('ignore_user_abort')) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Installation must continue when the client disconnects.
			@ignore_user_abort(true);
		}

		if (! defined('DONOTCACHEDB')) {
			define('DONOTCACHEDB', true);
		}

		(is_multisite() && function_exists('get_network_option')) ? get_network_option(null, self::VERSION_KEY, '0.0.0') : get_option(self::VERSION_KEY, '0.0.0');
		if (is_multisite() && function_exists('update_network_option')) {
			update_network_option(null, self::VERSION_KEY, TFA_LS_VERSION); // In case we have a fatal error we don't want to keep running install.
		} else {
			update_option(self::VERSION_KEY, TFA_LS_VERSION); // In case we have a fatal error we don't want to keep running install.
		}

		Controller_DB::shared()->install();
		Controller_Settings::shared()->set_defaults();

		if (\TFAuthLS\Controller_Time::time() > Controller_Settings::shared()->get_int(Controller_Settings::OPTION_LAST_SECRET_REFRESH) + 180 * 86400) {
			Model_Crypto::refresh_secrets();
		}

		Controller_Time::shared()->install();
		Controller_Permissions::shared()->install();

		$this->purge_rewrite_rules();
	}

	private function purge_rewrite_rules(): void
	{
		// This is usually done internally in WP_Rewrite::flush_rules, but is followed there by WP_Rewrite::wp_rewrite_rules which repopulates it. This should cause it to be repopulated on the next request.
		update_option('rewrite_rules', '');
	}

	public function refresh_rewrite_rules(): void
	{
		flush_rewrite_rules();
	}

	/**
	 * Login Page
	 */
	private function login_enqueue_scripts(): void
	{
		$useCAPTCHA = false;

		if (Controller_Users::shared()->any_2fa_active()) {
			$this->validate_email_verification_token(null, $verification);

			Model_Script::create('2fa-ls-login', Model_Asset::js('login.js'), ['jquery'], TFA_LS_VERSION)
				->withTranslations(
					[
						'Message to Support'                                                                                                                                             => __('Message to Support', '2fa-login-security'),
						'Send'                                                                                                                                                           => __('Send', '2fa-login-security'),
						'An error was encountered while trying to send the message. Please try again.'                                                                                   => __('An error was encountered while trying to send the message. Please try again.', '2fa-login-security'),
						'<strong>ERROR</strong>: An error was encountered while trying to send the message. Please try again.'                                                           => wp_kses(__('<strong>ERROR</strong>: An error was encountered while trying to send the message. Please try again.', '2fa-login-security'), ['strong' => []]),
						'Login failed with status code 403. Please contact the site administrator.'                                                                                      => __('Login failed with status code 403. Please contact the site administrator.', '2fa-login-security'),
						'<strong>ERROR</strong>: Login failed with status code 403. Please contact the site administrator.'                                                              => wp_kses(__('<strong>ERROR</strong>: Login failed with status code 403. Please contact the site administrator.', '2fa-login-security'), ['strong' => []]),
						'Login failed with status code 503. Please contact the site administrator.'                                                                                      => __('Login failed with status code 503. Please contact the site administrator.', '2fa-login-security'),
						'<strong>ERROR</strong>: Login failed with status code 503. Please contact the site administrator.'                                                              => wp_kses(__('<strong>ERROR</strong>: Login failed with status code 503. Please contact the site administrator.', '2fa-login-security'), ['strong' => []]),
						'2FA Code'                                                                                                                                                       => __('2FA Code', '2fa-login-security'),
						'Your 2FA Code can be found within the authenticator app you used when first activating two-factor authentication. You may also use one of your recovery codes.' => __('Your 2FA Code can be found within the authenticator app you used when first activating two-factor authentication. You may also use one of your recovery codes.', '2fa-login-security'),
						'Remember for 30 days'                                                                                                                                           => __('Remember for 30 days', '2fa-login-security'),
						'Log In'                                                                                                                                                         => __('Log In', '2fa-login-security'),
						'<strong>ERROR</strong>: An error was encountered while trying to authenticate. Please try again.'                                                               => wp_kses(__('<strong>ERROR</strong>: An error was encountered while trying to authenticate. Please try again.', '2fa-login-security'), ['strong' => []]),
						'The 2FA code can be found in the authenticator app you used when first activating two-factor authentication. You may also use one of your recovery codes.'      => __('The 2FA code can be found in the authenticator app you used when first activating two-factor authentication. You may also use one of your recovery codes.', '2fa-login-security'),
					]
				)
				->setTranslationObjectName('TFA_LS_LOGIN_TRANSLATIONS')
				->enqueue();
			wp_enqueue_style('2fa-ls-login', Model_Asset::css('login.css'), [], TFA_LS_VERSION);
			wp_localize_script(
				'2fa-ls-login',
				'WFLSVars',
				[
					'ajaxurl'       => Utility_URL::relative_admin_url('admin-ajax.php'),
					'nonce'         => wp_create_nonce('wp-ajax'),
					'useCAPTCHA'    => $useCAPTCHA,
					'allowremember' => Controller_Settings::shared()->get_bool(Controller_Settings::OPTION_REMEMBER_DEVICE_ENABLED),
					'verification'  => $verification,
				]
			);
		}
	}

	private function get_2fa_management_script_data(): array
	{
		return [
			'WFLSVars' => [
				'ajaxurl'                => Utility_URL::relative_admin_url('admin-ajax.php'),
				'nonce'                  => wp_create_nonce('wp-ajax'),
				'modalTemplate'          => Model_View::create(
					'common/modal-prompt',
					[
						'title'          => '${title}',
						'message'        => '${message}',
						'primary_button' => [
							'id'    => 'wfls-generic-modal-close',
							'label' => __('Close', '2fa-login-security'),
							'link'  => '#',
						],
					]
				)->render(),
				'modalNoButtonsTemplate' => Model_View::create(
					'common/modal-prompt',
					[
						'title'   => '${title}',
						'message' => '${message}',
					]
				)->render(),
				'tokenInvalidTemplate'   => Model_View::create(
					'common/modal-prompt',
					[
						'title'          => '${title}',
						'message'        => '${message}',
						'primary_button' => [
							'id'    => 'wfls-token-invalid-modal-reload',
							'label' => __('Reload', '2fa-login-security'),
							'link'  => '#',
						],
					]
				)->render(),
				'modalHTMLTemplate'      => Model_View::create(
					'common/modal-prompt',
					[
						'title'          => '${title}',
						'message'        => '{{html message}}',
						'primary_button' => [
							'id'    => 'wfls-generic-modal-close',
							'label' => __('Close', '2fa-login-security'),
							'link'  => '#',
						],
					]
				)->render(),
			],
		];
	}

	private function get_2fa_management_assets(): array
	{
		$assets = [
			Model_Script::create('2fa-ls-jquery.qrcode', Model_Asset::js('jquery.qrcode.min.js'), ['jquery'], TFA_LS_VERSION),
		];
		$assets[] = Model_Script::create('2fa-ls-admin', Model_Asset::js('admin.js'), ['jquery'], TFA_LS_VERSION)
			->withTranslation('You have unsaved changes to your options. If you leave this page, those changes will be lost.', __('You have unsaved changes to your options. If you leave this page, those changes will be lost.', '2fa-login-security'))
			->setTranslationObjectName('WFLS_ADMIN_TRANSLATIONS');
		$assets[] = Model_Style::create('2fa-ls-admin', Model_Asset::css('admin.css'), [], TFA_LS_VERSION);

		return $assets;
	}

	private function enqueue_2fa_management_assets(): void
	{
		if ($this->management_assets_enqueued) {
			return;
		}
		wp_enqueue_script('jquery-ui-dialog');
		wp_enqueue_style('wp-jquery-ui-dialog');
		foreach ($this->get_2fa_management_assets() as $asset) {
			$asset->enqueue();
		}
		foreach ($this->get_2fa_management_script_data() as $key => $data) {
			wp_localize_script('2fa-ls-admin', $key, $data);
		}
		$this->management_assets_enqueued = true;
	}

	/**
	 * Admin Pages
	 */
	private function admin_enqueue_scripts($hookSuffix): void
	{

		wp_enqueue_style('2fa-ls-admin-global', Model_Asset::css('admin-global.css'), [], TFA_LS_VERSION);

		if (isset($_GET['page']) && 'wfls' === sanitize_key(wp_unslash($_GET['page']))) {
			$this->enqueue_2fa_management_assets();
		}

		if (Controller_Notices::shared()->has_notice(wp_get_current_user()) || in_array($hookSuffix, ['user-edit.php', 'user-new.php', 'profile.php'], true)) {
			wp_enqueue_script('2fa-ls-admin-global', Model_Asset::js('admin-global.js'), ['jquery'], TFA_LS_VERSION, true);

			wp_localize_script(
				'2fa-ls-admin-global',
				'GWFLSVars',
				[
					'ajaxurl' => admin_url('admin-ajax.php'),
					'nonce'   => wp_create_nonce('wp-ajax'),
				]
			);
		}
	}

	private function save_settings_form(): void
	{
		if (! current_user_can(Controller_Permissions::CAP_MANAGE_SETTINGS)) {
			wp_die(esc_html__('You do not have permission to change options.', '2fa-login-security'));
		}

		if (! isset($_POST['wfls-settings-nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wfls-settings-nonce'])), 'wfls-save-settings')) {
			wp_die(esc_html__('Your browser sent an invalid security token. Please reload and try again.', '2fa-login-security'));
		}

		// Each submitted setting is sanitized according to its expected type below.
		$submitted = isset($_POST['wfls_settings']) && is_array($_POST['wfls_settings']) ? wp_unslash($_POST['wfls_settings']) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$settings  = Controller_Settings::shared();
		$changes   = [];

		$boolOptions = [
			Controller_Settings::OPTION_REMEMBER_DEVICE_ENABLED,
			Controller_Settings::OPTION_ENABLE_LOGIN_HISTORY_COLUMNS,
			Controller_Settings::OPTION_USE_NTP,
			Controller_Settings::OPTION_DELETE_ON_DEACTIVATION,
		];
		foreach ($boolOptions as $optionKey) {
			$changes[$optionKey] = isset($submitted[$optionKey]);
		}

		if (isset($submitted['remember-device-duration-days'])) {
			$days                                                          = max(1, (int) $submitted['remember-device-duration-days']);
			$changes[Controller_Settings::OPTION_REMEMBER_DEVICE_DURATION] = $days * 86400;
		}

		if (isset($submitted[Controller_Settings::OPTION_REQUIRE_2FA_USER_GRACE_PERIOD])) {
			$changes[Controller_Settings::OPTION_REQUIRE_2FA_USER_GRACE_PERIOD] = (int) $submitted[Controller_Settings::OPTION_REQUIRE_2FA_USER_GRACE_PERIOD];
		}

		if (isset($submitted[Controller_Settings::OPTION_IP_SOURCE])) {
			$changes[Controller_Settings::OPTION_IP_SOURCE] = sanitize_text_field($submitted[Controller_Settings::OPTION_IP_SOURCE]);
		}

		if (isset($submitted[Controller_Settings::OPTION_IP_TRUSTED_PROXIES])) {
			$changes[Controller_Settings::OPTION_IP_TRUSTED_PROXIES] = sanitize_textarea_field($submitted[Controller_Settings::OPTION_IP_TRUSTED_PROXIES]);
		}

		foreach ($submitted as $key => $value) {
			if (0 === strpos($key, 'enabled-roles.')) {
				$changes[$key] = sanitize_text_field($value);
			}
		}

		$valid       = $settings->validate_multiple($changes);
		$redirectURL = add_query_arg(['page' => 'WFLS'], self_admin_url('admin.php'));
		if (true !== $valid) {
			wp_safe_redirect(add_query_arg(['wfls_settings_error' => 1], $redirectURL));
			exit;
		}

		$settings->set_multiple($changes, true);
		wp_safe_redirect(add_query_arg(['wfls_settings_saved' => 1], $redirectURL));
		exit;
	}

	private function edit_user_profile($user): void
	{
		if (get_current_user_id() === $user->ID || ! current_user_can(Controller_Permissions::CAP_ACTIVATE_2FA_OTHERS)) {
			$manageURL = admin_url('admin.php?page=WFLS');
		} else {
			$manageURL = admin_url('admin.php?page=WFLS&user=' . ((int) $user->ID));
		}

		if (is_multisite() && is_super_admin()) {
			if (get_current_user_id() === $user->ID) {
				$manageURL = network_admin_url('admin.php?page=WFLS');
			} else {
				$manageURL = network_admin_url('admin.php?page=WFLS&user=' . ((int) $user->ID));
			}
		}
		$user_allowed_2fa      = Controller_Users::shared()->can_activate_2fa($user);
		$viewer_is_user        = get_current_user_id() === $user->ID;
		$viewer_can_manage_2fa = current_user_can(Controller_Permissions::CAP_ACTIVATE_2FA_OTHERS);
		$in_grace_period       = false;
		$required_at           = null;
		$requires_2fa          = Controller_Users::shared()->requires_2fa($user, $in_grace_period, $required_at);
		$has_2fa               = Controller_Users::shared()->has_2fa_active($user);
		$locked_out            = $requires_2fa && ! $has_2fa;
		Controller_Settings::shared()->get_user_2fa_grace_period();
		if ($user_allowed_2fa && ($viewer_is_user || $viewer_can_manage_2fa)):
?>
			<h2 id="wfls-user-settings"><?php esc_html_e('2FA Login Security', '2fa-login-security'); ?></h2>
			<table class="form-table">
				<tr id="2fa-ls">
					<th><label for="2fa-ls-btn"><?php esc_html_e('2FA Status', '2fa-login-security'); ?></label></th>
					<td>
						<p>
							<strong><?php echo $locked_out ? esc_html__('Locked Out', '2fa-login-security') : ($has_2fa ? esc_html__('Active', '2fa-login-security') : esc_html__('Inactive', '2fa-login-security')); ?>:</strong>
							<?php
							echo $locked_out ?
								($viewer_is_user ? esc_html__('Two-factor authentication is required for your account, but has not been configured.', '2fa-login-security') : esc_html__('Two-factor authentication is required for this account, but has not been configured.', '2fa-login-security'))
								: ($has_2fa ? esc_html__('2FA is active.', '2fa-login-security') : esc_html__('2FA is inactive.', '2fa-login-security'));
							?>
							<a href="<?php echo esc_url(Controller_Support::esc_support_url(Controller_Support::ITEM_MODULE_LOGIN_SECURITY_2FA)); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Learn More', '2fa-login-security'); ?></a>
						</p>
						<?php if (! $has_2fa && $in_grace_period): ?>
							<p><strong>
									<?php
									printf(
										$viewer_is_user ?
											/* translators: Date */ esc_html__('Two-factor authentication must be activated for your account prior to %s to avoid losing access.', '2fa-login-security')
											: /* translators: Date */ esc_html__('Two-factor authentication must be activated for this account prior to %s.', '2fa-login-security'),
										esc_html(Controller_Time::format_site_datetime($required_at))
									)
									?>
								</strong></p>
						<?php endif ?>
						<?php
						if ($has_2fa || $viewer_is_user):
						?>
							<p><a href="<?php echo esc_url($manageURL); ?>" class="button"><?php echo (Controller_Users::shared()->has_2fa_active($user) ? esc_html__('Manage 2FA', '2fa-login-security') : esc_html__('Activate 2FA', '2fa-login-security')); ?></a></p><?php endif ?>
						<?php if ($viewer_can_manage_2fa): ?>
							<?php if ($locked_out): ?>
								<?php
								// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.Arrays.ArrayIndentation, Generic.WhiteSpace.ScopeIndent -- The view renderer escapes contextual data and preserves legacy embedded-template indentation.
								echo Model_View::create(
									'common/reset-grace-period',
									[
										'user'            => $user,
										'in_grace_period' => $in_grace_period,
									]
								)->render();
								// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.Arrays.ArrayIndentation, Generic.WhiteSpace.ScopeIndent
								?>
							<?php elseif ($in_grace_period && Controller_Users::shared()->has_revokable_grace_period($user)): ?>
								<?php
								// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.Arrays.ArrayIndentation, Generic.WhiteSpace.ScopeIndent -- The view renderer escapes contextual data and preserves legacy embedded-template indentation.
								echo Model_View::create(
									'common/revoke-grace-period',
									[
										'user' => $user,
									]
								)->render();
								// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.Arrays.ArrayIndentation, Generic.WhiteSpace.ScopeIndent
								?>
							<?php endif ?>
							<p>
								<a href="<?php echo esc_url(is_multisite() ? network_admin_url('admin.php?page=WFLS#top#settings') : admin_url('admin.php?page=WFLS#top#settings')); ?>" class="button"><?php esc_html_e('Manage 2FA Settings', '2fa-login-security'); ?></a>
							</p>
						<?php endif ?>
					</td>
				</tr>
			</table>
<?php
		endif;
	}

	/**
	 * Authentication
	 */
	private function authenticate($user, $username, $password)
	{
		if (defined('XMLRPC_REQUEST')) { // XML-RPC call and we're not enforcing 2FA on it
			return $user;
		}

		$is_login            = ! (defined('TFA_LS_AUTHENTICATION_CHECK') && TFA_LS_AUTHENTICATION_CHECK);
		$combined_two_factor = false;

		/*
		 * If we don't have a valid $user at this point, it means the $username/$password combo is invalid. We'll check
		 * to see if the user has provided a combined password in the format `<password><code>`, populating $user from
		 * that if so.
		 */
		if (! defined('TFA_LS_CHECKING_COMBINED') && (! isset($_POST['wfls-token']) || ! is_string($_POST['wfls-token'])) && (! is_object($user) || ! ($user instanceof \WP_User))) {
			// Compatibility with WF legacy 2FA
			$combined_totp_regex     = '/((?:[0-9]{3}\s*){2})$/i';
			$combined_recovery_regex = '/((?:[a-f0-9]{4}\s*){4})$/i';

			$revised_password = null;
			$code             = null;

			if (preg_match($combined_totp_regex, $password, $matches)) {
				// Possible TOTP code
				if (strlen($password) > strlen($matches[1])) {
					$revised_password = substr($password, 0, strlen($password) - strlen($matches[1]));
					$code             = $matches[1];
				}
			} elseif (preg_match($combined_recovery_regex, $password, $matches)) {
				// Possible recovery code
				if (strlen($password) > strlen($matches[1])) {
					$revised_password = substr($password, 0, strlen($password) - strlen($matches[1]));
					$code             = $matches[1];
				}
			}

			if (null !== $revised_password && null !== $code) {
				define('TFA_LS_CHECKING_COMBINED', true); // Avoid recursing into this block
				if (! defined('TFA_LS_AUTHENTICATION_CHECK')) {
					define('TFA_LS_AUTHENTICATION_CHECK', true);
				}
				$revised_user = wp_authenticate($username, $revised_password);
				if ($revised_user instanceof \WP_User  && Controller_TOTP::shared()->validate_2fa($revised_user, $code, $is_login)) {
					define('TFA_LS_COMBINED_IS_VALID', true); // This will cause the front-end to skip the 2FA prompt
					$user                = $revised_user;
					$combined_two_factor = true;
				}
			}
		}

		if (! $combined_two_factor && ($is_login && $user instanceof \WP_User)) {
			if (Controller_Users::shared()->has_2fa_active($user)) {
				if (Controller_Users::shared()->has_remembered_2fa($user)) {
					return $user;
				}
				if (array_key_exists('wfls-token', $_POST)) {
					$token = sanitize_text_field(wp_unslash($_POST['wfls-token']));
					if (is_string($token) && Controller_TOTP::shared()->validate_2fa($user, $token)) {
						return $user;
					}
					return new \WP_Error('wfls_twofactor_failed', wp_kses(__('<strong>CODE INVALID</strong>: The 2FA code provided is either expired or invalid. Please try again.', '2fa-login-security'), ['strong' => []]));
				}
			}
			$in_2fa_grace_period = false;
			$time_2fa_required   = null;
			if (Controller_Users::shared()->has_2fa_active($user)) {
				return new \WP_Error('wfls_twofactor_required', wp_kses(__('<strong>CODE REQUIRED</strong>: Please provide your 2FA code when prompted.', '2fa-login-security'), ['strong' => []]));
			}
			if (Controller_Users::shared()->requires_2fa($user, $in_2fa_grace_period, $time_2fa_required)) {
				return new \WP_Error('wfls_twofactor_blocked', wp_kses(__('<strong>LOGIN BLOCKED</strong>: 2FA is required to be active on your account. Please contact the site administrator.', '2fa-login-security'), ['strong' => []]));
			}
			if ($in_2fa_grace_period) {
				Controller_Notices::shared()->add_notice(Model_Notice::SEVERITY_CRITICAL, new Model_HTML(wp_kses(sprintf( /* translators: 1. Date; 2. Configuration URL */__('You do not currently have two-factor authentication active on your account, which will be required beginning %1$s. <a href="%2$s">Configure 2FA</a>', '2fa-login-security'), Controller_Time::format_site_datetime($time_2fa_required), esc_url((is_multisite() && is_super_admin($user->ID)) ? network_admin_url('admin.php?page=WFLS') : admin_url('admin.php?page=WFLS'))), ['a' => ['href' => []]])), 'wfls-will-be-required', $user);
			}
		}

		return $user;
	}

	private function set_logged_in_cookie($logged_in_cookie, $expire, $expiration, $user_id): void
	{
		$user = new \WP_User($user_id);
		if (Controller_Users::shared()->has_2fa_active($user) && isset($_POST['wfls-remember-device'])) {
			$remember_device = (bool) sanitize_text_field(wp_unslash($_POST['wfls-remember-device']));
			if ($remember_device) {
				Controller_Users::shared()->remember_2fa($user);
			}
		}
	}

	private function record_login($user_login/*, $user -- we'd like to use the second parameter instead, but too many plugins call this hook and only provide one of the two required parameters*/): void
	{
		$user = get_user_by('login', $user_login);
		if ($user instanceof \WP_User  && $user->exists()) {
			update_user_meta($user->ID, 'wfls-last-login', Controller_Time::time());
		}
	}

	private function register_post($sanitized_user_login, $user_email, $errors): void
	{
		// CAPTCHA checks have been removed from registration flow.
	}

	private function validate_email_verification_token($user = null, &$token = null): ?bool
	{
		$token = isset($_REQUEST['wfls-email-verification']) ? sanitize_text_field(wp_unslash($_REQUEST['wfls-email-verification'])) : null;
		if (empty($token)) {
			return null;
		}
		return is_string($token) && Controller_Users::shared()->validate_verification_token($token, $user);
	}

	/**
	 * @param \WP_Error $errors
	 * @param string    $redirect_to
	 * @return \WP_Error
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress requires the redirect parameter in this filter signature.
	private function wp_login_errors($errors, $redirect_to)
	{
		$has_errors                     = (method_exists($errors, 'has_errors') ? $errors->has_errors() : ! empty($errors->errors)); // has_errors was added in WP 5.1
		$email_verification_token_valid = $this->validate_email_verification_token();
		if (! $has_errors && null !== $email_verification_token_valid) {
			if ($email_verification_token_valid) {
				$errors->add('wfls_email_verified', esc_html__('Email verification succeeded. Please continue logging in.', '2fa-login-security'), 'message');
			} else {
				$errors->add('wfls_email_not_verified', esc_html__('Email verification invalid or expired. Please try again.', '2fa-login-security'), 'message');
			}
		}
		return $errors;
	}

	/**
	 * Menu
	 */
	private function admin_menu(): void
	{
		$user         = wp_get_current_user();
		$grace_period = false;
		if (Controller_Notices::shared()->has_notice($user)) {
			Controller_Users::shared()->requires_2fa($user, $grace_period);
			if (! $grace_period) {
				Controller_Notices::shared()->remove_notice(false, 'wfls-will-be-required', $user);
			}
		}

		Controller_Notices::shared()->enqueue_notices();

		$use_submenu = false;
		if (is_multisite() && ! is_network_admin()) {
			$use_submenu = false;

			if (is_super_admin()) {
				return;
			}
		}

		add_menu_page(
			__('Login Security', '2fa-login-security'),
			__('Login Security', '2fa-login-security'),
			Controller_Permissions::CAP_ACTIVATE_2FA_SELF,
			'WFLS',
			$this->menu(...),
			'dashicons-lock'
		);
	}

	private function menu(): void
	{
		$user           = wp_get_current_user();
		$administrator  = false;
		$can_edit_users = false;
		if (Controller_Permissions::shared()->can_manage_settings($user)) {
			$administrator = true;
		}

		if (user_can($user, Controller_Permissions::CAP_ACTIVATE_2FA_OTHERS)) {
			$can_edit_users = true;
			if (isset($_GET['user'])) {
				$user = new \WP_User(absint(wp_unslash($_GET['user'])));
				if (! $user->exists()) {
					$user = wp_get_current_user();
				}
			}
		}

		$sections = [];

		if (isset($_GET['role']) && $can_edit_users) {
			$role_key    = sanitize_key(wp_unslash($_GET['role']));
			$roles       = new \WP_Roles();
			$role        = $roles->get_role($role_key);
			$role_title  = 'super-admin' === $role_key ? __('Super Administrator', '2fa-login-security') : $roles->role_names[$role_key];
			$required_at = Controller_Settings::shared()->get_required_2fa_role_activation_time($role_key);
			$states      = [
				'grace_period' => [
					'title'           => __('Grace Period', '2fa-login-security'),
					'in_grace_period' => true,
				],
				'locked_out'   => [
					'title'           => __('Locked Out', '2fa-login-security'),
					'in_grace_period' => false,
				],
			];
			foreach ($states as $key => $state) {
				$page_key  = "page_$key";
				$page      = isset($_GET[$page_key]) ? max(absint(wp_unslash($_GET[$page_key])), 1) : 1;
				$title     = $state['title'];
				$last_page = true;
				if (false === $required_at) {
					$users = [];
				} else {
					$users = Controller_Users::shared()->get_inactive_2fa_users($role_key, $state['in_grace_period'], $page, self::USERS_PER_PAGE, $last_page);
				}
				$sections[] = [
					'tab'     => new Model_Tab($key, $key, $title, $title),
					'title'   => new Model_Title($key, sprintf( /* translators: User count */__('Users without 2FA active (%s)', '2fa-login-security'), $title) . ' - ' . $role_title),
					'content' => new Model_View(
						'page/role',
						[
							'role'        => $role,
							'role_title'  => $role_title,
							'state_title' => $title,
							'required_at' => $required_at,
							'state'       => $state,
							'users'       => $users,
							'page'        => $page,
							'last_page'   => $last_page,
							'page_key'    => $page_key,
							'state_key'   => $key,
						]
					),
				];
			}
		} else {
			$sections[] = [
				'tab'     => new Model_Tab('manage', 'manage', __('Two-Factor Authentication', '2fa-login-security'), __('Two-Factor Authentication', '2fa-login-security')),
				'title'   => new Model_Title('manage', __('Two-Factor Authentication', '2fa-login-security'), Controller_Support::support_url(Controller_Support::ITEM_MODULE_LOGIN_SECURITY_2FA), new Model_HTML(wp_kses(__('Learn more<span class="wfls-hidden-xs"> about Two-Factor Authentication</span>', '2fa-login-security'), ['span' => ['class' => []]]))),
				'content' => new Model_View(
					'page/manage',
					[
						'user'           => $user,
						'can_edit_users' => $can_edit_users,
					]
				),
			];

			if ($administrator) {
				$sections[] = [
					'tab'     => new Model_Tab('settings', 'settings', __('Settings', '2fa-login-security'), __('Settings', '2fa-login-security')),
					'title'   => new Model_Title('settings', __('Login Security Settings', '2fa-login-security'), Controller_Support::support_url(Controller_Support::ITEM_MODULE_LOGIN_SECURITY), new Model_HTML(wp_kses(__('Learn more<span class="wfls-hidden-xs"> about Login Security</span>', '2fa-login-security'), ['span' => ['class' => []]]))),
					'content' => new Model_View(
						'page/settings',
						[
							'hasWoocommerce' => false,
						]
					),
				];
			}
		}

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- The view renderer escapes contextual data and returns trusted template markup.
		$view = new Model_View(
			'page/page',
			[
				'sections' => $sections,
			]
		);
		echo $view->render();
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private function user_new_form(): void
	{
		if (Controller_Settings::shared()->get_user_2fa_grace_period()) {
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- The view renderer escapes contextual data and returns trusted template markup.
			echo Model_View::create('user/grace-period-toggle', [])->render();
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	private function user_register($new_user_id): void
	{
		$creator = wp_get_current_user();
		if (! Controller_Permissions::shared()->can_manage_settings($creator) || $creator->ID === $new_user_id) {
			return;
		}
		if (isset($_POST['wfls-grace-period-toggle'])) {
			Controller_Users::shared()->allow_grace_period($new_user_id);
		}
	}
}
