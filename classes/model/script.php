<?php

/**
 * JavaScript asset model.
 *
 * @package TFAuthLS
 */

namespace TFAuthLS;

/**
 * Registers, enqueues, and renders plugin JavaScript assets.
 */
class Model_Script extends Model_Asset
{


	/**
	 * JavaScript translation values.
	 *
	 * @var array
	 */
	private array $translations = array();
	/**
	 * Name of the JavaScript translation object.
	 *
	 * @var string|null
	 */
	private $translation_object_name;

	/**
	 * Enqueues the script.
	 */
	public function enqueue(): void
	{
		if ($this->registered) {
			wp_enqueue_script($this->handle);
		} else {
			wp_enqueue_script($this->handle, $this->source, $this->dependencies, $this->version, false);
		}
		if ($this->translation_object_name && ! empty($this->translations)) {
			wp_localize_script($this->handle, $this->translation_object_name, $this->translations);
		}
	}

	/**
	 * Determines whether the script is enqueued.
	 *
	 * @return bool
	 */
	public function isEnqueued()
	{
		return wp_script_is($this->handle);
	}

	/**
	 * Renders the script tag inline.
	 */
	public function renderInline(): void
	{
		if (empty($this->source)) {
			return;
		}
		// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript -- This is the intentional inline fallback for a non-enqueued asset.
		?>
		<script type="text/javascript" src="<?php echo esc_url($this->getSourceUrl()); ?>"></script>
		<?php
		// phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedScript
	}

	/**
	 * Registers the script.
	 *
	 * @return static
	 */
	public function register()
	{
		wp_register_script($this->handle, $this->source, $this->dependencies, $this->version, false);
		return parent::register();
	}

	/**
	 * Adds a translated placeholder value.
	 *
	 * @param string $placeholder Placeholder name.
	 * @param string $translation Translated value.
	 * @return static
	 */
	public function withTranslation($placeholder, $translation): static
	{
		$this->translations[$placeholder] = $translation;
		return $this;
	}

	/**
	 * Sets all translated placeholder values.
	 *
	 * @param array $translations Translated values keyed by placeholder.
	 * @return static
	 */
	public function withTranslations($translations): static
	{
		$this->translations = $translations;
		return $this;
	}

	/**
	 * Sets the JavaScript translation object name.
	 *
	 * @param string $name Translation object name.
	 * @return static
	 */
	public function setTranslationObjectName($name): static
	{
		$this->translation_object_name = $name;
		return $this;
	}
}
