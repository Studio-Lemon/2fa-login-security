<?php

namespace TFAuthLS;

abstract class Model_Asset
{


	protected $handle;
	protected $source;
	protected $dependencies;
	protected $version;
	protected $registered = false;

	final public function __construct($handle, $source = '', $dependencies = array(), $version = false)
	{
		$this->handle       = $handle;
		$this->source       = $source;
		$this->dependencies = $dependencies;
		$this->version      = $version;
	}

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

	abstract public function enqueue();

	abstract public function isEnqueued();

	abstract public function renderInline();

	public function renderInlineIfNotEnqueued(): void
	{
		if (! $this->isEnqueued()) {
			$this->renderInline();
		}
	}

	public function setRegistered()
	{
		$this->registered = true;
		return $this;
	}

	public function register()
	{
		return $this->setRegistered();
	}

	public static function js(string $file)
	{
		return self::_pluginBaseURL() . 'js/' . $file;
	}

	public static function css(string $file)
	{
		return self::_pluginBaseURL() . 'css/' . $file;
	}

	public static function img(string $file)
	{
		return self::_pluginBaseURL() . 'img/' . $file;
	}

	protected static function _pluginBaseURL()
	{
		return plugins_url('', TFA_LS_FCPATH) . '/';
	}

	public static function create($handle, $source = '', $dependencies = array(), $version = false)
	{
		return new static($handle, $source, $dependencies, $version);
	}
}
