<?php

/**
 * Admin notice queue management.
 *
 * @package TFAuthLS
 */

// phpcs:disable PSR2.Methods.MethodDeclaration.Underscore, Universal.Operators.StrictComparisons.LooseEqual -- Legacy private helper names and notice identifier/category comparisons are retained for compatibility.

namespace TFAuthLS;

use TFAuthLS\Text\Model_HTML;

/**
 * Manages notices displayed to administrators and users.
 */
class Controller_Notices
{



	const USER_META_KEY = 'wfls_notices';

	/**
	 * Returns the singleton Controller_Notices.
	 *
	 * @return Controller_Notices
	 */
	public static function shared()
	{
		static $_shared = null;
		if (null === $_shared) {
			$_shared = new Controller_Notices();
		}
		return $_shared;
	}

	/**
	 * Adds an admin notice to the display queue. If $user is provided, it will show only for that user, otherwise it
	 * will show for all administrators.
	 *
	 * @param string            $severity Notice severity.
	 * @param string|Model_HTML $message Notice content.
	 * @param bool|string       $category If not false, notices with the same category will be removed prior to adding this one.
	 * @param bool|\WP_User     $user If not false, the user that the notice should show for.
	 * @param array             $buttons Additional buttons to display before the dismiss button.
	 * @return void
	 */
	public function add_notice($severity, $message, $category = false, $user = false, $buttons = array()): void
	{
		$notices = $this->_notices($user);
		foreach ($notices as $id => $n) {
			if (false !== $category && isset($n['category']) && $n['category'] == $category) { // Same category overwrites previous entry
				unset($notices[$id]);
			}
		}

		$id             = Model_Crypto::uuid();
		$notices[$id] = array(
			'severity'    => $severity,
			'messageHTML' => Model_HTML::esc_html($message),
			'buttons'     => $buttons,
		);

		if (false !== $category) {
			$notices[$id]['category'] = $category;
		}

		$this->_save_notices($notices, $user);
	}

	/**
	 * Removes a notice using one of two possible search methods:
	 *
	 *  1. If $id matches. $category is ignored but only notices for $user are checked.
	 *  2. If $category matches. Only notices for $user are checked.
	 *
	 * @param bool|int      $id Notice identifier.
	 * @param bool|string   $category Notice category.
	 * @param bool|\WP_User $user User whose notices are searched.
	 * @return void
	 */
	public function remove_notice($id = false, $category = false, $user = false): void
	{
		if (false === $id && false === $category) {
			return;
		}
		if (false !== $id) {
			$category = false;
		}

		$notices = $this->_notices($user);
		foreach ($notices as $nid => $n) {
			if ($id == $nid) {
				// ID match
				unset($notices[$nid]);
				break;
			} elseif (false !== $id) {
				continue;
			}

			if (false !== $category && isset($n['category']) && $category == $n['category']) { // Category match
				unset($notices[$nid]);
			}
		}
		$this->_save_notices($notices, $user);
	}

	/**
	 * Returns whether or not a notice exists for the given user.
	 *
	 * @param bool|\WP_User $user User whose notices are checked.
	 * @return bool
	 */
	public function has_notice($user): bool
	{
		$notices = $this->_notices($user);
		return (bool) count($notices);
	}

	/**
	 * Enqueues a user's notices. For administrators this also includes global notices.
	 *
	 * @return bool Whether any notices were enqueued.
	 */
	public function enqueue_notices()
	{
		$user = wp_get_current_user();
		if (0 == $user->ID) {
			return false;
		}

		$added   = false;
		$notices = array();
		if (Controller_Permissions::shared()->can_manage_settings($user)) {
			$global_notices = $this->_notices(false);
			$notices       = array_merge($notices, $global_notices);
		}

		$user_notices = $this->_notices($user);
		$notices      = array_merge($notices, $user_notices);

		foreach ($notices as $nid => $n) {
			$notice = new Model_Notice($nid, $n['severity'], $n['messageHTML'], $n['category'], $n['buttons'] ?? array());
			if (is_multisite()) {
				add_action('network_admin_notices', array($notice, 'display_notice'));
			} else {
				add_action('admin_notices', array($notice, 'display_notice'));
			}

			$added = true;
		}

		return $added;
	}

	/**
	 * Utility
	 */

	/**
	 * Returns the notices for a user if provided, otherwise the global notices.
	 *
	 * @param bool|\WP_User $user User whose notices are retrieved.
	 * @return array
	 */
	protected function _notices($user)
	{
		if ($user instanceof \WP_User) {
			$notices = get_user_meta($user->ID, self::USER_META_KEY, true);
			return array_filter((array) $notices);
		}
		return Controller_Settings::shared()->get_array(Controller_Settings::OPTION_GLOBAL_NOTICES);
	}

	/**
	 * Saves the notices.
	 *
	 * @param array         $notices Notices to save.
	 * @param bool|\WP_User $user User whose notices are saved.
	 * @return void
	 */
	protected function _save_notices($notices, $user)
	{
		if ($user instanceof \WP_User) {
			update_user_meta($user->ID, self::USER_META_KEY, $notices);
			return;
		}
		Controller_Settings::shared()->set(Controller_Settings::OPTION_GLOBAL_NOTICES, $notices, true);
	}
}
