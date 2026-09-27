<?php
/**
 * Grace-period status template.
 *
 * @package TFAuthLS
 */

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
if (! defined('TFA_LS_VERSION')) {
	exit;
}
/**
 * Grace-period template variables.
 *
 * @var \WP_User $user The user being edited. Required.
 * @var bool $in_grace_period
 * @var bool $locked_out
 * @var int $required_at
 */

$own_account = false;
$own_user    = wp_get_current_user();
if ($own_user->ID === $user->ID) {
	$own_account = true;
}
$default_in_grace_period = \TFAuthLS\Controller_Settings::shared()->get_user_2fa_grace_period();
$has_grace_period        = $default_in_grace_period > 0;
?>
<div class="wfls-block wfls-always-active wfls-flex-item-full-width">
	<div class="wfls-block-header wfls-block-header-border-bottom">
		<div class="wfls-block-header-content">
			<div class="wfls-block-title">
				<strong><?php echo $in_grace_period ? esc_html__('Grace Period', '2fa-login-security') : esc_html__('Locked Out', '2fa-login-security'); ?></strong>
			</div>
		</div>
	</div>
	<div class="wfls-block-content">
		<?php if ($in_grace_period) : ?>
			<p>
				<?php
				$required_date_formatted = \TFAuthLS\Controller_Time::format_local_time('F j, Y g:i A', $required_at);
				echo $own_account ?
					sprintf(wp_kses( /* translators: Date */__('Two-factor authentication will be required for your account beginning <strong>%s</strong>', '2fa-login-security'), array( 'strong' => array() )), esc_html($required_date_formatted)) :
					sprintf(wp_kses( /* translators: 1. Username; 2. Date */__('Two-factor authentication will be required for user <strong>%1$s</strong> beginning <strong>%2$s</strong>.', '2fa-login-security'), array( 'strong' => array() )), esc_html($user->user_login), esc_html($required_date_formatted))
				?>
			</p>
			<?php if (\TFAuthLS\Controller_Users::shared()->has_revokable_grace_period($user)) : ?>
				<?php
				echo \TFAuthLS\Model_View::create(
					'common/revoke-grace-period',
					array(
						'user' => $user,
					)
				)->render()
				?>
			<?php endif ?>
		<?php else : ?>
			<p>
				<?php
				echo $own_account ?
					esc_html__('Two-factor authentication is required for your account, but has not been configured.', '2fa-login-security') :
					esc_html__('Two-factor authentication is required for this account, but has not been configured.', '2fa-login-security')
				?>
			</p>
			<?php
			echo \TFAuthLS\Model_View::create(
				'common/reset-grace-period',
				array(
					'user'                    => $user,
					'in_grace_period'         => $in_grace_period,
					'default_in_grace_period' => $default_in_grace_period,
				)
			)->render()
			?>
		<?php endif ?>
	</div>
</div>