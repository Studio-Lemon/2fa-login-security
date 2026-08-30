<?php

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

	private $_id;
	private $_title;
	private $_helpURL;
	private $_helpLink;

	public function __construct($id, $title, $helpURL = null, $helpLink = null)
	{
		$this->_id       = $id;
		$this->_title    = $title;
		$this->_helpURL  = $helpURL;
		$this->_helpLink = $helpLink;
	}

	public function __get(string $name)
	{
		switch ($name) {
			case 'id':
				return $this->_id;
			case 'title':
				return $this->_title;
			case 'helpURL':
				return $this->_helpURL;
			case 'helpLink':
				return $this->_helpLink;
		}

		throw new \OutOfBoundsException('Invalid key: ' . $name);
	}
}
