<?php
/*
Plugin Name: 2FA Login Security
Description: 2FA Login Security for WordPress
Author: 2FA Login Security Contributors
Author URI: https://wordpress.org/plugins/2fa-login-security/
Version: 1.1.16
Network: true
Requires at least: 4.7
Requires PHP: 7.0
Text Domain: 2fa-login-security
Domain Path: /languages
@copyright Copyright (C) 2019-2023 Defiant Inc.
*/
if (defined('WP_INSTALLING') && WP_INSTALLING) {
	return;
}
if (! defined('ABSPATH')) {
	exit;
}

define('TFA_LS_VERSION', '1.1.16');

define('TFA_LS_PLUGIN_BASENAME', plugin_basename(__FILE__));

if (! defined('TFA_LS_EMAIL_VALIDITY_DURATION_MINUTES')) {
	define('TFA_LS_EMAIL_VALIDITY_DURATION_MINUTES', 15);
}

global $wp_plugin_paths;
foreach ($wp_plugin_paths as $dir => $realdir) {
	if (strpos(__FILE__, $realdir) === 0) {
		define('TFA_LS_FCPATH', $dir . '/' . basename(__FILE__));
		define('TFA_LS_PATH', trailingslashit($dir));
		break;
	}
}

if (! defined('TFA_LS_FCPATH')) {
	/** @noinspection PhpConstantReassignmentInspection */
	define('TFA_LS_FCPATH', __FILE__);
	/** @noinspection PhpConstantReassignmentInspection */
	define('TFA_LS_PATH', trailingslashit(dirname(TFA_LS_FCPATH)));
}

require_once __DIR__ . '/classes/utility/array.php';
require_once __DIR__ . '/classes/utility/baseconversion.php';
require_once __DIR__ . '/classes/utility/lock.php';
require_once __DIR__ . '/classes/utility/nulllock.php';
require_once __DIR__ . '/classes/utility/databaselock.php';
require_once __DIR__ . '/classes/utility/measuredstring.php';
require_once __DIR__ . '/classes/utility/multisite.php';
require_once __DIR__ . '/classes/utility/multisiteconfigurationextractor.php';
require_once __DIR__ . '/classes/utility/number.php';
require_once __DIR__ . '/classes/utility/serialization.php';
require_once __DIR__ . '/classes/utility/sleep.php';
require_once __DIR__ . '/classes/utility/url.php';
require_once __DIR__ . '/classes/model/asset.php';
require_once __DIR__ . '/classes/model/compat.php';
require_once __DIR__ . '/classes/model/crypto.php';
require_once __DIR__ . '/classes/model/ip.php';
require_once __DIR__ . '/classes/model/notice.php';
require_once __DIR__ . '/classes/model/request.php';
require_once __DIR__ . '/classes/model/settings.php';
require_once __DIR__ . '/classes/model/script.php';
require_once __DIR__ . '/classes/model/style.php';
require_once __DIR__ . '/classes/model/tokenbucket.php';
require_once __DIR__ . '/classes/model/view.php';
require_once __DIR__ . '/classes/model/2fainitializationdata.php';
require_once __DIR__ . '/classes/model/crypto/base2n.php';
require_once __DIR__ . '/classes/model/crypto/jwt.php';
require_once __DIR__ . '/classes/model/crypto/symmetric.php';
require_once __DIR__ . '/classes/model/settings/db.php';
require_once __DIR__ . '/classes/model/settings/wpoptions.php';
require_once __DIR__ . '/classes/model/text/html.php';
require_once __DIR__ . '/classes/model/text/javascript.php';
require_once __DIR__ . '/classes/model/view/tab.php';
require_once __DIR__ . '/classes/model/view/title.php';
require_once __DIR__ . '/classes/controller/db.php';
require_once __DIR__ . '/classes/controller/notices.php';
require_once __DIR__ . '/classes/controller/permissions.php';
require_once __DIR__ . '/classes/controller/settings.php';
require_once __DIR__ . '/classes/controller/support.php';
require_once __DIR__ . '/classes/controller/time.php';
require_once __DIR__ . '/classes/controller/totp.php';
require_once __DIR__ . '/classes/controller/users.php';
require_once __DIR__ . '/classes/controller/whitelist.php';
require_once __DIR__ . '/classes/controller/ajax.php';
require_once __DIR__ . '/classes/controller/javascript.php';
require_once __DIR__ . '/classes/controller/wordfencels.php';

if (! defined('TFA_LS_VERSIONONLY_MODE')) { // Used to get version from file
	\TFAuthLS\Controller_TFAuthLS::shared()->init();
}
