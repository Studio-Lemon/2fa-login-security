<?php

/**
 * Array utility helpers.
 *
 * @package TFAuthLS
 */

namespace TFAuthLS;

/**
 * Provides common array operations.
 */
class Utility_Array
{


	/**
	 * Finds a key's zero-based offset.
	 *
	 * @param array      $array Array to search.
	 * @param int|string $key Key to find.
	 * @return int|null Key offset, if found.
	 */
	public static function find_offset($array, $key): ?int
	{
		$offset = 0;
		foreach ($array as $index => $value) {
			if ($index === $key) {
				return $offset;
			}
			++$offset;
		}
		return null;
	}

	/**
	 * Inserts an item after a target key.
	 *
	 * @param array      $array Array to update.
	 * @param int|string $target_key Key after which to insert.
	 * @param int|string $key Key for the inserted value.
	 * @param mixed      $value Value to insert.
	 * @return bool Whether the item was inserted.
	 */
	public static function insert_after(&$array, $target_key, $key, $value): bool
	{
		$offset = self::find_offset($array, $target_key);
		if (null === $offset) {
			return false;
		}
		$array = array_merge(
			array_slice($array, 0, $offset + 1),
			array($key => $value),
			array_slice($array, $offset + 1)
		);
		return true;
	}

	/**
	 * Returns the items from $array whose keys are in $keys.
	 *
	 * @param array        $array Array to filter.
	 * @param array|string $keys Keys to select.
	 * @param bool         $single Return single-value as-is instead of a one-element array.
	 * @param mixed|null   $default Value to return when $single is true and nothing is found.
	 * @return array|mixed
	 */
	public static function array_choose($array, $keys, $single = false, $default = null)
	{
		if (! is_array($keys)) {
			$keys = array($keys);
		}

		$matches = array_filter(
			$array,
			function ($k) use ($keys): bool {
				return in_array($k, $keys);
			},
			ARRAY_FILTER_USE_KEY
		);
		if ($single) {
			$key = self::array_first($keys);
			if (null !== $key && isset($matches[$key])) {
				return $matches[$key];
			}

			return $default;
		}
		return $matches;
	}

	/**
	 * Convenience function for `array_choose` in its single return value mode for better code readability.
	 *
	 * @param array      $array Array to search.
	 * @param string     $key Key to retrieve.
	 * @param mixed|null $default Value to return when the key is absent.
	 * @return mixed
	 */
	public static function array_get($array, $key, $default = null)
	{
		return self::array_choose($array, $key, true, $default);
	}

	/**
	 * Returns the first array value.
	 *
	 * @param array $array Array to inspect.
	 * @return mixed|null First value, if present.
	 */
	public static function array_first($array)
	{
		if (empty($array)) {
			return null;
		}

		$values = array_values($array);
		return $values[0];
	}

	/**
	 * Returns the last array value.
	 *
	 * @param array $array Array to inspect.
	 * @return mixed|null Last value, if present.
	 */
	public static function array_last($array)
	{
		if (empty($array)) {
			return null;
		}

		$values = array_values($array);
		return $values[count($values) - 1];
	}
}
