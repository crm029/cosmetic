# Security Policy

## Supported version

Security fixes are applied to the current default branch.

## Reporting a vulnerability

Do not publish exploit details, credentials, customer information, or an unpatched vulnerability in a public issue. Report it privately to the repository owner through the private contact channel associated with this repository. Include the affected path, impact, reproducible steps, and (if available) a minimal proof of concept. Do not access, modify, or download other users' data while validating a report.

## Operational rules

- Never commit .env files, API keys, merchant IDs, SMS passwords, production database credentials, customer exports, or private uploads.
- Use environment secrets on the server and rotate any credential that may have been exposed.
- Keep dependencies updated and run Composer's security audit.
- Deploy only with APP_DEBUG disabled, HTTPS enabled, and the web document root pointed at public/.
- Back up the database and uploaded assets, and test restoration.
- Review docs/SECURITY_AUDIT.md before every production release.

A security report is not permission to test systems or accounts that you do not own or administer.
