<?php

/**
 * Base asset model.
 *
 * @package TFAuthLS
 */

namespace TFAuthLS;

/**
 * Defines the shared behavior of JavaScript and stylesheet assets.
 */
abstract class Model_Asset
{



	/**
	 * Asset handle.
	 *
	 * @var string
	 */
	protected $handle;
	/**
	 * Asset source URL.
	 *
	 * @var string
	 */
	protected $source;
	/**
	 * Asset dependencies.
	 *
	 * @var string[]
	 */
	protected $dependencies;
	/**
	 * Asset version.
	 *
	 * @var string|bool
	 */
	protected $version;
	/**
	 * Whether the asset is registered.
	 *
	 * @var bool
	 */
	protected $registered = false;

	/**
	 * Creates an asset.
	 *
	 * @param string       $handle       Asset handle.
	 * @param string       $source       Asset source URL.
	 * @param string[]     $dependencies Asset dependencies.
	 * @param string|false $version      Asset version.
	 */
	final public function __construct($handle, $source = '', $dependencies = array(), $version = false)
	{
		$this->handle       = $handle;
		$this->source       = $source;
		$this->dependencies = $dependencies;
		$this->version      = $version;
	}

	// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Maintains the established asset API.
	/**
	 * Returns the asset source URL, including its version when available.
	 *
	 * @return string|null
	 */
	public function getSourceUrl()
	{
		if (empty($this->source)) {
			return null;
		}
		if (is_string($this->version)) {
			return add_query_arg('ver', $this->version, $this->source);
		}
		return $this->source;
	}

	/**
	 * Enqueues the asset.
	 */
	abstract public function enqueue();

	/**
	 * Determines whether the asset is enqueued.
	 *
	 * @return bool
	 */
	abstract public function isEnqueued();

	/**
	 * Renders the asset inline.
	 */
	abstract public function renderInline();

	/**
	 * Renders the asset inline unless it is already enqueued.
	 */
	public function renderInlineIfNotEnqueued(): void
	{
		if (! $this->isEnqueued()) {
			$this->renderInline();
		}
	}

	/**
	 * Marks the asset as registered.
	 *
	 * @return static
	 */
	public function setRegistered()
	{
		$this->registered = true;
		return $this;
	}

	/**
	 * Registers the asset.
	 *
	 * @return static
	 */
	public function register()
	{
		return $this->setRegistered();
	}

	/**
	 * Returns the URL of a plugin JavaScript file.
	 *
	 * @param string $file File name.
	 * @return string
	 */
	public static function js(string $file)
	{
		return self::plugin_base_url() . 'js/' . $file;
	}

	/**
	 * Returns the URL of a plugin stylesheet.
	 *
	 * @param string $file File name.
	 * @return string
	 */
	public static function css(string $file)
	{
		return self::plugin_base_url() . 'css/' . $file;
	}

	/**
	 * Returns the URL of a plugin image file.
	 *
	 * @param string $file File name.
	 * @return string
	 */
	public static function img(string $file)
	{
		return self::plugin_base_url() . 'img/' . $file;
	}

	/**
	 * Returns the base URL for plugin assets.
	 *
	 * @return string
	 */
	protected static function plugin_base_url()
	{
		return plugins_url('', TFA_LS_FCPATH) . '/';
	}

	/**
	 * Creates an asset instance.
	 *
	 * @param string       $handle       Asset handle.
	 * @param string       $source       Asset source URL.
	 * @param string[]     $dependencies Asset dependencies.
	 * @param string|false $version      Asset version.
	 * @return static
	 */
	public static function create($handle, $source = '', $dependencies = array(), $version = false)
	{
		return new static($handle, $source, $dependencies, $version);
	}
	// phpcs:enable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
}
