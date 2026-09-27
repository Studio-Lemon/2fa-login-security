<?php

/**
 * View title model.
 *
 * @package TFAuthLS
 */

namespace TFAuthLS\View;

/**
 * Class Model_Title
 *
 * @package LS2FA\Page
 * @property-read string $id A valid DOM ID for the title.
 * @property-read string|\TFAuthLS\Text\Model_HTML $title The title text or HTML.
 * @property-read string|null $helpURL The help URL.
 * @property-read string|\TFAuthLS\Text\Model_HTML|null $helpLink The text/HTML of the help link.
 */
class Model_Title
{


	/**
	 * Title identifier.
	 *
	 * @var string
	 */
	private $_id;
	/**
	 * Title text.
	 *
	 * @var string|\TFAuthLS\Text\Model_HTML
	 */
	private $_title;
	/**
	 * Help URL.
	 *
	 * @var string|null
	 */
	private $_help_url;
	/**
	 * Help link text.
	 *
	 * @var string|\TFAuthLS\Text\Model_HTML|null
	 */
	private $_help_link;

	/**
	 * Creates a title model.
	 *
	 * @param string                                $id Title ID.
	 * @param string|\TFAuthLS\Text\Model_HTML      $title Title text.
	 * @param string|null                           $help_url Help URL.
	 * @param string|\TFAuthLS\Text\Model_HTML|null $help_link Help link text.
	 */
	public function __construct($id, $title, $help_url = null, $help_link = null)
	{
		$this->_id       = $id;
		$this->_title    = $title;
		$this->_help_url  = $help_url;
		$this->_help_link = $help_link;
	}

	/**
	 * Returns a public title property.
	 *
	 * @param string $name Property name.
	 * @return mixed Property value.
	 * @throws \OutOfBoundsException When the property is unknown.
	 */
	public function __get(string $name)
	{
		switch ($name) {
			case 'id':
				return $this->_id;
			case 'title':
				return $this->_title;
			case 'helpURL':
				return $this->_help_url;
			case 'helpLink':
				return $this->_help_link;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not rendered as HTML.
		throw new \OutOfBoundsException('Invalid key: ' . $name);
	}
}
