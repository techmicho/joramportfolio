# Joram Rusiana Personal Portfolio

Dynamic PHP/MySQL personal portfolio with an authenticated administrator dashboard.

## Features

- Database-driven profile, skills, projects, education, experience, achievements, and contact messages
- Complete public portfolio pages and responsive light/dark interface
- Admin authentication with password hashing, session protection, inactivity logout, and login lockout
- CSRF protection, prepared statements, output escaping, server-side validation, and spam protection
- Secure JPG, PNG, WEBP, GIF, and PDF certificate/project/profile uploads
- Website title, accent color, and logo settings

## Requirements

- PHP 8.0 or later with PDO MySQL enabled
- MySQL 5.7 or later
- A web server configured to run PHP

## Deployment

Deploy on Ubuntu 24.04 with Caddy, PHP-FPM, and MySQL by cloning this repository
on the server:

```bash
sudo git clone https://github.com/<your-account>/<your-repo>.git /opt/portfolio
sudo bash /opt/portfolio/deploy/ubuntu-caddy-deploy.sh --domain portfolio.example.com
```

The script installs the packages, creates the database, imports
`database/portfolio.sql`, writes `includes/config.local.php`, configures
PHP-FPM and Caddy, and sets safe permissions.

See [`DEPLOY-UBUNTU-CADDY.md`](DEPLOY-UBUNTU-CADDY.md) for prerequisites, DNS,
the manual (non-script) path, updates with `git pull`, backups, and
troubleshooting.

No credentials are stored in this repository. `includes/config.local.php` and
uploaded media are intentionally ignored.

## Database setup

1. Create a MySQL database and database user.
2. Import `database/portfolio.sql` (the full, current schema).
3. Configure `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` using server environment variables, or copy `includes/config.local.php.example` to `includes/config.local.php` and enter the values locally. Do not commit `config.local.php`.

> The files in `database/migrations/` are only for upgrading a database created
> from the older schema. They are **not** needed on a fresh install and will
> fail with "Duplicate column name" if run against `portfolio.sql`.

## Administrator setup

No password is included in the SQL export. Create the first administrator either:

**Web setup (recommended for hosting):** set a private `ADMIN_SETUP_KEY` of at
least 24 characters in `includes/config.local.php`, open `/admin/setup.php`,
create the account, then set the key back to `''` and delete
`admin/setup.php`.

**CLI (local development):**

```bash
php database/create_admin.php admin@example.com "replace-with-a-long-private-password"
```

Then sign in at `/admin/login.php`. Record the production password privately and do not commit or publish it.

## Security

- Never commit database credentials or administrator passwords.
- Use HTTPS for login and administration pages.
- Keep `includes/config.local.php` excluded from Git.
- Limit the administrator account and hosting permissions to trusted users.
