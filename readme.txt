=== 2FA Login Security ===
Contributors: 2fa-login-security
Tags: security, login security, 2fa, two factor authentication, xml-rpc, mfa, 2 factor
Tested up to: 7.1.2
Stable tag: 2.0.0-beta.4 // x-release-please-version


Secure your website with 2FA Login Security, providing focused two-factor authentication for WordPress logins.

== Description ==

### 2FA LOGIN SECURITY

2FA Login Security provides focused account protection features for WordPress sites:

* Two-factor authentication (2FA) for WordPress users.
* Role-based 2FA enforcement and grace period controls.
* Optional remember-device support.

#### TWO-FACTOR AUTHENTICATION

* Two-factor authentication (2FA), one of the most secure forms of remote system authentication available.
* Use any TOTP-based authenticator app or service like Google Authenticator, Authy, 1Password or FreeOTP.
* Enable 2FA for any WordPress user role.
* Completely free to use, no limits or restrictions of any kind.

#### XML-RPC PROTECTION

* XML-RPC settings were removed from this fork on purpose.
* Recommended approach: disable XML-RPC at the theme or server level unless a legacy integration explicitly requires it.
* Reason: XML-RPC remains a common brute-force and abuse target, and disabling it entirely is usually the safest default.

Theme example (add to your active theme's functions.php):

	add_filter('xmlrpc_enabled', '__return_false');

Optional extra hardening for pingback methods:

	add_filter('xmlrpc_methods', function ($methods) {
		unset($methods['pingback.ping']);
		unset($methods['pingback.extensions.getPingbacks']);
		return $methods;
	});

== Installation ==

Secure your website using the following steps:

1. Install 2FA Login Security automatically or by uploading the ZIP file.
2. Activate 2FA Login Security through the Plugins menu in WordPress.
3. Go to the 'Login Security' menu and activate two-factor authentication and configure other settings.

To install 2FA Login Security on WordPress Multisite installations:

1. Install 2FA Login Security via the plugin directory or by uploading the ZIP file.
2. Network Activate 2FA Login Security.
3. Once network activated, it appears in Network Admin for super administrators and on individual sites for users who have permission to activate 2FA.

== Screenshots ==

Secure your website with 2FA Login Security.

1. Take login security to the next level with two-factor authentication.
2. Logging in is easy with 2FA.
3. Configuration options include role-based access controls and 2FA behavior settings.