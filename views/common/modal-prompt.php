<?php
/**
 * Presents a modal prompt.
 *
 * @package TFAuthLS
 * @var string $id (optional) The CSS ID to apply to the modal.
 * @var string|\TFAuthLS\Text\Model_HTML $title The title for the prompt. Required.
 * @var string|\TFAuthLS\Text\Model_HTML $message The message for the prompt. Required.
 * @var array $primary_button The parameters for the primary button. The array is in the format array('id' => <element id>, 'label' => <button text>, 'link' => <href value>). Optional.
 * @var array $secondary_buttons The parameters for any secondary buttons. It is an array of arrays in the format array('id' => <element id>, 'label' => <button text>, 'link' => <href value>). The ordering of entries is the right-to-left order the buttons will be displayed. Optional.
 */

if (! defined('TFA_LS_VERSION')) {
	exit;
}

$title_html   = \TFAuthLS\Text\Model_HTML::esc_html($title);
$message_html = \TFAuthLS\Text\Model_HTML::esc_html($message);

if (! isset($secondary_buttons)) {
	$secondary_buttons = array();
}
$secondary_buttons = array_reverse($secondary_buttons);
?>
<div
	<?php
	if (! empty($id)) :
		?>
	id="<?php echo esc_attr($id); ?>" <?php endif; ?> class="wfls-modal">
	<div class="wfls-modal-header">
		<div class="wfls-modal-header-content">
			<div class="wfls-modal-title">
				<strong><?php echo wp_kses_post($title_html); ?></strong>
			</div>
		</div>
		<div class="wfls-modal-header-action">
			<div class="wfls-padding-add-left-small wfls-modal-header-action-close"><a href="#" onclick="WFLS.closeStandaloneModal(); return false"><i class="wfls-fa wfls-fa-times-circle" aria-hidden="true"></i></a></div>
		</div>
	</div>
	<div class="wfls-modal-content">
		<?php echo wp_kses_post($message_html); ?>
	</div>
	<div class="wfls-modal-footer">
		<ul class="wfls-flex-horizontal wfls-flex-align-right wfls-full-width">
			<?php foreach ($secondary_buttons as $button) : ?>
				<li class="wfls-padding-add-left-small"><a href="<?php echo esc_url($button['link']); ?>" class="wfls-btn <?php echo esc_attr(isset($button['type']) ? $button['type'] : 'wfls-btn-default'); ?> wfls-btn-callout-subtle
				<?php
				if (! empty($button['class'])) :
					?>
					<?php echo esc_attr($button['class']); ?><?php endif; ?>"
						<?php
						if (! empty($button['id'])) :
							?>
						id="<?php echo esc_attr($button['id']); ?>" <?php endif; ?>><?php echo wp_kses_post(isset($button['labelHTML']) ? $button['labelHTML'] : esc_html($button['label'])); ?></a></li>
			<?php endforeach; ?>
			<?php if (isset($primary_button) && is_array($primary_button)) : ?>
				<li class="wfls-padding-add-left-small"><a href="<?php echo esc_url($primary_button['link']); ?>" class="wfls-btn <?php echo esc_attr(isset($primary_button['type']) ? $primary_button['type'] : 'wfls-btn-primary'); ?> wfls-btn-callout-subtle
				<?php
				if (! empty($primary_button['class'])) :
					?>
					<?php echo esc_attr($primary_button['class']); ?><?php endif; ?>"
						<?php
						if (! empty($primary_button['id'])) :
							?>
						id="<?php echo esc_attr($primary_button['id']); ?>" <?php endif; ?>><?php echo wp_kses_post(isset($primary_button['labelHTML']) ? $primary_button['labelHTML'] : esc_html($primary_button['label'])); ?></a></li>
			<?php endif ?>
		</ul>
	</div>
</div>