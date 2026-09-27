<?php

/**
 * Multisite configuration extraction utilities.
 *
 * @package TFAuthLS
 */

namespace TFAuthLS;

/**
 * Extracts active multisite values from user metadata.
 */
class Utility_MultisiteConfigurationExtractor
{



	/**
	 * Metadata key prefix.
	 *
	 * @var Utility_MeasuredString
	 */
	private $prefix;
	/**
	 * Metadata key suffix.
	 *
	 * @var Utility_MeasuredString
	 */
	private $suffix;
	/**
	 * Offset of the suffix within a metadata key.
	 *
	 * @var int
	 */
	private int $suffix_offset;

	/**
	 * Initializes the metadata key matcher.
	 *
	 * @param string $prefix Metadata key prefix.
	 * @param string $suffix Metadata key suffix.
	 */
	public function __construct($prefix, $suffix)
	{
		$this->prefix        = new Utility_MeasuredString($prefix);
		$this->suffix        = new Utility_MeasuredString($suffix);
		$this->suffix_offset = -$this->suffix->length;
	}

	/**
	 * Parses a `get_user_meta` result array into a more usable format. The input array will be something similar to
	 * [
	 *         'wp_capabilities' => '...',
	 *         'wp_3_capabilities' => '...',
	 *         'wp_4_capabilities' => '...',
	 *         'wp_10_capabilities' => '...',
	 * ]
	 *
	 * This will return
	 * [
	 *         1 => '...',
	 *         3 => '...',
	 *         4 => '...',
	 *         10 => '...',
	 * ]
	 *
	 * @param array $values User metadata values.
	 * @return array Metadata values keyed by blog ID.
	 */
	private function parse_blog_ids($values): array
	{
		$parsed = array();
		foreach ($values as $key => $value) {
			if (str_ends_with($key, $this->suffix->string) && 0 === strpos($key, (string) $this->prefix)) {
				$blog_id = substr($key, $this->prefix->length, strlen($key) - $this->prefix->length + $this->suffix_offset);
				if ('' === $blog_id || '0' === $blog_id) {
					$parsed[1] = $value;
				} elseif ('_' === substr($blog_id, -1)) {
					$parsed[(int) $blog_id] = $value;
				}
			}
		}
		return $parsed;
	}

	/**
	 * Filters $values, which is the resulting array from `$this->parse_blog_ids` so it contains only the values for
	 * the sites in $sites.
	 *
	 * @param array $values Values keyed by blog ID.
	 * @param array $sites Active multisite sites.
	 * @return array Values limited to active sites.
	 */
	private function filter_values(array $values, $sites): array
	{
		$filtered = array();
		foreach ($sites as $site) {
			$blog_id              = (int) $site->blog_id;
			$filtered[$blog_id] = $values[$blog_id];
		}
		return $filtered;
	}

	/**
	 * Processes a `get_user_meta` result array to re-key it so the keys are the numerical ID of all multisite blog IDs
	 * in `$values` that are still in an active state.
	 *
	 * @param array $values User metadata values.
	 * @return array
	 */
	public function extract($values)
	{
		$parsed = $this->parse_blog_ids($values);
		if (empty($parsed)) {
			return $parsed;
		}
		$sites = Utility_Multisite::retrieve_active_sites(array_keys($parsed));
		return $this->filter_values($parsed, $sites);
	}
}
