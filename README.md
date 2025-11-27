# Guardian 2FA

🔐 Advanced Two-Factor Authentication plugin for WordPress with multiple authentication methods.

## Description

Guardian 2FA adds an extra layer of security to your WordPress site by requiring users to enter a second factor in addition to their regular password. Supports multiple authentication methods and flexible admin controls.

## Features

### Current Features
- ✅ Two-factor authentication via Google Authenticator (TOTP)
- ✅ QR code generation for easy setup
- ✅ User-friendly admin interface
- ✅ Per-user 2FA settings

### Planned Features (Roadmap)
- 🔄 Admin controls to enforce 2FA for specific user roles
- 🔄 Backup codes for account recovery
- 🔄 Email-based 2FA as alternative to authenticator apps
- 🔄 SMS-based 2FA (optional)
- 🔄 Trusted devices management
- 🔄 2FA usage statistics for admins

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Google Authenticator app (iOS/Android) or compatible TOTP app

## Installation

1. Download the plugin
2. Upload `guardian-2fa` folder to `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure 2FA in your user profile settings

## Usage

### For Users:

#### Setup 2FA for your account:
1. Go to **Users → Your Profile** in WordPress admin
2. Scroll to **Guardian 2FA Settings** section
3. Choose authentication method (Google Authenticator or Email)
4. For Google Authenticator: Scan the QR code with your app
5. For Email: Verify your email address
6. Enter the verification code
7. Save backup codes in a safe place
8. Click **Save Changes**

#### Login with 2FA:
1. Enter your username and password as usual
2. Enter the 6-digit code from your chosen method
3. Click **Login**

### For Administrators:

#### Enforce 2FA for user roles:
1. Go to **Settings → Guardian 2FA**
2. Select user roles that must use 2FA (Administrators, Editors, etc.)
3. Set grace period for users to set up 2FA
4. Click **Save Settings**

#### View 2FA statistics:
1. Go to **Users → 2FA Status**
2. See which users have 2FA enabled
3. View authentication logs

## Authentication Methods

### 1. Google Authenticator (TOTP)
Time-based one-time passwords using industry-standard TOTP algorithm. Compatible with:
- Google Authenticator
- Microsoft Authenticator
- Authy
- Any TOTP-compatible app

### 2. Email-based (Planned)
Receive 6-digit codes via email. Useful for users who prefer not to use authenticator apps.

### 3. Backup Codes (Planned)
One-time use codes for emergency access when primary method is unavailable.

## Security

- Uses industry-standard TOTP (RFC 6238) algorithm
- Secure secret key generation
- Rate limiting to prevent brute force attacks
- Encrypted storage of user secrets

## Support

For issues, questions, or feature requests, please use the [GitHub Issues](https://github.com/youwwwmaster/2FA-wordpress-login/issues) page.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This plugin is licensed under GPL v2 or later.

## Author

Developed with ❤️ for WordPress security

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.