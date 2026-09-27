<?php

/**
 * View rendering model.
 *
 * @package TFAuthLS
 */

namespace TFAuthLS;

/**
 * Renders plugin view templates with supplied data.
 */
class Model_View
{


	/**
	 * Base directory for view templates.
	 *
	 * @var string
	 */
	protected string $path;

	/**
	 * View file extension.
	 *
	 * @var string
	 */
	protected $file_extension = '.php';

	/**
	 * Requested view name.
	 *
	 * @var string
	 */
	protected $view;

	/**
	 * Data exposed to the view template.
	 *
	 * @var array
	 */
	protected $data;

	/**
	 * Equivalent to the constructor but allows for call chaining.
	 *
	 * @param string $view View name.
	 * @param array  $data View data.
	 */
	public static function create($view, $data = array()): self
	{
		return new self($view, $data);
	}

	/**
	 * Creates a view renderer.
	 *
	 * @param string $view View name.
	 * @param array  $data View data.
	 */
	public function __construct($view, $data = array())
	{
		$this->path = TFA_LS_PATH . 'views';
		$this->view = $view;
		$this->data = $data;
	}

	/**
	 * Renders the view template.
	 *
	 * @return string
	 * @throws ViewNotFoundException When the requested view does not exist.
	 */
	public function render(): string
	{
		$view = preg_replace('/\.{2,}/', '.', $this->view);
		$path = $this->path . '/' . $view . $this->file_extension;
		if (! file_exists($path)) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not rendered as HTML.
			throw new ViewNotFoundException('The view ' . $path . ' does not exist or is not readable.');
		}

		// phpcs:ignore PHPCompatibility.FunctionUse.RemovedFunctions.extractDeprecated, WordPress.PHP.DontExtract.extract_extract -- View data is intentionally exposed as template variables.
		extract($this->data, EXTR_SKIP);

		ob_start();
		// @noinspection PhpIncludeInspection
		include $path;
		$output = ob_get_clean();
		return false === $output ? '' : $output;
	}

	/**
	 * Renders the view as a string.
	 *
	 * @return string
	 */
	public function __toString(): string
	{
		try {
			return $this->render();
		} catch (ViewNotFoundException $e) {
			return defined('WP_DEBUG') && WP_DEBUG ? $e->getMessage() : 'The view could not be loaded.';
		}
	}

	// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Maintains the established view API.
	/**
	 * Adds data for template rendering.
	 *
	 * @param array $data View data.
	 * @return static
	 */
	public function addData($data): static
	{
		$this->data = array_merge($data, $this->data);
		return $this;
	}

	/**
	 * Returns the current view data.
	 *
	 * @return array
	 */
	public function getData()
	{
		return $this->data;
	}

	/**
	 * Replaces the view data.
	 *
	 * @param array $data View data.
	 * @return static
	 */
	public function setData($data): static
	{
		$this->data = $data;
		return $this;
	}

	/**
	 * Returns the requested view name.
	 *
	 * @return string
	 */
	public function getView()
	{
		return $this->view;
	}

	/**
	 * Sets the requested view name.
	 *
	 * @param string $view View name.
	 * @return static
	 */
	public function setView($view): static
	{
		$this->view = $view;
		return $this;
	}

	/**
	 * Prevent POP
	 */
	public function __wakeup()
	{
		$this->path           = TFA_LS_PATH . 'views';
		$this->view           = '';
		$this->data           = array();
		$this->file_extension = '.php';
	}
	// phpcs:enable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
}

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Exception belongs to the view model.
/**
 * Signals that a requested view template could not be found.
 */
class ViewNotFoundException extends \Exception {}
// phpcs:enable Generic.Files.OneObjectStructurePerFile.MultipleFound
