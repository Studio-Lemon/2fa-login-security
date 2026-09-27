<?php

/**
 * Two-factor management page.
 *
 * @package TFAuthLS
 */

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
if (! defined('TFA_LS_VERSION')) {
	exit;
}

/**
 * Management page variables.
 *
 * @var \WP_User $user The user being edited. Required.
 * @var bool $can_edit_users Whether or not the viewer of the page can edit other users. Optional, defaults to false.
 */

if (! isset($can_edit_users)) {
	$can_edit_users = false;
}

$own_account = false;
$own_user    = wp_get_current_user();
if ($own_user->ID === $user->ID) {
	$own_account = true;
}

$enabled     = \TFAuthLS\Controller_Users::shared()->has_2fa_active($user);
$requires2fa = \TFAuthLS\Controller_Users::shared()->requires_2fa($user, $in_grace_period, $required_at);
$locked_out   = $requires2fa && ! $enabled;

?>
<p>
	<?php
	echo wp_kses(
		sprintf( /* translators: Support URL */__('Two-Factor Authentication, or 2FA, significantly improves login security for your website. 2FA Login Security works with a number of TOTP-based apps like Google Authenticator, FreeOTP, and Authy. For more details, <a href="%s" target="_blank" rel="noopener noreferrer">visit the plugin page</a>.', '2fa-login-security'), \TFAuthLS\Controller_Support::esc_support_url(\TFAuthLS\Controller_Support::ITEM_MODULE_LOGIN_SECURITY_2FA)),
		array(
			'a' => array(
				'href' => array(),
			),
		)
	);
	?>
</p>
<?php if ($can_edit_users) : ?>
	<div id="wfls-editing-display" class="wfls-flex-row wfls-flex-row-xs-wrappable wfls-flex-row-equal-heights">
		<div class="wfls-block wfls-always-active wfls-flex-item-full-width wfls-add-bottom">
			<div class="wfls-block-header wfls-block-header-border-bottom">
				<div class="wfls-block-header-content">
					<div class="wfls-block-title">
						<strong><?php echo wp_kses(sprintf( /* translators: 1. WordPress avatar tag; 2. WordPress username */__('Editing User:&nbsp;&nbsp;%1$s <span class="wfls-text-plain">%2$s</span>', '2fa-login-security'), get_avatar($user->ID, 16, '', $user->user_login), \TFAuthLS\Text\Model_HTML::esc_html($user->user_login) . ($own_account ? ' ' . __('(you)', '2fa-login-security') : '')), array('span' => array('class' => array()))); ?></strong>
					</div>
				</div>
			</div>
		</div>
	</div>
<?php endif; ?>
<div id="wfls-deactivation-controls" class="wfls-flex-row wfls-flex-row-wrappable wfls-flex-row-equal-heights"
	<?php
	if (! $enabled) {
		echo ' style="display: none;"';
	}
	?>>
	<!-- begin status content -->
	<div class="wfls-flex-row wfls-flex-row-equal-heights wfls-flex-item-xs-100">
		<?php
		echo \TFAuthLS\Model_View::create(
			'manage/deactivate',
			array(
				'user' => $user,
			)
		)->render();
		?>
	</div>
	<!-- end status content -->
	<!-- begin regenerate codes -->
	<div class="wfls-flex-row wfls-flex-row-equal-heights wfls-flex-item-xs-100">
		<?php
		echo \TFAuthLS\Model_View::create(
			'manage/regenerate',
			array(
				'user'      => $user,
				'remaining' => \TFAuthLS\Controller_Users::shared()->recovery_code_count($user),
			)
		)->render();
		?>
	</div>
	<!-- end regenerate codes -->
</div>
<div id="wfls-activation-controls" class="wfls-flex-row wfls-flex-row-xs-wrappable wfls-flex-row-equal-heights"
	<?php
	if ($enabled) {
		echo ' style="display: none;"';
	}
	?>>
	<?php
	$initialization_data = new \TFAuthLS\Model_2fainitialization_data($user);
	?>
	<!-- begin qr code -->
	<div class="wfls-flex-row wfls-flex-row-equal-heights wfls-col-sm-half-padding-right wfls-flex-item-xs-100 wfls-flex-item-sm-50">
		<?php
		echo \TFAuthLS\Model_View::create(
			'manage/code',
			array(
				'initialization_data' => $initialization_data,
			)
		)->render();
		?>
	</div>
	<!-- end qr code -->
	<!-- begin activation -->
	<div class="wfls-flex-row wfls-flex-row-equal-heights wfls-col-sm-half-padding-left wfls-flex-item-xs-100 wfls-flex-item-sm-50">
		<?php
		echo \TFAuthLS\Model_View::create(
			'manage/activate',
			array(
				'initialization_data' => $initialization_data,
			)
		)->render();
		?>
	</div>
	<!-- end activation -->
</div>
<div id="wfls-grace-period-controls" class="wfls-flex-row wfls-flex-row-xs-wrappable wfls-flex-row-equal-heights"
	<?php
	if ($enabled || ! ($locked_out || $in_grace_period)) {
		echo ' style="display: none;"';
	}
	?>>
	<div class="wfls-flex-row wfls-flex-row-equal-heights wfls-flex-item-xs-100 wfls-add-top">
		<?php
		echo \TFAuthLS\Model_View::create(
			'manage/grace-period',
			array(
				'user'            => $user,
				'locked_out'      => $locked_out,
				'in_grace_period' => $in_grace_period,
				'required_at'     => $required_at,
			)
		)->render();
		?>
	</div>
</div>
<?php
$time          = time();
$corrected_time = \TFAuthLS\Controller_Time::time($time);
$tz            = get_option('timezone_string');
if (empty($tz)) {
	$offset = get_option('gmt_offset');
	$tz     = 'UTC' . ($offset >= 0 ? '+' . $offset : $offset);
}
?>
<?php if (\TFAuthLS\Controller_Permissions::shared()->can_manage_settings()) : ?>
	<p><?php esc_html_e('Server Time:', '2fa-login-security'); ?> <?php echo esc_html(gmdate('Y-m-d H:i:s', $time)); ?> UTC (<?php echo esc_html(\TFAuthLS\Controller_Time::format_local_time('Y-m-d H:i:s', $time) . ' ' . $tz); ?>)<br>
		<?php esc_html_e('Browser Time:', '2fa-login-security'); ?> <script type="application/javascript">
			var date = new Date();
			document.write(date.toUTCString() + ' (' + date.toString() + ')');
		</script><br>
		<?php
		if (\TFAuthLS\Controller_Settings::shared()->is_ntp_enabled()) {
			echo esc_html__('Corrected Time (NTP):', '2fa-login-security') . ' ' . gmdate('Y-m-d H:i:s', $corrected_time) . ' UTC (' . \TFAuthLS\Controller_Time::format_local_time('Y-m-d H:i:s', $corrected_time) . ' ' . esc_html($tz) . ')<br>';
		}
		?>
		<?php esc_html_e('Detected IP:', '2fa-login-security'); ?> <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
																						?><?php echo \TFAuthLS\Text\Model_HTML::esc_html(\TFAuthLS\Model_Request::current()->ip()); ?></p>
<?php endif; ?>