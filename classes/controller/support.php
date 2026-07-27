<?php

namespace TFAuthLS;

class Controller_Support
{

	const ITEM_INDEX = 'index';
	const ITEM_CHANGELOG = 'changelog';
	const ITEM_MODULE_LOGIN_SECURITY                         = 'module-login-security';
	const ITEM_MODULE_LOGIN_SECURITY_2FA                     = 'module-login-security-2fa';

	public static function supportURLs(): array
	{
		$ref       = new \ReflectionClass(static::class);
		$constants = $ref->getConstants();

		$items = array();
		foreach ($constants as $name => $value) {
			if (strpos($name, 'ITEM_') === 0) {
				$name           = strtolower(substr($name, 5));
				$items[$name] = static::supportURL($value);
			}
		}

		return $items;
	}

	public static function esc_supportURL($item = self::ITEM_INDEX)
	{
		return esc_url(self::supportURL($item));
	}

	public static function supportURL(string $item = self::ITEM_INDEX): string
	{
		$base = 'https://github.com/Studio-Lemon/2fa-login-security';
		switch ($item) {
			case self::ITEM_INDEX:
				return $base;

				// These all fall through to the query format



			case self::ITEM_MODULE_LOGIN_SECURITY:
			case self::ITEM_MODULE_LOGIN_SECURITY_2FA:
				return $base . '?query=' . $item;
		}

		return '';
	}
}
