<?php
/**
 * Role users page.
 *
 * @package TFAuthLS
 */

if (! defined('TFA_LS_VERSION')) {
	exit;
}
?>
<?php if (is_multisite()) : ?>
	<p><em>(<?php esc_html_e('This page only shows users and roles on the main site of this network', '2fa-login-security'); ?>)</em></p>
<?php endif ?>
<div class="wfls-block wfls-always-active wfls-flex-item-full-width wfls-add-bottom">
	<?php if (false === $required_at) : ?>
		<div class="wfls-block-content">
			<p><?php echo esc_html(sprintf( /* translators: Role name */__('2FA is not required for the %s role', '2fa-login-security'), $role_title)); ?></p>
		</div>
	<?php elseif (empty($users)) : ?>
		<div class="wfls-block-content">
			<p>
				<?php if (1 === $page) : ?>
					<?php echo esc_html(sprintf( /* translators: 1. 2FA state; 2. Role name */__('No users found in the %1$s state for the %2$s role', '2fa-login-security'), $state_title, $role_title)); ?>
				<?php else : ?>
					<?php echo esc_html(sprintf( /* translators: page number */__('Page %d is out of range', '2fa-login-security'), $page)); ?>
				<?php endif ?>
			</p>
		</div>
	<?php else : ?>
		<table class="wfls-table wfls-table-striped wfls-table-header-separators wfls-table-expanded wfls-no-bottom">
			<tr>
				<th>User</th>
				<th>Required Date</th>
			</tr>
			<?php foreach ($users as $user) : ?>
				<tr>
					<th scope="row"><a href="<?php echo esc_url(get_edit_user_link($user->user_id)); ?>#wfls-user-settings"><?php echo esc_html($user->user_login); ?></a></td>
					<td>
						<?php if ($user->required_at) : ?>
							<?php echo esc_html(\TFAuthLS\Controller_Time::format_local_time('F j, Y g:i A', $user->required_at)); ?>
						<?php else : ?>
							<?php esc_html_e('N/A', '2fa-login-security'); ?>
						<?php endif ?>
					</td>
				</tr>
			<?php endforeach ?>
			<?php if (1 !== $page || ! $last_page) : ?>
				<tr>
					<td colspan="2" class="wfls-center">
						<?php if ($page > 1) : ?>
							<a href="<?php echo esc_url(add_query_arg($page_key, $page - 1) . "#$state_key"); ?>"><span class="dashicons dashicons-arrow-left-alt2"></span></a>
						<?php endif ?>
						<strong class="wfls-page-indicator"><?php esc_html_e('Page ', '2fa-login-security'); ?><?php echo (int) $page; ?></strong>
						<?php if (! $last_page) : ?>
							<a href="<?php echo esc_url(add_query_arg($page_key, $page + 1) . "#$state_key"); ?>"><span class="dashicons dashicons-arrow-right-alt2"></span></a>
						<?php endif ?>
					</td>
				</tr>
			<?php endif ?>
		</table>
	<?php endif ?>
</div>