<?php

/**
 * Stylesheet asset model.
 *
 * @package TFAuthLS
 */

namespace TFAuthLS;

/**
 * Represents a stylesheet asset.
 */
class Model_Style extends Model_Asset
{



	/**
	 * Enqueues the stylesheet.
	 */
	public function enqueue(): void
	{
		if ($this->registered) {
			wp_enqueue_style($this->handle);
		} else {
			wp_enqueue_style($this->handle, $this->source, $this->dependencies, $this->version);
		}
	}

	/**
	 * Returns whether the stylesheet is enqueued.
	 *
	 * @return bool Whether the stylesheet is enqueued.
	 */
	public function isEnqueued()
	{
		return wp_style_is($this->handle);
	}

	/**
	 * Renders an inline stylesheet link.
	 */
	public function renderInline(): void
	{
		if (empty($this->source)) {
			return;
		}
		$url      = esc_url($this->getSourceUrl());
		// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- This is the intentional inline fallback for a non-enqueued asset.
		$link_tag = "<link rel=\"stylesheet\" type=\"text/css\" href=\"{$url}\">";
?>
		<script type="text/javascript">
			jQuery('head').append(<?php echo wp_json_encode($link_tag); ?>);
		</script>
<?php
		// phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
	}

	/**
	 * Registers the stylesheet.
	 *
	 * @return static The registered asset.
	 */
	public function register()
	{
		wp_register_style($this->handle, $this->source, $this->dependencies, $this->version);
		return parent::register();
	}
}
