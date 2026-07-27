# How To Use 2FA Login Security

This guide explains how to set up, use, and disable two-factor authentication in the current standalone 2FA Login Security plugin.

## What two-factor authentication means

Two-factor authentication adds a second proof of identity to the login process. You know your password, and you also have access to an authenticator app on a phone, tablet, or another device. Both are required before the site lets you in.

This plugin uses Time-Based One-Time Passwords, or TOTP. Your authenticator app generates a short code that changes every 30 seconds.

## Before you start

Install an authenticator app if you do not already use one. Common options include:

- Google Authenticator
- FreeOTP Authenticator
- 1Password
- LastPass Authenticator
- Microsoft Authenticator
- Authy
- Any other app that supports TOTP

## How to enable two-factor authentication

1. Open the `Login Security` page in WordPress admin.
2. Open your authenticator app and create a new entry.
3. Scan the QR code shown by the plugin.
4. If you are doing setup on the same mobile device, use the manual setup code shown below the QR code instead.
5. Download the recovery codes and save them somewhere safe.
6. Enter the six-digit code shown in your authenticator app.
7. Click `Activate`.

If this is your first time setting up 2FA on the site, test it in another browser or a private window before you log out of your current session.

## How to log in with two-factor authentication

The standard login flow is:

1. Enter your username and password.
2. Submit the login form.
3. Enter the current code from your authenticator app when prompted.
4. Complete login.

If you use 2FA on multiple sites, make sure you choose the correct site entry in your authenticator app.

## Combined password + code login

This plugin also supports entering the TOTP code directly after your password in the same field.

1. Enter your username.
2. Enter your password.
3. Immediately append the current authenticator code to the end of the password.
4. Submit the login form.

Example:

- Password: `w0rdf3nce#!`
- Current code: `233455`
- Combined entry: `w0rdf3nce#!233455`

## How to use recovery codes

Recovery codes are fallback login codes for when you lose access to your authenticator device or remove the saved account entry by mistake.

- Each recovery code can only be used once.
- Recovery codes are longer than the normal six-digit authenticator code.
- You should save or print them when you first activate 2FA.

Example recovery code:

- `5199 5c24 77dc 0ed7`

To use a recovery code:

1. Enter your username and password.
2. When the `2FA Code` prompt appears, enter one recovery code.
3. Complete login.

If you use most of your recovery codes, or if you no longer trust the copy you saved, generate a new set from the `Login Security` page. Generating a new set invalidates the old set.

## How to disable two-factor authentication

To disable 2FA on your own account:

1. Log in to WordPress.
2. Open the `Login Security` page.
3. Click `Deactivate`.

To disable 2FA for another user:

1. Open the `Users` page in WordPress admin.
2. Find the user.
3. Click the `2FA` link below that username.
4. On the user's 2FA management screen, click `Deactivate`.
