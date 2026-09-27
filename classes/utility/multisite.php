<?php

/**
 * Multisite utility helpers.
 *
 * @package TFAuthLS
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The legacy multisite fallback query must read the site table directly.

namespace TFAuthLS;

/**
 * Provides multisite blog queries.
 */
class Utility_Multisite
{



	/**
	 * Returns an array of all active multisite blogs (if `$blog_ids` is `null`) or a list of active multisite blogs
	 * filtered to only those in `$blog_ids` if non-null.
	 *
	 * @param array|null $blog_ids Optional blog IDs to restrict the result to.
	 * @return array
	 */
	public static function retrieve_active_sites($blog_ids = null)
	{
		$args = array(
			'number'                 => 0, // No limit on the result set.
			'update_site_meta_cache' => false, // Defaults to true which is not desirable for this use case.
			// Ignore archived/spam/deleted sites
			'archived'               => 0,
			'spam'                   => 0,
			'deleted'                => 0,
		);

		if (null !== $blog_ids) {
			$args['site__in'] = $blog_ids;
		}

		if (function_exists('get_sites')) {
			return get_sites($args);
		}

		global $wpdb;
		if (null !== $blog_ids) {
			$blog_ids_query = implode(',', wp_parse_id_list($args['site__in']));
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The ID list is sanitized with wp_parse_id_list().
			return $wpdb->get_results("SELECT * FROM {$wpdb->blogs} WHERE blog_id IN ({$blog_ids_query}) AND archived = 0 AND spam = 0 AND deleted = 0");
		}

		return $wpdb->get_results("SELECT * FROM {$wpdb->blogs} WHERE archived = 0 AND spam = 0 AND deleted = 0");
	}
}
