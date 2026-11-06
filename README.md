# Guardian 2FA

🔐 Per-user two-factor authentication (TOTP) for WordPress login.

## Description

Guardian 2FA asks for a 6-digit code from an authenticator app after the regular password. Each user sets up their own secret on their profile page; users who have not set up 2FA log in with their password only.

## Features

### Current Features
- ✅ Per-user TOTP secrets (RFC 6238: SHA-1, 30 seconds, 6 digits), set up and confirmed on the user's profile page
- ✅ QR code and manual key for setup
- ✅ Site-wide switch to turn the 2FA step on or off
- ✅ Pending login bound to the browser (random HttpOnly cookie, valid 10 minutes)
- ✅ At most 5 code attempts per login
- ✅ A code cannot be used twice
- ✅ XML-RPC login refused for users with 2FA
- ✅ Administrators can turn off 2FA for a user (for a lost phone)

### Planned Features (Roadmap)
- 🔄 Admin controls to enforce 2FA for specific user roles
- 🔄 Backup codes for account recovery
- 🔄 Email-based 2FA as alternative to authenticator apps
- 🔄 Trusted devices management
- 🔄 2FA usage statistics for admins

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher with the `gd` extension (QR code image)
- Google Authenticator, Microsoft Authenticator, Authy, or any TOTP app

## Installation

1. Upload the `guardian-2fa` folder to `/wp-content/plugins/`
2. Activate **Guardian 2FA** in **Plugins**
3. Turn on 2FA in **Settings → Guardian 2FA**
4. Each user sets up 2FA in **Users → Profile**

## Usage

### Set up 2FA for your account
1. Go to **Users → Profile** and scroll to **Guardian 2FA**
2. Scan the QR code with your authenticator app, or enter the key manually
3. Enter the 6-digit code from the app in **Code from the app**
4. Click **Update Profile** — 2FA is now active for your account

### Log in with 2FA
1. Enter your username and password as usual
2. Enter the 6-digit code from the app
3. After 5 wrong codes, start again from the password step

### Turn off 2FA for a user (administrator)
1. Open **Users → All Users** and edit the user
2. In **Guardian 2FA**, check **Turn off 2FA for this account**
3. Click **Update User**; the user can set up 2FA again on their profile

## Security

- Secrets are generated with `random_int` (160 bits) and stored per user in user meta, in plain text — protect database access and backups.
- Codes are compared in constant time; the previous, current, and next 30-second windows are accepted, and each window only once per user.
- The pending login token is stored only as a SHA-256 hash.
- Application passwords for the REST API are not affected by the plugin.
- Use HTTPS so the token cookie is sent with the `Secure` flag.

## Upgrading from 0.1.x

Version 0.1.x used one secret for the whole site. That secret is deleted on activation of 0.2.0, and every user must set up 2FA on their profile again.

## Support

For issues, questions, or feature requests, please use the [GitHub Issues](https://github.com/Asia-Dev-Team/2FA-wordpress-login/issues) page.

## Contributing

Contributions are welcome! See [CONTRIBUTING.md](CONTRIBUTING.md).

## ☕ Support the Project

If you find Guardian 2FA useful and want to support its development, consider buying me a coffee (or beer, or cat treats 😺):

### One-time Support
- 🍺 [Beer & Cat Treats - $5](https://buy.stripe.com/aFa28r9290t35hjbxpgMw0k)
- 🍕 [Pizza Power - $10](https://buy.stripe.com/5kQ3cv5PXfnX39bbxpgMw0l)
- 🌟 [You're a Legend - $50](https://buy.stripe.com/14AbJ14LT3FfeRT44XgMw0m)

### Monthly Support
- 🙌 [Monthly High-Five Club - $5/month](https://buy.stripe.com/7sY7sLemt6Rrh01591gMw0j)

Your support helps maintain and improve this plugin. Every contribution, no matter the size, is greatly appreciated! ❤️

## License

This plugin is licensed under GPL v2 or later.

## Author

Developed with ❤️ for WordPress security

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.