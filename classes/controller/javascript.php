<?php

namespace TFAuthLS;

use TFAuthLS\Controller_TFAuthLS;
use TFAuthLS\Controller_Settings;
use TFAuthLS\Model_Asset;
use TFAuthLS\Model_Request;
use TFAuthLS\Controller_Permissions;
use TFAuthLS\Controller_Support;
use TFAuthLS\Controller_Time;

class Controller_Javascript {


	/**
	 * Returns a mapping of translation strings for the Javascript frontend to use, populated via the WordPress
	 * translation system.
	 *
	 * It would be nice to be less redundant here, but the support for that is in WP 5.0 and unavailable in our
	 * current oldest supported version.
	 */
	public static function i18nStrings(): array {
		return array(
			'(definitely a human)'                       => __( '(definitely a human)', '2fa-login-security' ),
			'(probably a bot)'                           => __( '(probably a bot)', '2fa-login-security' ),
			'(probably a human)'                         => __( '(probably a human)', '2fa-login-security' ),
			'2FA'                                        => __( '2FA', '2fa-login-security' ),
			'2FA Notifications'                          => __( '2FA Notifications', '2fa-login-security' ),
			'2FA Relative URL (optional)'                => __( '2FA Relative URL (optional)', '2fa-login-security' ),
			'2FA Role'                                   => __( '2FA Role', '2fa-login-security' ),
			'2FA Roles'                                  => __( '2FA Roles', '2fa-login-security' ),
			'2FA management shortcode'                   => __( '2FA management shortcode', '2fa-login-security' ),
			'Allow remembering device for 30 days'       => __( 'Allow remembering device for 30 days', '2fa-login-security' ),
			'An error occurred'                          => __( 'An error occurred', '2fa-login-security' ),
			'An error was encountered while trying to disable NTP. Please try again.' => __( 'An error was encountered while trying to disable NTP. Please try again.', '2fa-login-security' ),
			'An error was encountered while trying to reset the NTP state. Please try again.' => __( 'An error was encountered while trying to reset the NTP state. Please try again.', '2fa-login-security' ),
			'An error was encountered while trying to send the notification. Please try again.' => __( 'An error was encountered while trying to send the notification. Please try again.', '2fa-login-security' ),
			'Cancel'                                     => __( 'Cancel', '2fa-login-security' ),
			'Cancel Changes'                             => __( 'Cancel Changes', '2fa-login-security' ),
			'Close'                                      => __( 'Close', '2fa-login-security' ),
			'Count'                                      => __( 'Count', '2fa-login-security' ),
			'Detected IP(s)'                             => __( 'Detected IP(s)', '2fa-login-security' ),
			'days'                                       => __( 'days', '2fa-login-security' ),
			'Delete Login Security tables and data on deactivation' => __( 'Delete Login Security tables and data on deactivation', '2fa-login-security' ),
			'Disable'                                    => __( 'Disable', '2fa-login-security' ),
			'Disable XML-RPC authentication'             => __( 'Disable XML-RPC authentication', '2fa-login-security' ),
			'Edit trusted proxies'                       => __( 'Edit trusted proxies', '2fa-login-security' ),
			'e.g., /my-account/'                         => __( 'e.g., /my-account/', '2fa-login-security' ),
			'Error Disabling NTP'                        => __( 'Error Disabling NTP', '2fa-login-security' ),
			'Error Resetting NTP'                        => __( 'Error Resetting NTP', '2fa-login-security' ),
			'Error Saving Option'                        => __( 'Error Saving Option', '2fa-login-security' ),
			'Error Saving Options'                       => __( 'Error Saving Options', '2fa-login-security' ),
			'Error Sending Notification'                 => __( 'Error Sending Notification', '2fa-login-security' ),
			'For roles that require 2FA, users will have this many days to set up 2FA. Failure to set up 2FA during this period will result in the user losing account access. This grace period will apply to new users from the time of account creation. For existing users, this grace period will apply relative to the time at which the requirement is implemented. This grace period will not automatically apply to admins and must be manually enabled for each admin user.' => __( 'For roles that require 2FA, users will have this many days to set up 2FA. Failure to set up 2FA during this period will result in the user losing account access. This grace period will apply to new users from the time of account creation. For existing users, this grace period will apply relative to the time at which the requirement is implemented. This grace period will not automatically apply to admins and must be manually enabled for each admin user.', '2fa-login-security' ),
			'General'                                    => __( 'General', '2fa-login-security' ),
			'Grace Period'                               => __( 'Grace Period', '2fa-login-security' ),
			'How to get IPs'                             => __( 'How to get IPs', '2fa-login-security' ),
			'If enabled, users with 2FA enabled may choose to be prompted for a code only once every 30 days per device.' => __( 'If enabled, users with 2FA enabled may choose to be prompted for a code only once every 30 days per device.', '2fa-login-security' ),
			'If enabled, XML-RPC calls that require authentication will also require a valid 2FA code to be appended to the password. You must choose the "Skipped" option if you use the WordPress app, the Jetpack plugin, or other services that require XML-RPC.' => __( 'If enabled, XML-RPC calls that require authentication will also require a valid 2FA code to be appended to the password. You must choose the "Skipped" option if you use the WordPress app, the Jetpack plugin, or other services that require XML-RPC.', '2fa-login-security' ),
			'If enabled, all settings and 2FA records will be deleted on deactivation. If later reactivated, all users that previously had 2FA active will need to set it up again.' => __( 'If enabled, all settings and 2FA records will be deleted on deactivation. If later reactivated, all users that previously had 2FA active will need to set it up again.', '2fa-login-security' ),
			'In order to use 2FA with the WooCommerce customer role, you must either enable the "WooCommerce integration" option or use the "wordfence_2fa_management" shortcode to provide customers with access to the 2FA management interface. The default interface is only available through WordPress admin pages which are not accessible to users in the customer role.' => __( 'In order to use 2FA with the WooCommerce customer role, you must either enable the "WooCommerce integration" option or use the "wordfence_2fa_management" shortcode to provide customers with access to the 2FA management interface. The default interface is only available through WordPress admin pages which are not accessible to users in the customer role.', '2fa-login-security' ),
			'Learn More'                                 => __( 'Learn More', '2fa-login-security' ),
			'NTP'                                        => __( 'NTP', '2fa-login-security' ),
			'NTP is a protocol that allows for remote time synchronization. 2FA Login Security uses this protocol to ensure that it has the most accurate time which is necessary for TOTP-based two-factor authentication.' => __( 'NTP is a protocol that allows for remote time synchronization. 2FA Login Security uses this protocol to ensure that it has the most accurate time which is necessary for TOTP-based two-factor authentication.', '2fa-login-security' ),
			'NTP is currently <strong>enabled</strong>.' => __( 'NTP is currently <strong>enabled</strong>.', '2fa-login-security' ),
			'NTP is currently disabled as %d subsequent attempts have failed.' => /* translators: number of attempts */ __( 'NTP is currently disabled as %d subsequent attempts have failed.', '2fa-login-security' ),
			'NTP updates are currently failing.'         => __( 'NTP updates are currently failing.', '2fa-login-security' ),
			'NTP was manually disabled.'                 => __( 'NTP was manually disabled.', '2fa-login-security' ),
			'NTP will be automatically disabled after %d more attempts.' => /* translators: number of attempts */ __( 'NTP will be automatically disabled after %d more attempts.', '2fa-login-security' ),
			'NTP will be automatically disabled after 1 more attempt.' => __( 'NTP will be automatically disabled after 1 more attempt.', '2fa-login-security' ),
			'Notification Results'                       => __( 'Notification Results', '2fa-login-security' ),
			'Notification Sent'                          => __( 'Notification Sent', '2fa-login-security' ),
			'Notify'                                     => __( 'Notify', '2fa-login-security' ),
			'Requests'                                   => __( 'Requests', '2fa-login-security' ),
			'Required'                                   => __( 'Required', '2fa-login-security' ),
			'Requiring 2FA for customers is not recommended as some customers may experience difficulties setting up or using two-factor authentication. Instead, using the "Optional" mode for users with the customer role is recommended which will allow customers to enable 2FA, but will not require them to do so.' => __( 'Requiring 2FA for customers is not recommended as some customers may experience difficulties setting up or using two-factor authentication. Instead, using the "Optional" mode for users with the customer role is recommended which will allow customers to enable 2FA, but will not require them to do so.', '2fa-login-security' ),
			'Reset'                                      => __( 'Reset', '2fa-login-security' ),
			'Reset Score Statistics'                     => __( 'Reset Score Statistics', '2fa-login-security' ),
			'Save'                                       => __( 'Save', '2fa-login-security' ),
			'Save Changes'                               => __( 'Save Changes', '2fa-login-security' ),
			'Send Anyway'                                => __( 'Send Anyway', '2fa-login-security' ),
			'Send an email to users with the selected role to notify them of the grace period for enabling 2FA. Select the desired role and optionally specify the URL to be sent in the email to setup 2FA. If left blank, the URL defaults to the standard wordpress login and Wordfence’s Two-Factor Authentication plugin page. For example, if using WooCommerce, input the relative URL of the account page.' => __( 'Send an email to users with the selected role to notify them of the grace period for enabling 2FA. Select the desired role and optionally specify the URL to be sent in the email to setup 2FA. If left blank, the URL defaults to the standard wordpress login and Wordfence’s Two-Factor Authentication plugin page. For example, if using WooCommerce, input the relative URL of the account page.', '2fa-login-security' ),
			'Setting the grace period to 0 will prevent users in roles where 2FA is required, including newly created users, from logging in if they have not already enabled two-factor authentication.' => __( 'Setting the grace period to 0 will prevent users in roles where 2FA is required, including newly created users, from logging in if they have not already enabled two-factor authentication.', '2fa-login-security' ),
			'Skipped'                                    => __( 'Skipped', '2fa-login-security' ),
			'Show 2FA menu on WooCommerce Account page'  => __( 'Show 2FA menu on WooCommerce Account page', '2fa-login-security' ),
			'Show last login column on WP Users page'    => __( 'Show last login column on WP Users page', '2fa-login-security' ),
			'The constant TFA_LS_DISABLE_NTP is defined which disables NTP entirely. Remove this constant or set it to a falsy value to enable NTP.' => __( 'The constant TFA_LS_DISABLE_NTP is defined which disables NTP entirely. Remove this constant or set it to a falsy value to enable NTP.', '2fa-login-security' ),
			'These IPs (or CIDR ranges) will be ignored when determining the requesting IP via the X-Forwarded-For HTTP header. Enter one IP or CIDR range per line.' => __( 'These IPs (or CIDR ranges) will be ignored when determining the requesting IP via the X-Forwarded-For HTTP header. Enter one IP or CIDR range per line.', '2fa-login-security' ),
			'Trusted Proxies'                            => __( 'Trusted Proxies', '2fa-login-security' ),
			'Use single-column layout for WooCommerce/shortcode 2FA management interface' => __( 'Use single-column layout for WooCommerce/shortcode 2FA management interface', '2fa-login-security' ),
			'When enabled, the "wordfence_2fa_management" shortcode may be used to provide access for users to manage 2FA settings on custom pages.' => __( 'When enabled, the "wordfence_2fa_management" shortcode may be used to provide access for users to manage 2FA settings on custom pages.', '2fa-login-security' ),
			'When enabled, the 2FA management interface embedded through the WooCommerce integration or via a shortcode will use a vertical stacked layout as opposed to horizontal columns. Adjust this setting as appropriate to match your theme. This may be overridden using the "stacked" attribute for individual shortcodes.' => __( 'When enabled, the 2FA management interface embedded through the WooCommerce integration or via a shortcode will use a vertical stacked layout as opposed to horizontal columns. Adjust this setting as appropriate to match your theme. This may be overridden using the "stacked" attribute for individual shortcodes.', '2fa-login-security' ),
			'2FA Login Security Installed'               => __( '2FA Login Security Installed', '2fa-login-security' ),
			'You have just installed 2FA Login Security. Use the Login Security menu to activate and manage two-factor authentication for user accounts.' => __( 'You have just installed 2FA Login Security. Use the Login Security menu to activate and manage two-factor authentication for user accounts.', '2fa-login-security' ),
			'Visit the plugin page for updates and documentation.' => __( 'Visit the plugin page for updates and documentation.', '2fa-login-security' ),
			'Your IP with this setting'                  => __( 'Your IP with this setting', '2fa-login-security' ),
			'WooCommerce & Custom Integrations'          => __( 'WooCommerce & Custom Integrations', '2fa-login-security' ),
			'WooCommerce integration'                    => __( 'WooCommerce integration', '2fa-login-security' ),
		);
	}

	/**
	 * Returns an array of constants/initial state values for use on the Javascript frontend to avoid hardcoding values.
	 */
	public static function jsConstants(): array {
		$response = array();

		$response['plugin'] = array(
			'ip'                           => array(
				'current' => Model_Request::current()->ip(),
				'preview' => Model_Request::current()->detected_ip_preview(),
			),
			'ls_from_core'                 => false,
			'ntp'                          => array(
				'constant_disabled'  => Controller_Settings::shared()->is_ntp_disabled_via_constant(),
				'cron_disabled'      => Controller_Settings::shared()->is_ntp_cron_disabled( $failureCount ),
				'cron_failure_count' => $failureCount,
				'max_failures'       => Controller_Time::FAILURE_LIMIT,
			),
			'should_use_core_font_awesome' => false,
			'server'                       => array(
				'has_woocommerce' => false,
			),
		);

		$response['roles'] = array(
			'labels' => array(
				Controller_Settings::STATE_2FA_DISABLED => __( 'Disabled', '2fa-login-security' ),
				Controller_Settings::STATE_2FA_OPTIONAL => __( 'Optional', '2fa-login-security' ),
				Controller_Settings::STATE_2FA_REQUIRED => __( 'Required', '2fa-login-security' ),
			),
			'states' => array(
				'disabled' => Controller_Settings::STATE_2FA_DISABLED,
				'optional' => Controller_Settings::STATE_2FA_OPTIONAL,
				'required' => Controller_Settings::STATE_2FA_REQUIRED,
			),
		);

		$response['support'] = array(
			'url' => Controller_Support::supportURLs(),
		);

		$roles   = new \WP_Roles();
		$options = array();
		if ( is_multisite() ) {
			$options[] = array(
				'role'            => 'super-admin',
				'name'            => 'enabled-roles.super-admin',
				'title'           => __( 'Super Administrator', '2fa-login-security' ),
				'editable'        => true,
				'allow_disabling' => false,
				'state'           => Controller_Settings::shared()->get_required_2fa_role_activation_time( 'super-admin' ) !== false ? 'required' : 'optional',
			);
		}

		foreach ( $roles->role_objects as $name => $r ) {
			/** @var \WP_Role $r */
			$options[] = array(
				'role'            => $name,
				'name'            => 'enabled-roles.' . $name,
				'title'           => $roles->role_names[ $name ],
				'editable'        => true,
				'allow_disabling' => ( ! is_multisite() && 'administrator' === $name ? false : true ),
				'state'           => Controller_Settings::shared()->get_required_2fa_role_activation_time( $name ) !== false ? 'required' : ( $r->has_cap( Controller_Permissions::CAP_ACTIVATE_2FA_SELF ) ? 'optional' : 'disabled' ),
			);
		}
		$response['options'] = array(
			'roles'     => $options,
			'ip_source' => array(
				array(
					'value'       => Model_Request::IP_SOURCE_AUTOMATIC,
					'label'       => __( 'Use the most secure method to get visitor IP addresses. Prevents spoofing and works with most sites.', '2fa-login-security' ),
					'recommended' => true,
				),
				array(
					'value' => Model_Request::IP_SOURCE_REMOTE_ADDR,
					'label' => __( 'Use PHP\'s built in REMOTE_ADDR and don\'t use anything else. Very secure if this is compatible with your site.', '2fa-login-security' ),
				),
				array(
					'value' => Model_Request::IP_SOURCE_X_FORWARDED_FOR,
					'label' => __( 'Use the X-Forwarded-For HTTP header. Only use if you have a front-end proxy or spoofing may result.', '2fa-login-security' ),
				),
				array(
					'value' => Model_Request::IP_SOURCE_X_REAL_IP,
					'label' => __( 'Use the X-Real-IP HTTP header. Only use if you have a front-end proxy or spoofing may result.', '2fa-login-security' ),
				),
			),
			'value'     => self::_prefixOptions( Controller_Settings::shared()->all() ),
		);

		return $response;
	}

	/**
	 * Prefixes all keys in the given options with "wfls-" to avoid name collisions with the main plugin.
	 *
	 * @return mixed[]
	 */
	private static function _prefixOptions( $options ): array {
		$result = array();
		foreach ( $options as $key => $value ) {
			$result[ 'wfls-' . $key ] = $value;
		}
		return $result;
	}

	/**
	 * Returns the importmap array for our bundled modules.
	 *
	 * @return array
	 */
}
