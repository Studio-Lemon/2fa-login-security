<?php

/**
 * Support URL helpers.
 *
 * @package TFAuthLS
 */

namespace TFAuthLS;

/**
 * Provides URLs for plugin support resources.
 */
class Controller_Support
{
	const ITEM_INDEX                     = 'index';
	const ITEM_MODULE_LOGIN_SECURITY     = 'how-to';
	const ITEM_MODULE_LOGIN_SECURITY_2FA = 'how-to-enable-two-factor-authentication';


	/**
	 * Returns all configured support URLs.
	 *
	 * @return string[]
	 */
	public static function support_urls(): array
	{
		$ref       = new \ReflectionClass(static::class);
		$constants = $ref->getConstants();

		$items = array();
		foreach ($constants as $name => $value) {
			if (strpos($name, 'ITEM_') === 0) {
				$name           = strtolower(substr($name, 5));
				$items[$name] = static::support_url($value);
			}
		}

		return $items;
	}

	/**
	 * Returns an escaped support URL.
	 *
	 * @param string $item Support resource identifier.
	 * @return string
	 */
	public static function esc_support_url($item = self::ITEM_INDEX)
	{
		return esc_url(self::support_url($item));
	}

	/**
	 * Returns a support URL.
	 *
	 * @param string $item Support resource identifier.
	 * @return string
	 */
	public static function support_url(string $item = self::ITEM_INDEX): string
	{
		$base = 'https://github.com/Studio-Lemon/2fa-login-security';
		switch ($item) {
			case self::ITEM_INDEX:
				return $base;

				// These all fall through to the query format
			case self::ITEM_MODULE_LOGIN_SECURITY:
			case self::ITEM_MODULE_LOGIN_SECURITY_2FA:
				return $base . '#' . $item;
		}

		return '';
	}
}
