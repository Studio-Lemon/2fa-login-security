<?php

namespace TFAuthLS\Text;

/**
 * Represents text that is already HTML-safe and should not be encoded again.
 *
 * @package Wordfence2FA\Text
 */
class Model_HTML {

	private $_html;

	public static function esc_html( $content ) {
		if ( $content instanceof Model_HTML ) {
			return (string) $content;
		}
		return esc_html( $content );
	}

	public function __construct( $html ) {
		$this->_html = $html;
	}

	public function __toString(): string {
		return $this->_html;
	}
}
