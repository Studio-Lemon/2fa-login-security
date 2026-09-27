<?php
/**
 * URL helpers.
 *
 * @package TFAuthLS
 */

namespace TFAuthLS;

/**
 * Provides WordPress admin URL helpers.
 */
class Utility_URL {



	/**
	 * Similar to WordPress' `admin_url`, this returns a host-relative URL for the given path. It may be used to avoid
	 * canonicalization issues with CORS (e.g., the site is configured for the www. variant of the URL but doesn't forward
	 * the other).
	 *
	 * @param string $path Admin path.
	 * @return string Host-relative admin URL.
	 */
	public static function relative_admin_url( $path = '' ) {
		$url        = admin_url($path);
		$components = wp_parse_url($url);
		$s          = $components['path'];
		if (isset($components['query']) && ('' !== $components['query'] && '0' !== $components['query'])) {
			$s .= '?' . $components['query'];
		}
		if (isset($components['fragment']) && ('' !== $components['fragment'] && '0' !== $components['fragment'])) {
			$s .= '#' . $components['fragment'];
		}
		return $s;
	}

	/**
	 * Convenience function to generate an admin URL using the appropriate function depending on multisite status.
	 *
	 * @param string $path Admin path.
	 * @return string Appropriate site or network admin URL.
	 */
	public static function maybe_network_admin_url( $path ) {
		return function_exists('network_admin_url') && is_multisite() ? network_admin_url($path) : admin_url($path);
	}
}
