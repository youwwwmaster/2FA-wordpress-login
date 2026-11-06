# Changelog

All notable changes to Guardian 2FA will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Planned Features
- Admin controls to enforce 2FA for specific user roles
- Backup codes for account recovery
- Email-based 2FA as alternative to authenticator apps
- Trusted devices management
- 2FA usage statistics for admins

## [0.2.0] - 2026-11-06

### Security
- Each user has their own TOTP secret; the single site-wide secret is removed.
- The pending 2FA login is bound to the browser with a random HttpOnly cookie token. In 0.1.x one global record held the pending user, so concurrent logins could end in another user's account.
- At most 5 code attempts per login.
- A code cannot be reused (last accepted time step stored per user).
- XML-RPC login is refused for users with 2FA; in 0.1.x it skipped the 2FA step.
- Constant-time code comparison; secrets are 160 bits instead of 80.
- `qrcode.php` no longer works as a public QR generator endpoint.

### Changed
- 2FA setup moved from the settings page to each user's profile page, with confirmation by code.
- Plugin name in WordPress is **Guardian 2FA** (was "Freepass 2FA").

### Removed
- AJAX secret generator and `admin.js`.

### Upgrade
- The 0.1.x site-wide secret is deleted on activation; users set up 2FA again on their profiles.

## [0.1.0] - 2025-11-28

### Added
- Initial project structure
- GPL v2 license
- Comprehensive README with roadmap
- Donation links for project support
- Basic 2FA functionality via Google Authenticator
- QR code generation
- User profile integration

### Security
- TOTP (Time-based One-Time Password) implementation
- Secure secret key generation

---

## Version Format

- **[Unreleased]** - Upcoming features and changes
- **[X.Y.Z]** - Released versions
  - **X** (Major) - Breaking changes
  - **Y** (Minor) - New features, backwards compatible
  - **Z** (Patch) - Bug fixes, backwards compatible

## Change Categories

- **Added** - New features
- **Changed** - Changes in existing functionality
- **Deprecated** - Soon-to-be removed features
- **Removed** - Removed features
- **Fixed** - Bug fixes
- **Security** - Security improvements