<?php

namespace TFAuthLS;

class Utility_MultisiteConfigurationExtractor {


	private $prefix, $suffix;
	private float|int $suffixOffset;

	public function __construct( $prefix, $suffix ) {
		$this->prefix       = new Utility_MeasuredString( $prefix );
		$this->suffix       = new Utility_MeasuredString( $suffix );
		$this->suffixOffset = -$this->suffix->length;
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
	 * @param array $values
	 */
	private function parseBlogIds( $values ): array {
		$parsed = array();
		foreach ( $values as $key => $value ) {
			if ( substr( $key, $this->suffixOffset ) === $this->suffix->string && strpos( $key, (string) $this->prefix ) === 0 ) {
				$blogId = substr( $key, $this->prefix->length, strlen( $key ) - $this->prefix->length + $this->suffixOffset );
				if ( $blogId === '' || $blogId === '0' ) {
					$parsed[1] = $value;
				} elseif ( substr( $blogId, -1 ) === '_' ) {
					$parsed[ (int) $blogId ] = $value;
				}
			}
		}
		return $parsed;
	}

	/**
	 * Filters $values, which is the resulting array from `$this->parseBlogIds` so it contains only the values for the
	 * sites in $sites.
	 *
	 * @param array $sites
	 */
	private function filterValues( array $values, $sites ): array {
		$filtered = array();
		foreach ( $sites as $site ) {
			$blogId              = (int) $site->blog_id;
			$filtered[ $blogId ] = $values[ $blogId ];
		}
		return $filtered;
	}

	/**
	 * Processes a `get_user_meta` result array to re-key it so the keys are the numerical ID of all multisite blog IDs
	 * in `$values` that are still in an active state.
	 *
	 * @param array $values
	 * @return array
	 */
	public function extract( $values ) {
		$parsed = $this->parseBlogIds( $values );
		if ( empty( $parsed ) ) {
			return $parsed;
		}
		$sites = Utility_Multisite::retrieve_active_sites( array_keys( $parsed ) );
		return $this->filterValues( $parsed, $sites );
	}
}
