<?php
/**
 * HTML-safe text model.
 *
 * @package LS2FA\Text
 */

namespace TFAuthLS\Text;

/**
 * Represents text that is already HTML-safe and should not be encoded again.
 *
 * @package LS2FA\Text
 */
class Model_HTML {



	/**
	 * HTML-safe text value.
	 *
	 * @var string
	 */
	private $_html;

	/**
	 * Escapes text unless it is already represented by this model.
	 *
	 * @param mixed $content Text to escape.
	 * @return string Escaped HTML text.
	 */
	public static function esc_html( $content ) {
		if ($content instanceof Model_HTML) {
			return (string) $content;
		}
		return esc_html($content);
	}

	/**
	 * Creates an HTML-safe text value.
	 *
	 * @param string $html HTML-safe text.
	 */
	public function __construct( $html ) {
		$this->_html = $html;
	}

	/**
	 * Returns the HTML-safe text value.
	 *
	 * @return string HTML-safe text.
	 */
	public function __toString(): string {
		return $this->_html;
	}
}
