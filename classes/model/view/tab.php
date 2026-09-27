<?php

/**
 * View tab model.
 *
 * @package TFAuthLS
 */

// phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore -- Legacy protected property names are retained for compatibility.

namespace TFAuthLS\View;

/**
 * Represents a tab in the UI.
 *
 * @package LS2FA\View
 * @property string $id
 * @property string $a
 * @property string $tabTitle
 * @property string $pageTitle
 * @property bool $active
 */
class Model_Tab
{


	/**
	 * Tab identifier.
	 *
	 * @var string
	 */
	protected $_id;
	/**
	 * Tab URL or anchor.
	 *
	 * @var string
	 */
	protected $_a;
	/**
	 * Visible tab title.
	 *
	 * @var string
	 */
	protected $_tab_title;
	/**
	 * Page title.
	 *
	 * @var string
	 */
	protected $_page_title;
	/**
	 * Whether the tab is active.
	 *
	 * @var bool
	 */
	protected $_active;

	/**
	 * Creates a tab model.
	 *
	 * @param string $id Tab ID.
	 * @param string $a Tab URL or anchor.
	 * @param string $tab_title Visible tab title.
	 * @param string $page_title Page title.
	 * @param bool   $active Whether the tab is active.
	 */
	public function __construct($id, $a, $tab_title, $page_title, $active = false)
	{
		$this->_id        = $id;
		$this->_a         = $a;
		$this->_tab_title  = $tab_title;
		$this->_page_title = $page_title;
		$this->_active    = $active;
	}

	/**
	 * Returns a public tab property.
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
			case 'a':
				return $this->_a;
			case 'tabTitle':
				return $this->_tab_title;
			case 'pageTitle':
				return $this->_page_title;
			case 'active':
				return $this->_active;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not rendered as HTML.
		throw new \OutOfBoundsException('Invalid key: ' . $name);
	}
}
