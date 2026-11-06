# Security Policy

## Supported Versions

We release patches for security vulnerabilities. Currently supported versions:

| Version | Supported          |
| ------- | ------------------ |
| 0.2.x   | :white_check_mark: |
| < 0.2   | :x:                |

## Reporting a Vulnerability

**Please do not report security vulnerabilities through public GitHub issues.**

If you discover a security vulnerability in Guardian 2FA, please report it privately:

### How to Report

1. **Email:** Send details to the repository owner (check GitHub profile)
2. **GitHub Security Advisory:** Use [GitHub's private vulnerability reporting](https://github.com/Asia-Dev-Team/2FA-wordpress-login/security/advisories/new)

### What to Include

Please include the following information:
- Type of vulnerability
- Full paths of source file(s) related to the vulnerability
- Location of the affected source code (tag/branch/commit)
- Step-by-step instructions to reproduce the issue
- Proof-of-concept or exploit code (if possible)
- Impact of the vulnerability

### Response Timeline

- **Initial Response:** Within 48 hours
- **Status Update:** Within 7 days
- **Fix Timeline:** Depends on severity
  - Critical: 1-7 days
  - High: 7-14 days
  - Medium: 14-30 days
  - Low: 30-90 days

## Security Best Practices

When using Guardian 2FA:

1. **Keep Updated:** Always use the latest version
2. **Strong Passwords:** 2FA is additional security, not a replacement for strong passwords
3. **Lost phone:** An administrator can turn off 2FA for the user on their profile page
4. **HTTPS:** Always use HTTPS on your WordPress site
5. **Regular Backups:** Maintain regular site backups

## Known Security Considerations

- Secret keys are stored per user in the WordPress database in plain text
- QR codes are generated server-side; use HTTPS
- At most 5 code attempts per login; a code cannot be reused
- XML-RPC login is refused for users with 2FA
- No sensitive data is logged

## Security Updates

Security updates will be:
- Released as soon as possible
- Documented in [CHANGELOG.md](CHANGELOG.md)
- Announced in GitHub releases
- Tagged with `[SECURITY]` prefix in commit messages

## Credits

We appreciate responsible disclosure. Security researchers who report valid vulnerabilities will be:
- Credited in the CHANGELOG (unless they prefer to remain anonymous)
- Mentioned in the GitHub release notes
- Given our eternal gratitude ❤️

---

Thank you for helping keep Guardian 2FA and its users safe!