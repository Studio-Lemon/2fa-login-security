<?php

/**
 * JavaScript-safe text model.
 *
 * @package LS2FA\Text
 */

// phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore -- Legacy private property names are retained for compatibility.

namespace TFAuthLS\Text;

/**
 * Represents text that is already JavaScript-safe and should not be encoded again.
 *
 * @package LS2FA\Text
 */
class Model_JavaScript
{



	/**
	 * JavaScript-safe text value.
	 *
	 * @var string
	 */
	private $_java_script;

	/**
	 * Returns a string escaped for use in JavaScript. This is almost identical in behavior to esc_js except that
	 * we don't call _wp_specialchars and keep \r rather than stripping it.
	 *
	 * @param string|Model_JavaScript $content Content to escape.
	 * @return string JavaScript-escaped content.
	 */
	public static function esc_js($content)
	{
		if ($content instanceof Model_HTML) {
			return (string) $content;
		}

		$safe_text = wp_check_invalid_utf8($content);
		$safe_text = preg_replace('/&#(x)?0*(?(1)27|39);?/i', "'", stripslashes($safe_text));
		$safe_text = str_replace("\r", '\\r', $safe_text);
		$safe_text = str_replace("\n", '\\n', addslashes($safe_text));
		return apply_filters('js_escape', $safe_text, $content);
	}

	/**
	 * Writes a JavaScript string literal.
	 *
	 * @param string|Model_JavaScript $value Value to write.
	 */
	public static function echo_string_literal($value): void
	{
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_js() escapes the JavaScript string literal.
		echo "'" . self::esc_js($value) . "'";
	}

	/**
	 * Creates a JavaScript-safe text value.
	 *
	 * @param string $java_script JavaScript-safe text.
	 */
	public function __construct($java_script)
	{
		$this->_java_script = $java_script;
	}

	/**
	 * Returns the JavaScript-safe text value.
	 *
	 * @return string JavaScript-safe text.
	 */
	public function __toString(): string
	{
		return $this->_java_script;
	}
}
