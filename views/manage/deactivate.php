<?php
if (!defined('TFA_LS_VERSION')) {
	exit;
}
/**
 * @var \WP_User $user The user being edited. Required.
 */

$ownAccount = false;
$ownUser = wp_get_current_user();
if ($ownUser->ID == $user->ID) {
	$ownAccount = true;
}
?>
<div class="wfls-block wfls-always-active wfls-flex-item-full-width">
	<div class="wfls-block-header wfls-block-header-border-bottom">
		<div class="wfls-block-header-content">
			<div class="wfls-block-title">
				<strong><?php esc_html_e('2FA Active', '2fa-login-security'); ?></strong>
			</div>
		</div>
	</div>
	<div class="wfls-block-content wfls-padding-add-bottom">
		<p><?php if ($ownAccount) {
				esc_html_e('Two-factor authentication is currently active on your account. You may deactivate it by clicking the button below.', '2fa-login-security');
			} else {
				echo wp_kses(sprintf(/* translators: Username */__('Two-factor authentication is currently active on the account <strong>%s</strong>. You may deactivate it by clicking the button below.', '2fa-login-security'), esc_html($user->user_login)), array('strong' => array()));
			} ?></p>
		<p class="wfls-center wfls-add-top"><a href="#" class="wfls-btn wfls-btn-default" id="wfls-deactivate" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Deactivate', '2fa-login-security'); ?></a></p>
	</div>
</div>
<div style="display: none;">
	<?php
	echo \TFAuthLS\Model_View::create('common/modal-prompt', array(
		'id' => 'wfls-template-deactivate-prompt',
		'title' => __('Deactivate 2FA', '2fa-login-security'),
		'message' => __('Are you sure you want to deactivate two-factor authentication?', '2fa-login-security'),
		'primaryButton' => array('class' => 'wfls-deactivate-prompt-cancel', 'label' => __('Cancel', '2fa-login-security'), 'link' => '#'),
		'secondaryButtons' => array(array('class' => 'wfls-deactivate-prompt-confirm', 'label' => __('Deactivate', '2fa-login-security'), 'link' => '#')),
	))->render();
	?>
</div>
<script type="application/javascript">
	(function($) {
		$(function() {
			$('#wfls-deactivate').on('click', function(e) {
				e.preventDefault();
				e.stopPropagation();

				var content = $("#wfls-template-deactivate-prompt").clone().attr('id', null);
				WFLS.standaloneModalHTML(content, {
					onOpen: function(modal) {
						$(modal).find('.wfls-deactivate-prompt-cancel').on('click', WFLS.closeStandaloneModal);
						$(modal).find('.wfls-deactivate-prompt-confirm').on('click', function(e) {
							e.preventDefault();
							e.stopPropagation();

							var payload = {
								user: <?php echo (int) $user->ID; ?>,
							};

							WFLS.ajax(
								'TFA_LS_deactivate',
								payload,
								function(response) {
									if (response.error) {
										WFLS.standaloneModal('<?php echo \TFAuthLS\Text\Model_JavaScript::esc_js(__('Error Deactivating 2FA', '2fa-login-security')); ?>', response.error);
									} else {
										WFLS.closeStandaloneModal();
										$('#wfls-deactivation-controls').crossfade($('#wfls-activation-controls'));
									}
								},
								function(error) {
									WFLS.standaloneModal('<?php echo \TFAuthLS\Text\Model_JavaScript::esc_js(__('Error Deactivating 2FA', '2fa-login-security')); ?>', '<?php echo \TFAuthLS\Text\Model_JavaScript::esc_js(__('An error was encountered while trying to deactivate two-factor authentication. Please try again.', '2fa-login-security')); ?>');
								}
							);
						});
					}
				});
			});
		});
	})(jQuery);
</script>