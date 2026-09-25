<?php

/**
 * Plugin Name:                 2FA Login Security
 * Plugin URI:                  https://github.com/Studio-Lemon/2fa-login-security
 * Description:                 2FA Login Security for WordPress
 * Author:                      Erik van der Bas, Studio Lemon
 * Author URI:                  https://wordpress.org/plugins/2fa-login-security/
 * Text Domain:                 2fa-login-security
 * Domain Path:                 /languages
 * Requires PHP:                        8.1
 * Requires at least:               7.0
 * x-release-please-start-version
 * Version:                     2.0.0-beta.3
 * Network:                     true
 * x-release-please-end
 *
 * @package TFAuthLS
 */

namespace TFAuthLS;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

if (function_exists('wp_installing') && wp_installing()) {
	return;
}
if (! defined('ABSPATH')) {
	exit;
}

define('TFA_LS_VERSION', '2.0.0-beta.3'); // x-release-please-version

define('TFA_LS_PLUGIN_BASENAME', plugin_basename(__FILE__));

if (! defined('TFA_LS_EMAIL_VALIDITY_DURATION_MINUTES')) {
	define('TFA_LS_EMAIL_VALIDITY_DURATION_MINUTES', 15);
}

define('TFA_LS_FCPATH', __FILE__);
define('TFA_LS_PATH', trailingslashit(dirname(TFA_LS_FCPATH)));


require __DIR__ . '/plugin-update-checker/plugin-update-checker.php';

$update_checker = PucFactory::buildUpdateChecker(
	'https://github.com/Studio-Lemon/2fa-login-security/',
	__FILE__,
	'2fa-login-security'
);

$update_checker->setBranch('master');
$vcs_api = $update_checker->getVcsApi();

/** @var \YahnisElsts\PluginUpdateChecker\v5p6\Vcs\GitHubApi $vcs_api */
$vcs_api->enableReleaseAssets('/2fa-login-security\.zip/', 2);

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
require_once __DIR__ . '/classes/controller/2fa-login-security.php';


\TFAuthLS\Controller_TFAuthLS::shared()->init();
