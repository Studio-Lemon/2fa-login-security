<?php
/**
 * User summary table for the WordPress admin settings screen.
 *
 * @package 2fa-login-security
 * @var array|false|null $counts The counts to display, false if the last count failed, or null to hide user counts.
 */

if (! defined('TFA_LS_VERSION')) {
	exit;
}
?>
<div class="wfls-block wfls-always-active wfls-flex-item-full-width">
	<div class="wfls-block-header wfls-block-header-border-bottom">
		<div class="wfls-block-header-content">
			<div class="wfls-block-title">
				<h3><?php esc_html_e('User Summary', '2fa-login-security'); ?></h3>
			</div>
		</div>
		<div class="wfls-block-header-action wfls-block-header-action-text wfls-nowrap wfls-padding-add-right-responsive">
			<a href="users.php"><?php esc_html_e('Manage Users', '2fa-login-security'); ?></a>
		</div>
	</div>
	<?php if (is_array($counts)) : ?>
		<div class="wfls-block-content wfls-padding-no-left wfls-padding-no-right">
			<table class="wfls-table wfls-table-striped wfls-table-header-separators wfls-table-expanded wfls-no-bottom">
				<thead>
					<tr>
						<th><?php esc_html_e('Role', '2fa-login-security'); ?></th>
						<th class="wfls-center"><?php esc_html_e('Total Users', '2fa-login-security'); ?></th>
						<th class="wfls-center"><?php esc_html_e('2FA Active', '2fa-login-security'); ?></th>
						<th class="wfls-center"><?php esc_html_e('2FA Inactive', '2fa-login-security'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					$roles                    = new WP_Roles();
					$role_names               = $roles->get_names();
					$role_names['super-admin'] = __('Super Administrator', '2fa-login-security');
					$role_names[ \TFAuthLS\Controller_Users::TRUNCATED_ROLE_KEY ] = __('Custom Capabilities / Multiple Roles', '2fa-login-security');
					foreach ($counts['avail_roles'] as $role_tag => $count) :
						$active_count   = (isset($counts['active_avail_roles'][ $role_tag ]) ? $counts['active_avail_roles'][ $role_tag ] : 0);
						$inactive_count = $count - $active_count;
						if (0 === $active_count && 0 === $inactive_count) {
							continue;
						}
						$role_name         = $role_names[ $role_tag ];
						$required_at       = \TFAuthLS\Controller_Settings::shared()->get_required_2fa_role_activation_time($role_tag);
						$inactive          = 0 < $inactive_count && false !== $required_at;
						$view_users_base_url = 'admin.php?' . http_build_query(
							array(
								'page' => 'WFLS',
								'role' => $role_tag,
							)
						);
						?>
						<tr>
							<td><?php echo esc_html(translate_user_role($role_name)); ?></td>
							<td class="wfls-center"><?php echo number_format($count); ?></td>
							<td class="wfls-center"><?php echo number_format($active_count); ?></td>
							<td class="wfls-center">
								<?php
								if ($inactive) :
									?>
									<a href="<?php echo esc_url(is_multisite() ? network_admin_url($view_users_base_url) : admin_url($view_users_base_url)); ?>"><?php endif ?>
									<?php echo number_format($inactive_count); ?>
									<?php
									if ($inactive) :
										?>
										(<?php esc_html_e('View users', '2fa-login-security'); ?>)</a><?php endif ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr>
						<th><?php esc_html_e('Total', '2fa-login-security'); ?></th>
						<th class="wfls-center"><?php echo number_format($counts['total_users']); ?></th>
						<th class="wfls-center"><?php echo number_format($counts['active_total_users']); ?></th>
						<th class="wfls-center"><?php echo number_format($counts['total_users'] - $counts['active_total_users']); ?></th>
					</tr>
					<?php if (is_multisite()) : ?>
						<tr>
							<td colspan="4" class="wfls-text-small"><?php esc_html_e('* User counts currently only reflect the main site on multisite installations.', '2fa-login-security'); ?></td>
						</tr>
					<?php endif; ?>
				</tfoot>
			</table>
		</div>
	<?php else : ?>
		<div class="wfls-block-content wfls-padding-add-bottom">
			<p><?php null === $counts ? esc_html_e('User counts are hidden by default on sites with large numbers of users in order to improve performance.', '2fa-login-security') : esc_html_e('User counts are currently disabled as the most recent attempt to count users failed to complete successfully.', '2fa-login-security'); ?></p>
			<a href="<?php echo esc_url(add_query_arg('wfls-show-user-counts', 'true') . '#top#settings'); ?>" class="wfls-btn wfls-btn-sm wfls-btn-primary"
				<?php
				if (\TFAuthLS\Controller_Users::shared()->should_force_user_counts()) :
					?>
				onclick="window.location.reload()" <?php endif ?>><?php null === $counts ? esc_html_e('Show User Counts', '2fa-login-security') : esc_html_e('Try Again', '2fa-login-security'); ?></a>
		</div>
	<?php endif ?>
</div>