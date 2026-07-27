<?php
if (!defined('WORDFENCE_LS_VERSION')) { exit; }
$settings = \WordfenceLS\Controller_Settings::shared();
$roles = new \WP_Roles();

$stateLabel = array(
	\WordfenceLS\Controller_Settings::STATE_2FA_DISABLED => __('Disabled', 'wordfence-login-security'),
	\WordfenceLS\Controller_Settings::STATE_2FA_OPTIONAL => __('Optional', 'wordfence-login-security'),
	\WordfenceLS\Controller_Settings::STATE_2FA_REQUIRED => __('Required', 'wordfence-login-security'),
);

$currentRoleState = function($roleName, $roleObject = null) use ($settings) {
	if ($settings->get_required_2fa_role_activation_time($roleName) !== false) {
		return \WordfenceLS\Controller_Settings::STATE_2FA_REQUIRED;
	}
	if ($roleName === 'super-admin') {
		return \WordfenceLS\Controller_Settings::STATE_2FA_OPTIONAL;
	}
	if ($roleObject && $roleObject->has_cap(\WordfenceLS\Controller_Permissions::CAP_ACTIVATE_2FA_SELF)) {
		return \WordfenceLS\Controller_Settings::STATE_2FA_OPTIONAL;
	}
	return \WordfenceLS\Controller_Settings::STATE_2FA_DISABLED;
};
?>

<?php if (isset($_GET['wfls_settings_saved'])): ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings updated.', 'wordfence-login-security'); ?></p></div>
<?php endif; ?>
<?php if (isset($_GET['wfls_settings_error'])): ?>
	<div class="notice notice-error"><p><?php esc_html_e('One or more settings were invalid. Please review your values and try again.', 'wordfence-login-security'); ?></p></div>
<?php endif; ?>

<form method="post" action="<?php echo esc_url(self_admin_url('admin-post.php')); ?>">
	<?php wp_nonce_field('wfls-save-settings', 'wfls-settings-nonce'); ?>
	<input type="hidden" name="action" value="wfls_save_settings">

	<div class="wfls-save-banner wfls-nowrap wfls-padding-add-right-responsive">
		<button type="submit" class="button button-primary"><?php esc_html_e('Save Settings', 'wordfence-login-security'); ?></button>
	</div>

	<div id="wfls-settings" class="wfls-flex-row wfls-flex-row-wrappable wfls-flex-row-equal-heights">
	<!-- begin status content -->
	<div id="wfls-user-stats" class="wfls-flex-row wfls-flex-row-equal-heights wfls-flex-item-xs-100">
		<?php
			echo \WordfenceLS\Model_View::create('settings/user-stats', array(
				'counts' => \WordfenceLS\Controller_Users::shared()->get_detailed_user_counts_if_enabled(),
			))->render();
		?>
	</div>
	<!-- end status content -->
	<!-- begin options content -->
	<div id="wfls-options" class="wfls-flex-item-xs-100">
		<table class="form-table" role="presentation">
			<tbody>
			<tr>
				<th scope="row"><?php esc_html_e('Remember Device', 'wordfence-login-security'); ?></th>
				<td><label><input type="checkbox" name="wfls_settings[remember-device]" value="1" <?php checked($settings->get_bool(\WordfenceLS\Controller_Settings::OPTION_REMEMBER_DEVICE_ENABLED)); ?>> <?php esc_html_e('Allow remembering device for trusted sessions', 'wordfence-login-security'); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e('Remember Duration (days)', 'wordfence-login-security'); ?></th>
				<td><input type="number" min="1" name="wfls_settings[remember-device-duration-days]" value="<?php echo esc_attr(max(1, (int) floor($settings->get_int(\WordfenceLS\Controller_Settings::OPTION_REMEMBER_DEVICE_DURATION, 30 * 86400) / 86400))); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e('XML-RPC 2FA', 'wordfence-login-security'); ?></th>
				<td><label><input type="checkbox" name="wfls_settings[xmlrpc-enabled]" value="1" <?php checked($settings->get_bool(\WordfenceLS\Controller_Settings::OPTION_XMLRPC_ENABLED)); ?>> <?php esc_html_e('Require 2FA for XML-RPC authentication', 'wordfence-login-security'); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e('Allow XML-RPC Authentication', 'wordfence-login-security'); ?></th>
				<td><label><input type="checkbox" name="wfls_settings[allow-xml-rpc]" value="1" <?php checked($settings->get_bool(\WordfenceLS\Controller_Settings::OPTION_ALLOW_XML_RPC, true)); ?>> <?php esc_html_e('Allow XML-RPC authentication endpoint', 'wordfence-login-security'); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e('2FA Grace Period (days)', 'wordfence-login-security'); ?></th>
				<td><input type="number" min="0" max="99" name="wfls_settings[2fa-user-grace-period]" value="<?php echo esc_attr($settings->get_user_2fa_grace_period()); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e('IP Source', 'wordfence-login-security'); ?></th>
				<td>
					<select name="wfls_settings[ip-source]">
						<?php $ipSource = $settings->get(\WordfenceLS\Controller_Settings::OPTION_IP_SOURCE, \WordfenceLS\Model_Request::IP_SOURCE_AUTOMATIC); ?>
						<option value="" <?php selected($ipSource, ''); ?>><?php esc_html_e('Automatic', 'wordfence-login-security'); ?></option>
						<option value="REMOTE_ADDR" <?php selected($ipSource, 'REMOTE_ADDR'); ?>>REMOTE_ADDR</option>
						<option value="HTTP_X_FORWARDED_FOR" <?php selected($ipSource, 'HTTP_X_FORWARDED_FOR'); ?>>HTTP_X_FORWARDED_FOR</option>
						<option value="HTTP_X_REAL_IP" <?php selected($ipSource, 'HTTP_X_REAL_IP'); ?>>HTTP_X_REAL_IP</option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e('Trusted Proxies', 'wordfence-login-security'); ?></th>
				<td>
					<textarea name="wfls_settings[ip-trusted-proxies]" rows="5" cols="50"><?php echo esc_textarea($settings->inflate(\WordfenceLS\Controller_Settings::OPTION_IP_TRUSTED_PROXIES, $settings->get(\WordfenceLS\Controller_Settings::OPTION_IP_TRUSTED_PROXIES, ''))); ?></textarea>
					<p class="description"><?php esc_html_e('One IP or CIDR/range per line.', 'wordfence-login-security'); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e('Login History Columns', 'wordfence-login-security'); ?></th>
				<td><label><input type="checkbox" name="wfls_settings[enable-login-history-columns]" value="1" <?php checked($settings->get_bool(\WordfenceLS\Controller_Settings::OPTION_ENABLE_LOGIN_HISTORY_COLUMNS, true)); ?>> <?php esc_html_e('Show login history columns in user tables', 'wordfence-login-security'); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e('Stack UI Columns', 'wordfence-login-security'); ?></th>
				<td><label><input type="checkbox" name="wfls_settings[stack-ui-columns]" value="1" <?php checked($settings->get_bool(\WordfenceLS\Controller_Settings::OPTION_STACK_UI_COLUMNS, true)); ?>> <?php esc_html_e('Use stacked layout on narrow screens', 'wordfence-login-security'); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e('Use NTP Clock Sync', 'wordfence-login-security'); ?></th>
				<td><label><input type="checkbox" name="wfls_settings[use-ntp]" value="1" <?php checked($settings->get_bool(\WordfenceLS\Controller_Settings::OPTION_USE_NTP, true)); ?>> <?php esc_html_e('Enable network time correction for TOTP verification', 'wordfence-login-security'); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e('Delete on Deactivation', 'wordfence-login-security'); ?></th>
				<td><label><input type="checkbox" name="wfls_settings[delete-deactivation]" value="1" <?php checked($settings->get_bool(\WordfenceLS\Controller_Settings::OPTION_DELETE_ON_DEACTIVATION, false)); ?>> <?php esc_html_e('Delete plugin data when deactivating', 'wordfence-login-security'); ?></label></td>
			</tr>
			</tbody>
		</table>

		<h2><?php esc_html_e('2FA Role Requirements', 'wordfence-login-security'); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e('Role', 'wordfence-login-security'); ?></th>
					<th><?php esc_html_e('2FA State', 'wordfence-login-security'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if (is_multisite()): ?>
					<tr>
						<td><?php esc_html_e('Super Administrator', 'wordfence-login-security'); ?></td>
						<td>
							<?php $state = $currentRoleState('super-admin', null); ?>
							<select name="wfls_settings[enabled-roles.super-admin]">
								<option value="optional" <?php selected($state, 'optional'); ?>><?php echo esc_html($stateLabel['optional']); ?></option>
								<option value="required" <?php selected($state, 'required'); ?>><?php echo esc_html($stateLabel['required']); ?></option>
							</select>
						</td>
					</tr>
				<?php endif; ?>
				<?php foreach ($roles->roles as $roleName => $roleData): ?>
					<?php $role = $roles->get_role($roleName); ?>
					<?php if (!$role): continue; endif; ?>
					<?php $state = $currentRoleState($roleName, $role); ?>
					<tr>
						<td><?php echo esc_html($roleData['name']); ?></td>
						<td>
							<select name="wfls_settings[enabled-roles.<?php echo esc_attr($roleName); ?>]">
								<option value="disabled" <?php selected($state, 'disabled'); ?>><?php echo esc_html($stateLabel['disabled']); ?></option>
								<option value="optional" <?php selected($state, 'optional'); ?>><?php echo esc_html($stateLabel['optional']); ?></option>
								<option value="required" <?php selected($state, 'required'); ?>><?php echo esc_html($stateLabel['required']); ?></option>
							</select>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<!-- end options content -->
</div>

	<p><button type="submit" class="button button-primary"><?php esc_html_e('Save Settings', 'wordfence-login-security'); ?></button></p>
</form>
