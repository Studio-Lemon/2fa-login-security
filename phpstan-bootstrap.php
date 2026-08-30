<?php
/**
 * Constants defined at runtime by the plugin bootstrap, declared here for static analysis only.
 *
 * @package 2fa-login-security
 */

declare(strict_types=1);

define( 'TFA_LS_VERSION', '1.1.16' );
define( 'TFA_LS_PLUGIN_BASENAME', '2fa-login-security/2fa-login-security.php' );
define( 'TFA_LS_EMAIL_VALIDITY_DURATION_MINUTES', 15 );
define( 'TFA_LS_FCPATH', __DIR__ . '/2fa-login-security.php' );
define( 'TFA_LS_PATH', __DIR__ . '/' );

// WordPress runtime constants that are missing from php-stubs/wordpress-stubs.
define( 'COOKIEPATH', '/' );
define( 'WPINC', 'wp-includes' );
