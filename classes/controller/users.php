<?php

/**
 * User management controller.
 *
 * @package TFAuthLS
 */

// phpcs:disable Squiz.Commenting.FileComment, Squiz.Commenting.ClassComment, Squiz.Commenting.VariableComment, Squiz.Commenting.FunctionComment, Generic.Commenting.DocComment, Squiz.PHP.CommentedOutCode.Found, Universal.Operators.StrictComparisons.LooseEqual, WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase, WordPress.NamingConventions.ValidVariableName.InterpolatedVariableNotSnakeCase, WordPress.NamingConventions.ValidHookName.NotLowercase, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value, WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode, WordPress.PHP.StrictInArray.MissingTrueStrict, WordPress.Security.NonceVerification.Recommended -- Legacy public identifiers, WordPress hook/request contracts, database schema, and token storage formats are retained for compatibility.

namespace TFAuthLS;

use TFAuthLS\Crypto\Model_JWT;
use TFAuthLS\Crypto\Model_Symmetric;
use RuntimeException;

class Controller_Users
{






	const RECOVERY_CODE_COUNT                 = 5;
	const RECOVERY_CODE_SIZE                  = 8;
	const SECONDS_PER_DAY                     = 86400;
	const META_KEY_GRACE_PERIOD_RESET         = 'wfls-grace-period-reset';
	const META_KEY_GRACE_PERIOD_OVERRIDE      = 'wfls-grace-period-override';
	const META_KEY_ALLOW_GRACE_PERIOD         = 'wfls-allow-grace-period';
	const META_KEY_VERIFICATION_TOKENS        = 'wfls-verification-tokens';
	const VERIFICATION_TOKEN_BYTES            = 64;
	const VERIFICATION_TOKEN_LIMIT            = 5; // Max number of concurrent tokens
	const VERIFICATION_TOKEN_TRANSIENT_PREFIX = 'wfls_verify_';
	const LARGE_USER_BASE_THRESHOLD           = 1000;
	const TRUNCATED_ROLE_KEY                  = 1;

	/**
	 * Returns the singleton Controller_Users.
	 *
	 * @return Controller_Users
	 */
	public static function shared()
	{
		static $_shared = null;
		if (null === $_shared) {
			$_shared = new Controller_Users();
		}
		return $_shared;
	}

	public function init(): void
	{
		$this->init_actions();
	}

	/**
	 * Imports the array of 2FA secrets. Users that do not currently exist or are disallowed from enabling 2FA are not imported.
	 *
	 * @param array $secrets An array of secrets in the format array(<user id> => array('secret' => <secret in hex>, 'recovery' => <recovery keys in hex>, 'ctime' => <timestamp>, 'vtime' => <timestamp>, 'type' => <type>), ...)
	 * @return int The number imported.
	 */
	public function import_2fa($secrets): int
	{
		global $wpdb;
		$table = Controller_DB::shared()->secrets;

		$count = 0;
		foreach ($secrets as $id => $parameters) {
			$user = new \WP_User($id);
			if (! $user->exists()) {
				continue;
			}
			if (! $this->can_activate_2fa($user)) {
				continue;
			}
			if ('authenticator' !== $parameters['type']) {
				continue;
			}
			if ($this->has_2fa_active($user)) {
				continue;
			}
			$secret   = Model_Compat::hex2bin($parameters['secret']);
			$recovery = Model_Compat::hex2bin($parameters['recovery']);
			$ctime    = (int) $parameters['ctime'];
			$vtime    = min((int) $parameters['vtime'], Controller_Time::time());
			$type     = $parameters['type'];
			$wpdb->query($wpdb->prepare("INSERT INTO `{$table}` (`user_id`, `secret`, `recovery`, `ctime`, `vtime`, `mode`) VALUES (%d, %s, %s, %d, %d, %s)", $user->ID, $secret, $recovery, $ctime, $vtime, $type));
			++$count;
		}
		return $count;
	}

	public function admin_users()
	{
		// We should eventually allow for any user to be granted the manage capability, but we won't account for that now
		if (is_multisite()) {
			$logins = get_super_admins();
			$users  = array();
			foreach ($logins as $l) {
				$user = new \WP_User(0, $l);
				if ($user->ID > 0) {
					$users[] = $user;
				}
			}
			return $users;
		}

		$query = new \WP_User_Query(
			array(
				'role'   => 'administrator',
				'number' => -1,
			)
		);
		return $query->get_results();
	}

	public function get_users_by_role($role, $limit = -1)
	{
		if ('super-admin' === $role) {
			$super_admins = array();
			foreach (get_super_admins() as $username) {
				$super_admins[] = new \WP_User($username);
			}
			return $super_admins;
		}
		$query = new \WP_User_Query(
			array(
				'role'   => $role,
				'number' => is_int($limit) ? $limit : -1,
			)
		);
		return $query->get_results();
	}

	/**
	 * Returns whether or not the user has a valid remembered device.
	 *
	 * @param \WP_User $user
	 * @return bool
	 */
	public function has_remembered_2fa($user)
	{
		static $_cache = array();
		if (isset($_cache[$user->ID])) {
			return $_cache[$user->ID];
		}

		if (! Controller_Settings::shared()->get_bool(Controller_Settings::OPTION_REMEMBER_DEVICE_ENABLED)) {
			return false;
		}

		$maxExpiration = \TFAuthLS\Controller_Time::time() + Controller_Settings::shared()->get_int(Controller_Settings::OPTION_REMEMBER_DEVICE_DURATION);

		$encrypted = Model_Symmetric::encrypt((string) $user->ID);
		if (! $encrypted) { // Can't generate cookie key due to host failure
			return false;
		}

		foreach ($_COOKIE as $name => $value) {
			if (! preg_match('/^wfls\-remembered\-(.+)$/', $name, $matches)) {
				continue;
			}

			$jwt = Model_JWT::decode_jwt($value);
			if (! $jwt) {
				continue;
			}
			if (! isset($jwt->payload['iv'])) {
				continue;
			}

			if (\TFAuthLS\Controller_Time::time() > min($jwt->expiration, $maxExpiration)) { // Either JWT is expired or the remember period was shortened since generating it
				continue;
			}

			$data      = Model_JWT::base64url_convert_from($matches[1]);
			$iv        = $jwt->payload['iv'];
			$encrypted = array(
				'data' => $data,
				'iv'   => $iv,
			);
			$user_id    = (int) Model_Symmetric::decrypt($encrypted);
			if (0 !== $user_id && $user_id === $user->ID) {
				$_cache[$user->ID] = true;
				return true;
			}
		}

		$_cache[$user->ID] = false;
		return false;
	}

	/**
	 * Sets the cookie needed to remember the 2FA status.
	 *
	 * @param \WP_User $user
	 */
	public function remember_2fa($user): void
	{
		if (! Controller_Settings::shared()->get_bool(Controller_Settings::OPTION_REMEMBER_DEVICE_ENABLED)) {
			return;
		}

		if ($this->has_remembered_2fa($user)) {
			return;
		}

		$encrypted = Model_Symmetric::encrypt((string) $user->ID);
		if (! $encrypted) { // Can't generate cookie key due to host failure
			return;
		}

		// Remove old cookies
		foreach (array_keys($_COOKIE) as $name) {
			if (! preg_match('/^wfls\-remembered\-(.+)$/', $name, $matches)) {
				continue;
			}
			setcookie($name, '', \TFAuthLS\Controller_Time::time() - 86400);
		}

		// Set the new one
		$expiration  = \TFAuthLS\Controller_Time::time() + Controller_Settings::shared()->get_int(Controller_Settings::OPTION_REMEMBER_DEVICE_DURATION);
		$jwt         = new Model_JWT(array('iv' => $encrypted['iv']), $expiration);
		$cookieName  = 'wfls-remembered-' . Model_JWT::base64url_convert_to($encrypted['data']);
		$cookieValue = (string) $jwt;
		setcookie($cookieName, $cookieValue, $expiration, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
	}

	/**
	 * Returns whether or not 2FA can be activated on the given user.
	 *
	 * @param \WP_User $user
	 * @return bool
	 */
	public function can_activate_2fa($user)
	{
		if (is_multisite() && ! is_super_admin($user->ID)) {
			return Controller_Permissions::shared()->does_user_have_multisite_capability($user, Controller_Permissions::CAP_ACTIVATE_2FA_SELF);
		}
		return user_can($user, Controller_Permissions::CAP_ACTIVATE_2FA_SELF);
	}

	/**
	 * Returns whether or not any user has 2FA activated.
	 */
	public function any_2fa_active(): bool
	{
		global $wpdb;
		$table = Controller_DB::shared()->secrets;
		return (bool) intval($wpdb->get_var("SELECT COUNT(*) FROM `{$table}`"));
	}

	/**
	 * Returns whether or not the user has 2FA activated.
	 *
	 * @param \WP_User $user
	 */
	public function has_2fa_active($user): bool
	{
		global $wpdb;
		$table = Controller_DB::shared()->secrets;
		return $this->can_activate_2fa($user) && (bool) intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `user_id` = %d", $user->ID)));
	}

	/**
	 * Deactivates a user.
	 *
	 * @param \WP_User $user
	 */
	public function deactivate_2fa($user): void
	{
		global $wpdb;
		$table = Controller_DB::shared()->secrets;
		$wpdb->query($wpdb->prepare("DELETE FROM `{$table}` WHERE `user_id` = %d", $user->ID));

		/**
		 * Fires when 2FA is disabled for a user.
		 *
		 * @since 1.1.13
		 *
		 * @param \WP_User $user The user.
		 */
		do_action('TFA_LS_2fa_deactivated', $user);
	}

	private function has_admin_with_2fa_active()
	{
		static $cache = null;
		if (null === $cache) {
			$activeIDs = $this->user_ids_with_2fa_active();
			foreach ($activeIDs as $id) {
				if (Controller_Permissions::shared()->can_manage_settings(new \WP_User($id))) {
					$cache = true;
					return $cache;
				}
			}
			$cache = false;
		}
		return $cache;
	}

	/**
	 * Returns whether or not 2FA is required for the user regardless of activation status. 2FA is considered required
	 * when the option to require it is enabled and there is at least one administrator with it active.
	 *
	 * @param \WP_User  $user
	 * @param bool|null &$grace_period
	 * @param int|null  &$required_at
	 * @return bool
	 */
	public function requires_2fa($user, &$grace_period = false, &$required_at = null)
	{
		static $cache = array();
		if (array_key_exists($user->ID, $cache)) {
			list($required, $grace_period, $required_at) = $cache[$user->ID];
			return $required;
		}
		$grace_period        = false;
		$required_at         = null;
		$required           = $this->does_user_role_require_2fa($user, $grace_period, $required_at);
		$cache[$user->ID] = array($required, $grace_period, $required_at);
		return $required;
	}

	/**
	 * Returns the number of recovery codes remaining for the user or null if the user does not have 2FA active.
	 *
	 * @param \WP_User $user
	 */
	public function recovery_code_count($user): ?float
	{
		global $wpdb;
		$table  = Controller_DB::shared()->secrets;
		$record = $wpdb->get_var($wpdb->prepare("SELECT `recovery` FROM `{$table}` WHERE `user_id` = %d", $user->ID));
		if (! $record) {
			return null;
		}

		return floor(Model_Crypto::strlen($record) / self::RECOVERY_CODE_SIZE);
	}

	/**
	 * Generates a new set of recovery codes and saves them to $user if provided.
	 *
	 * @param \WP_User|bool $user The user to save the codes to or false to just return codes.
	 * @param int           $count
	 */
	public function regenerate_recovery_codes($user = false, $count = self::RECOVERY_CODE_COUNT): array
	{
		$codes = array();
		for ($i = 0; $i < $count; $i++) {
			$c       = \TFAuthLS\Model_Crypto::random_bytes(self::RECOVERY_CODE_SIZE);
			$codes[] = $c;
		}

		if ($user && self::shared()->has_2fa_active($user)) {
			global $wpdb;
			$table = Controller_DB::shared()->secrets;
			$wpdb->query($wpdb->prepare("UPDATE `{$table}` SET `recovery` = %s WHERE `user_id` = %d", implode('', $codes), $user->ID));
		}

		return $codes;
	}

	/**
	 * Returns the active and inactive user counts.
	 */
	public function user_counts(): array
	{
		if (is_multisite() && function_exists('get_user_count')) {
			$total_users = get_user_count();
		} else {
			global $wpdb;
			$total_users = (int) $wpdb->get_var("SELECT COUNT(ID) as c FROM {$wpdb->users}");
		}
		$active_users = $this->active_count();
		return array(
			'active_users'   => $active_users,
			'inactive_users' => max($total_users - $active_users, 0),
		);
	}

	public function detailed_user_counts($force = false)
	{
		global $wpdb;

		$blog_prefix = $wpdb->get_blog_prefix();
		$wp_roles    = new \WP_Roles();
		$roles       = $wp_roles->get_names();

		$counts = array();
		$groups = array(
			'avail_roles'        => 0,
			'active_avail_roles' => 0,
		);

		foreach (array_keys($groups) as $group) {
			$counts[$group] = array();
			foreach ($roles as $role_key => $role_name) {
				$counts[$group][$role_key] = 0;
			}
			$counts[$group][self::TRUNCATED_ROLE_KEY] = 0;
		}

		$db_controller = Controller_DB::shared();

		if ($db_controller->create_temporary_role_counts_table()) {
			$lock              = new Utility_NullLock();
			$role_counts_table = $db_controller->role_counts_temporary;
		} else {
			$lock              = new Utility_DatabaseLock($db_controller, 'role-count-calculation');
			$role_counts_table = $db_controller->role_counts;
		}

		try {
			$lock->acquire();

			if (! $force && Controller_Settings::shared()->get_bool(Controller_Settings::OPTION_USER_COUNT_QUERY_STATE)) {
				throw new RuntimeException('Previous user count query failed to completed successfully. User count queries are currently disabled');
			}
			Controller_Settings::shared()->set(Controller_Settings::OPTION_USER_COUNT_QUERY_STATE, true);

			$db_controller->require_schema_version(2);
			$secrets_table = $db_controller->secrets;

			$db_controller->query("TRUNCATE {$role_counts_table}");
			$db_controller->query(
				$wpdb->prepare(
					<<<SQL
				INSERT INTO {$role_counts_table}
				SELECT
					um.meta_value AS serialized_roles,
					s.user_id IS NULL AS two_factor_inactive,
					1 AS user_count
				FROM
					{$wpdb->usermeta} um
				INNER JOIN {$wpdb->users} u ON u.ID = um.user_id
				LEFT JOIN {$secrets_table} s ON s.user_id = u.ID
				WHERE
					meta_key = %s
				ON DUPLICATE KEY
					UPDATE user_count = user_count + 1;
			SQL,
					"{$blog_prefix}capabilities"
				)
			);

			$results = $wpdb->get_results(
				<<<SQL
				SELECT
					serialized_roles AS serialized_roles,
					two_factor_inactive,
					user_count
				FROM
					{$role_counts_table};
			SQL,
				OBJECT
			);

			Controller_Settings::shared()->set(Controller_Settings::OPTION_USER_COUNT_QUERY_STATE, false);
		} catch (RuntimeException $e) {
			$lock->release(); // Finally is not supported in older PHP versions, so it is necessary to release the lock in two places
			return false;
		}
		$lock->release();

		foreach ($results as $row) {
			$truncated_role = false;
			try {
				$row_roles = Utility_Serialization::unserialize($row->serialized_roles, array('allowed_classes' => false), 'is_array');
			} catch (RuntimeException $e) {
				$row_roles      = array(self::TRUNCATED_ROLE_KEY => true);
				$truncated_role = true;
			}
			foreach ($row_roles as $row_role => $state) {
				if (true !== $state || (! $truncated_role && ! is_string($row_role))) {
					continue;
				}
				if (array_key_exists($row_role, $roles) || self::TRUNCATED_ROLE_KEY === $row_role) {
					foreach ($groups as $group => &$group_count) {
						if ('active_avail_roles' === $group && $row->two_factor_inactive) {
							continue;
						}
						$counts[$group][$row_role] += $row->user_count;
						$group_count                   += $row->user_count;
					}
				}
			}
		}

		foreach ($roles as $role_key => $role_name) {
			if (0 === $counts['avail_roles'][$role_key] && 0 === $counts['active_avail_roles'][$role_key]) {
				unset($counts['avail_roles'][$role_key]);
				unset($counts['active_avail_roles'][$role_key]);
			}
		}

		// Separately add super admins for multisite
		if (is_multisite()) {
			$super_admins       = 0;
			$activeSuperAdmins = 0;
			foreach (get_super_admins() as $username) {
				++$super_admins;
				$user = new \WP_User($username);
				if ($this->has_2fa_active($user)) {
					++$activeSuperAdmins;
				}
			}
			$counts['avail_roles']['super-admin']        = $super_admins;
			$counts['active_avail_roles']['super-admin'] = $activeSuperAdmins;
		}

		$counts['total_users']        = $groups['avail_roles'];
		$counts['active_total_users'] = $groups['active_avail_roles'];

		return $counts;
	}

	/**
	 * Returns the number of users with 2FA active.
	 */
	public function active_count(): int
	{
		global $wpdb;
		$table = Controller_DB::shared()->secrets;
		return intval($wpdb->get_var("SELECT COUNT(*) FROM `{$table}`"));
	}

	/**
	 * WP Filters/Actions
	 */
	protected function init_actions()
	{
		add_action('deleted_user', array($this, 'deleted_user'));
		add_filter('manage_users_columns', array($this, 'manage_users_columns'));
		add_filter('manage_users_custom_column', array($this, 'manage_users_custom_column'), 10, 3);
		add_filter('manage_users_sortable_columns', array($this, 'manage_users_sortable_columns'), 10, 1);
		add_filter('users_list_table_query_args', array($this, 'users_list_table_query_args'));
		add_filter('user_row_actions', array($this, 'user_row_actions'), 10, 2);
		add_filter('views_users', array($this, 'views_users'));

		if (is_multisite()) {
			add_filter('manage_users-network_columns', array($this, 'manage_users_columns'));
			add_filter('manage_users-network_custom_column', array($this, 'manage_users_custom_column'), 10, 3);
			add_filter('manage_users-network_sortable_columns', array($this, 'manage_users_sortable_columns'), 10, 1);
			add_filter('ms_user_row_actions', array($this, 'user_row_actions'), 10, 2);
			add_filter('views_users-network', array($this, 'views_users'));
		}
	}

	public function deleted_user($id): void
	{
		$user = new \WP_User($id);
		if (! $user->exists()) {
			global $wpdb;
			$table = Controller_DB::shared()->secrets;
			$wpdb->query($wpdb->prepare("DELETE FROM `{$table}` WHERE `user_id` = %d", $id));
		}
	}

	public function manage_users_columns(array $columns = array()): array
	{
		if (user_can(wp_get_current_user(), Controller_Permissions::CAP_ACTIVATE_2FA_OTHERS)) {
			$columns['wfls_2fa_status'] = esc_html__('2FA Status', '2fa-login-security');
		}

		if (Controller_Settings::shared()->are_login_history_columns_enabled() && Controller_Permissions::shared()->can_manage_settings(wp_get_current_user())) {
			$columns['wfls_last_login'] = esc_html__('Last Login', '2fa-login-security');
		}
		return $columns;
	}

	public function manage_users_custom_column($value = '', $column_name = '', $user_id = 0)
	{
		switch ($column_name) {
			case 'wfls_2fa_status':
				$user  = new \WP_User($user_id);
				$value = __('Not Allowed', '2fa-login-security');
				if (self::shared()->can_activate_2fa($user)) {
					$has2fa      = self::shared()->has_2fa_active($user);
					$requires2fa = $this->requires_2fa($user, $in_grace_period);
					if ($has2fa) {
						$value = esc_html__('Active', '2fa-login-security');
					} elseif ($in_grace_period) {
						$value = wp_kses(__('Inactive<small class="wfls-sub-status">(Grace Period)</small>', '2fa-login-security'), array('small' => array('class' => array())));
					} elseif ($requires2fa) {
						$value = wp_kses(null === $in_grace_period ? __('Locked Out<small class="wfls-sub-status">(Grace Period Disabled)</small>', '2fa-login-security') : __('Locked Out<small class="wfls-sub-status">(Grace Period Exceeded)</small>', '2fa-login-security'), array('small' => array('class' => array())));
					} else {
						$value = esc_html__('Inactive', '2fa-login-security');
					}
				}
				break;
			case 'wfls_last_login':
				$value = '-';
				$last = get_user_meta($user_id, 'wfls-last-login', true);
				if ($last && Utility_Number::is_unix_timestamp($last)) {
					$value = Controller_Time::format_local_time(get_option('date_format') . ' ' . get_option('time_format'), $last);
				}
				break;
		}

		return $value;
	}

	public function manage_users_sortable_columns($sortable_columns): array
	{
		return array_merge(
			$sortable_columns,
			array(
				'wfls_last_login' => 'wfls-lastlogin',
			)
		);
	}

	protected function user_ids_with_2fa_active()
	{
		global $wpdb;
		$table = Controller_DB::shared()->secrets;
		return $wpdb->get_col("SELECT DISTINCT `user_id` FROM {$table}");
	}

	public function users_list_table_query_args($args)
	{
		$mode = isset($_REQUEST['wf2fa']) ? sanitize_key(wp_unslash($_REQUEST['wf2fa'])) : '';
		if (preg_match('/^(?:in)?active$/i', $mode)) {
			if ('active' === $mode) {
				$args['include'] = $this->user_ids_with_2fa_active();
			} elseif ('inactive' === $mode) {
				unset($args['include']);
				$args['exclude'] = $this->user_ids_with_2fa_active();
			}
		}

		if (isset($args['orderby'])) {
			if (is_string($args['orderby'])) {
				if ('wfls-lastlogin' === $args['orderby']) {
					$args['meta_key'] = 'wfls-last-login';
					$args['orderby']  = 'meta_value';
				}
			} else {
				$has_one = false;
				if (array_key_exists('wfls-lastlogin', $args['orderby'])) {
					$args['meta_key']              = 'wfls-last-login';
					$args['orderby']['meta_value'] = $args['orderby']['wfls-lastlogin'];
					unset($args['orderby']['wfls-lastlogin']);
					$has_one = true;
				}

				if (in_array('wfls-lastlogin', $args['orderby'])) {
					if (! $has_one) { // We have to discard one if both are set to sort by because $meta_key can only be a single value rather than an array
						$args['meta_key']  = 'wfls-last-login';
						$args['orderby'][] = 'meta_value';
					}
					unset($args['orderby'][array_search('wfls-lastlogin', $args['orderby'])]);
					$has_one = true;
				}
			}
		}
		return $args;
	}

	public function user_row_actions(array $actions, $user): array
	{
		// Format is 'view' => '<a href="https://wfpremium.dev1.ryanbritton.com/author/ryan/" aria-label="View posts by ryan">View</a>'
		if (user_can(wp_get_current_user(), Controller_Permissions::CAP_ACTIVATE_2FA_OTHERS) && (self::shared()->can_activate_2fa($user) || self::shared()->has_2fa_active($user))) {
			$url              = (is_multisite() ? network_admin_url('admin.php?page=WFLS&user=' . $user->ID) : admin_url('admin.php?page=WFLS&user=' . $user->ID));
			$actions['wf2fa'] = '<a href="' . esc_url($url) . '" aria-label="' . esc_attr(sprintf( /* translators: Username */__('Edit two-factor authentication for %s', '2fa-login-security'), $user->user_login)) . '">' . esc_html__('2FA', '2fa-login-security') . '</a>';
		}
		return $actions;
	}

	public function views_users(array $views): array
	{
		// Format is 'subscriber' => '<a href=\\'users.php?role=subscriber\\'>Subscriber <span class="count">(40,002)</span></a>',
		include ABSPATH . WPINC . '/version.php';
		/** @var string $wp_version */
		if (user_can(wp_get_current_user(), Controller_Permissions::CAP_ACTIVATE_2FA_OTHERS) && version_compare($wp_version, '4.4.0', '>=')) {
			$counts                 = $this->user_counts();
			$views['all']           = str_replace(' class="current" aria-current="page"', '', $views['all']);
			$views['wfls-active']   = '<a href="' . esc_url(add_query_arg('wf2fa', 'active', 'users.php')) . '"' . (isset($_GET['wf2fa']) && 'active' == $_GET['wf2fa'] ? ' class="current" aria-current="page"' : '') . '>' . esc_html__('2FA Active', '2fa-login-security') . ' <span class="count">(' . number_format($counts['active_users']) . ')</span></a>';
			$views['wfls-inactive'] = '<a href="' . esc_url(add_query_arg('wf2fa', 'inactive', 'users.php')) . '"' . (isset($_GET['wf2fa']) && 'inactive' == $_GET['wf2fa'] ? ' class="current" aria-current="page"' : '') . '>' . esc_html__('2FA Inactive', '2fa-login-security') . ' <span class="count">(' . number_format($counts['inactive_users']) . ')</span></a>';
		}
		return $views;
	}

	private function get_grace_period_reset_time($user): ?int
	{
		$time = get_user_option(self::META_KEY_GRACE_PERIOD_RESET, $user->ID);
		if (empty($time)) {
			return null;
		}
		return (int) $time;
	}

	public function get_grace_period_override($user): ?int
	{
		$override = get_user_option(self::META_KEY_GRACE_PERIOD_OVERRIDE, $user->ID);
		if (false === $override) {
			return null;
		}
		return (int) $override;
	}

	private function does_user_role_require_2fa($user, &$in_grace_period = null, &$required_at = null): bool
	{
		$is2faAdmin = Controller_Permissions::shared()->can_manage_settings($user);
		$userDate   = self::get_grace_period_reset_time($user);
		if (null === $userDate) {
			$userDate = $this->get_registration_date($user);
		}
		if ($is2faAdmin && ! $this->get_grace_period_allowed_flag($user->ID)) {
			$grace_period   = 0;
			$in_grace_period = null;
		} else {
			$grace_period = self::get_grace_period_override($user);
			if (null === $grace_period) {
				$grace_period = Controller_Settings::shared()->get_user_2fa_grace_period();
			}
			$grace_period  *= self::SECONDS_PER_DAY;
			$in_grace_period = false;
		}
		$now = time();
		foreach (Controller_Permissions::shared()->get_all_roles($user) as $role) {
			$role_date = Controller_Settings::shared()->get_required_2fa_role_activation_time($role);
			if (false === $role_date) {
				continue;
			}
			$effectiveDate = max($userDate, $role_date) + $grace_period;
			if (null === $required_at || $effectiveDate < $required_at) {
				$required_at = $effectiveDate;
			}
			if ($effectiveDate <= $now && (! $is2faAdmin || $this->has_admin_with_2fa_active())) {
				if ($in_grace_period) {
					$in_grace_period = false;
				}
				return true;
			}
			if (null !== $in_grace_period) {
				$in_grace_period = true;
			}
		}
		return false;
	}

	private function get_registration_date($user): int|false
	{
		return strtotime($user->user_registered);
	}

	public function reset_2fa_grace_period($user, $override = null): bool
	{
		if (! $this->can_activate_2fa($user) || $this->has_2fa_active($user)) {
			return false;
		}
		update_user_option($user->ID, self::META_KEY_GRACE_PERIOD_RESET, time(), true);
		if (null !== $override) {
			update_user_option($user->ID, self::META_KEY_GRACE_PERIOD_OVERRIDE, (int) $override, true);
		}
		return true;
	}

	public function revoke_grace_period($user): void
	{
		foreach (
			array(
				self::META_KEY_GRACE_PERIOD_RESET,
				self::META_KEY_GRACE_PERIOD_OVERRIDE,
				self::META_KEY_ALLOW_GRACE_PERIOD,
			) as $option
		) {
			delete_user_option($user->ID, $option, true);
		}
	}

	public function allow_grace_period($user_id): void
	{
		update_user_option($user_id, self::META_KEY_ALLOW_GRACE_PERIOD, true, true);
	}

	public function get_grace_period_allowed_flag($user_id): bool
	{
		return (bool) get_user_option(self::META_KEY_ALLOW_GRACE_PERIOD, $user_id);
	}

	public function has_revokable_grace_period($user)
	{
		if ($this->get_grace_period_allowed_flag($user->ID)) {
			return true;
		}
		return null !== $this->get_grace_period_reset_time($user);
	}

	/**
	 * @return \StdClass[]
	 */
	private function get_inactive_2fa_super_admins($grace_period = false): array
	{
		$inactive = array();
		foreach (get_super_admins() as $username) {
			$user = new \WP_User($username);
			if (! $this->has_2fa_active($user)) {
				$this->requires_2fa($user, $in_grace_period, $required_at);
				if (null === $grace_period || $grace_period == $in_grace_period) {
					$current              = new \StdClass();
					$current->user_id     = $user->ID;
					$current->user_login  = $username;
					$current->required_at = $required_at;
					$inactive[]           = $current;
				}
			}
		}
		return $inactive;
	}

	private function generate_inactive_2fa_user_query($roleKey, $grace_period = null, $page = null, $per_page = null)
	{
		global $wpdb;
		$secondsPerDay             = self::SECONDS_PER_DAY;
		$grace_period_seconds        = (int) (Controller_Settings::shared()->get_user_2fa_grace_period() * self::SECONDS_PER_DAY);
		$roleTime                  = (int) (Controller_Settings::shared()->get_required_2fa_role_activation_time($roleKey));
		$siteId                    = get_current_blog_id();
		$blogPrefix                = $wpdb->get_blog_prefix($siteId);
		$usermeta                  = $wpdb->usermeta;
		$users                     = $wpdb->users;
		$secrets                   = Controller_DB::shared()->secrets;
		$admin                     = Controller_Permissions::shared()->can_role_manage_settings($roleKey);
		$parameters                = array(
			self::META_KEY_GRACE_PERIOD_RESET,
			self::META_KEY_GRACE_PERIOD_OVERRIDE,
		);
		$grace_periodClause         = "IF(overrides.days IS NULL, $grace_period_seconds, overrides.days * $secondsPerDay)";
		$registeredTimestampClause = "UNIX_TIMESTAMP(CONVERT_TZ($users.user_registered, '+00:00', @@time_zone))";
		$now                       = time();
		if ($admin) {
			$allowancesJoin    = <<<SQL
				LEFT JOIN (
					SELECT
						user_id,
						meta_value AS allowed
					FROM
						$usermeta
					WHERE
						meta_key = %s
				) allowances ON allowances.user_id = $usermeta.user_id
SQL;
			$parameters[]      = self::META_KEY_ALLOW_GRACE_PERIOD;
			$allowed_clause     = 'IFNULL(allowances.allowed, 0)';
			$grace_periodClause = "IF($allowed_clause = 0, 0, $grace_periodClause)";
		} else {
			$allowancesJoin = null;
			$allowed_clause  = null;
		}
		$timeClause = "GREATEST($roleTime, $registeredTimestampClause, IFNULL(resets.time, 0)) + $grace_periodClause";
		$query      = <<<SQL
			SELECT
				$usermeta.user_id,
				$users.user_login,
				$timeClause AS required_at
			FROM
				$usermeta
				JOIN $users ON $users.ID = $usermeta.user_id
				LEFT JOIN (
					SELECT
						user_id,
						meta_value AS time
					FROM
						$usermeta
					WHERE
						meta_key = %s
				) resets ON resets.user_id = $usermeta.user_id
				LEFT JOIN (
					SELECT
						user_id,
						meta_value AS days
					FROM
						$usermeta
					WHERE
						meta_key = %s
				) overrides ON overrides.user_id = $usermeta.user_id
				$allowancesJoin
			WHERE
				meta_key = '{$blogPrefix}capabilities'
				AND meta_value LIKE %s
				AND NOT $usermeta.user_id IN(SELECT user_id FROM {$secrets})
SQL;
		$conditions = array();
		$operator   = 'AND';
		if (null !== $grace_period) {
			if ($grace_period) {
				$conditions[] = "$timeClause > $now";
			} else {
				$conditions[] = "$timeClause <= $now";
				$operator     = 'OR';
			}
		}
		if ($admin) {
			$conditions[] = $allowed_clause . ' = ' . ($grace_period ? 1 : 0);
		}
		if (array() !== $conditions) {
			$query .= ' AND (' . implode(" $operator ", $conditions) . ')';
		}
		if (null !== $page && null !== $per_page) {
			$offset = (int) (($page - 1) * $per_page);
			$limit  = (int) ($per_page + 1);
			if ($offset >= 0 && $per_page > 0) {
				$query .= " LIMIT $offset, $limit";
			}
		}
		$serializedRoleKey = serialize($roleKey);
		$roleMatch         = '%' . (method_exists($wpdb, 'esc_like') ? $wpdb->esc_like($serializedRoleKey) : addcslashes($serializedRoleKey, '_%\\')) . '%';
		$parameters[]      = $roleMatch;
		return $wpdb->prepare(
			$query . ';',
			$parameters
		);
	}

	public function get_inactive_2fa_users($roleKey, $grace_period = null, $page = null, $per_page = null, &$lastPage = null)
	{
		global $wpdb;
		if (is_multisite() && 'super-admin' === $roleKey) {
			$super_admins = $this->get_inactive_2fa_super_admins($grace_period);
			if (null !== $page && null !== $per_page) {
				$start       = ($page - 1) * $per_page;
				$end         = $start + $per_page;
				$lastPage    = $end >= count($super_admins);
				$super_admins = array_slice($super_admins, $start, $per_page);
			}
			return $super_admins;
		}
		$query   = $this->generate_inactive_2fa_user_query($roleKey, $grace_period, $page, $per_page);
		$results = $wpdb->get_results($query);
		if (count($results) > $per_page) {
			$lastPage = false;
			array_pop($results);
		} else {
			$lastPage = true;
		}
		return $results;
	}

	private function get_verification_token_transient_key(string $hash): string
	{
		return self::VERIFICATION_TOKEN_TRANSIENT_PREFIX . $hash;
	}

	private function load_verification_token($hash): ?int
	{
		$key    = $this->get_verification_token_transient_key($hash);
		$user_id = get_transient($key);
		if (false === $user_id) {
			return null;
		}
		return intval($user_id);
	}

	/**
	 * @return mixed[]
	 */
	private function load_verification_tokens($user): array
	{
		$stored_hashes = get_user_meta($user->ID, self::META_KEY_VERIFICATION_TOKENS, true);
		$validHashes  = array();
		if (is_array($stored_hashes)) {
			foreach ($stored_hashes as $hash) {
				$user_id = $this->load_verification_token($hash);
				if ($user_id === $user->ID) {
					$validHashes[] = $hash;
				}
			}
		}
		return $validHashes;
	}

	private function hash_verification_token($token)
	{
		return wp_hash($token);
	}

	public function generate_verification_token($user): string
	{
		$token  = Model_Crypto::random_bytes(self::VERIFICATION_TOKEN_BYTES);
		$hash   = $this->hash_verification_token($token);
		$tokens = $this->load_verification_tokens($user);
		array_unshift($tokens, $hash);
		$token_count = count($tokens);
		while ($token_count > self::VERIFICATION_TOKEN_LIMIT) {
			$excessHash = array_pop($tokens);
			delete_transient($this->get_verification_token_transient_key($excessHash));
			--$token_count;
		}
		$key = $this->get_verification_token_transient_key($hash);
		set_transient($key, $user->ID, TFA_LS_EMAIL_VALIDITY_DURATION_MINUTES * 60);
		update_user_meta($user->ID, self::META_KEY_VERIFICATION_TOKENS, $tokens);
		return base64_encode($token);
	}

	public function validate_verification_token($token, $user = null): bool
	{
		$hash   = $this->hash_verification_token(base64_decode($token));
		$user_id = $this->load_verification_token($hash);
		return null !== $user_id && (null === $user || $user_id === $user->ID);
	}

	public function get_user_count()
	{
		global $wpdb;
		if (function_exists('get_user_count')) {
			return get_user_count();
		}
		return $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
	}

	public function has_large_user_base(): bool
	{
		return $this->get_user_count() >= self::LARGE_USER_BASE_THRESHOLD;
	}

	public function should_force_user_counts(): bool
	{
		return isset($_GET['wfls-show-user-counts']);
	}

	public function get_detailed_user_counts_if_enabled()
	{
		$force = $this->should_force_user_counts();
		if ($this->has_large_user_base() && ! $force) {
			return null;
		}
		return $this->detailed_user_counts($force);
	}
}
