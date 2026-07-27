<?php

namespace TFAuthLS;

class Model_Compat
{
	public static function hex2bin($string): false|string
	{ //Polyfill for PHP < 5.4
		if (!is_string($string)) {
			return false;
		}
		if (strlen($string) % 2 == 1) {
			return false;
		}
		return pack('H*', $string);
	}
}
