# Deploying the portfolio on Ubuntu with Caddy + MySQL

Target stack: **Ubuntu 24.04 LTS**, **Caddy 2.x**, **PHP 8.3 FPM**, **MySQL 8.0**.

The deploy kit is in `deploy/`. Deploy by cloning this repository on the server —
do not upload files by hand unless you are following the manual path below.

> **Important:** Caddy does **not** read `.htaccess` files. This deployment uses an equivalent Caddyfile that blocks `includes/`, `database/`, `dist/`, dotfiles, SQL dumps, and PHP files inside `uploads/`. Do not rely on the `.htaccess` files on this server.

---

## 1. Before you start

### DNS (needed for automatic HTTPS)

Create an **A record** pointing your domain to the server's public IPv4 address (and an **AAAA** record if the server has IPv6):

```
portfolio.example.com.   A     203.0.113.10
```

Wait for DNS to resolve (check with `dig +short portfolio.example.com`).

If you do not have a domain yet, deploy with `--domain :80` and access the site by IP over HTTP. You can switch to HTTPS later by re-running the script with your domain.

### Ports

Allow inbound **80/tcp** and **443/tcp** (and **443/udp** if you want HTTP/3):

- The deploy script opens them in UFW if UFW is active.
- **Also open them in your cloud provider's firewall / security group** (AWS, Oracle, DigitalOcean, etc.). The script cannot do that.

Make sure nothing else is using ports 80/443:

```bash
sudo ss -ltnp | grep -E ':(80|443)\b' || echo "ports free"
```

If Apache or Nginx is running, stop and disable it first:

```bash
sudo systemctl disable --now apache2 nginx 2>/dev/null || true
```

> The script expects the **official Caddy APT package** and the `caddy.service`
> systemd unit. A Snap install or a Caddy Docker container needs manual
> configuration instead.

### Access

You need SSH access with `sudo`. The script must run as root.

---

## 2. Clone the repository

The repository must contain this project at its root (that is, `index.php`,
`deploy/`, `database/`, and `includes/` are top-level entries). Push it to
GitHub first if you have not already.

On the server:

```bash
sudo apt update && sudo apt install -y git
sudo git clone https://github.com/<your-account>/<your-repo>.git /opt/portfolio
```

Use `sudo` so the checkout is owned by root; the deploy script only needs read
access to it.

For a **private** repository, authenticate with a read-only deploy key or a
fine-grained personal access token instead of your account password:

```bash
sudo git clone https://<token>@github.com/<your-account>/<your-repo>.git /opt/portfolio
```

The cloned folder should contain `index.php`, `admin/`, `includes/`, `deploy/`,
and `database/`.

---

## 3. Run the deploy script

With a domain (automatic HTTPS via Let's Encrypt):

```bash
sudo bash /opt/portfolio/deploy/ubuntu-caddy-deploy.sh \
  --domain portfolio.example.com
```

Without a domain, over HTTP by IP:

```bash
sudo bash /opt/portfolio/deploy/ubuntu-caddy-deploy.sh --domain :80
```

Optional flags:

| Flag | Default | Meaning |
| --- | --- | --- |
| `--app-dir` | `/var/www/portfolio` | Install path |
| `--db-name` | `portfolio` | MySQL database name |
| `--db-user` | `portfolio` | MySQL application user |
| `--db-pass` | reuse / generate | MySQL password |
| `--source` | parent of script | Folder to copy |
| `--skip-apt` | off | Do not install packages |
| `--skip-firewall` | off | Do not touch UFW |
| `--force-caddyfile` | off | Replace a customized `/etc/caddy/Caddyfile` (backup is made) |

> **Already hosting other sites in Caddy?** The script refuses to overwrite a custom `/etc/caddy/Caddyfile`. It only replaces the stock welcome file or a file it generated itself. Either back up the file and pass `--force-caddyfile`, then merge your other site blocks back, or copy the block from `deploy/Caddyfile.example` into your existing Caddyfile manually.

### What the script does

1. Installs Caddy (official APT repo if missing), `php-fpm`, `php-mysql`, `php-cli`, `mysql-client`, `unzip`, `rsync`.
2. Copies the app to `/var/www/portfolio`, **preserving** `uploads/` and `includes/config.local.php` on redeploys.
3. Creates the database, the app user for both `localhost` and `127.0.0.1`, and grants privileges.
4. Imports `database/portfolio.sql` **only if the database is empty**.
5. Writes `includes/config.local.php` with the real credentials.
6. Sets PHP limits (`upload_max_filesize = 10M`, `post_max_size = 12M`, `session.gc_maxlifetime = 3600`) in `/etc/php/8.3/fpm/conf.d/99-portfolio.ini`.
7. Adds the `caddy` user to the PHP-FPM socket group (otherwise Caddy gets `502 Bad Gateway`).
8. Writes and validates `/etc/caddy/Caddyfile` (backing up the old one and refusing to overwrite a custom config), then restarts Caddy.
9. Sets safe permissions: code is root-owned and read-only; only `uploads/` is writable by PHP.
10. Opens 80/443 in UFW if UFW is active.
11. Runs a local health check and prints the admin setup URL.

> The database password is printed at the end and stored in `includes/config.local.php`. Save it somewhere safe.

---

## 4. Finish the admin setup

1. Open `https://your-domain/admin/setup.php` (or `http://<server-ip>/admin/setup.php` in IP mode).
2. Set `ADMIN_SETUP_KEY` in `/var/www/portfolio/includes/config.local.php` to a private random string of **at least 24 characters**:

```bash
sudo nano /var/www/portfolio/includes/config.local.php
```

```php
'ADMIN_SETUP_KEY' => 'blue-Otter-47-river-Stone-9182-quiet',
```

3. Reload the setup page, enter the key, your email, and a password of **at least 12 characters**.
4. After it succeeds:

```bash
sudo nano /var/www/portfolio/includes/config.local.php   # set ADMIN_SETUP_KEY => ''
sudo rm -f /var/www/portfolio/admin/setup.php
```

5. Sign in at `/admin/login.php` and fill in profile, skills, projects, education, experience, achievements, and site settings.

On future redeploys, the script automatically removes `admin/setup.php` if an administrator account already exists.

---

## 5. Verify

```bash
sudo systemctl status caddy --no-pager
sudo systemctl status php8.3-fpm --no-pager
curl -I https://your-domain/
```

Checklist:

- [ ] `/` loads and shows the portfolio
- [ ] `/about.php`, `/skills.php`, `/projects.php`, `/resume.php`, `/contact.php` load
- [ ] `/about.html` redirects to `/about.php`
- [ ] Contact form saves a message (visible under **Messages** in admin)
- [ ] Admin login works and uploads succeed
- [ ] `https://your-domain/includes/config.local.php` returns **404**
- [ ] `https://your-domain/database/portfolio.sql` returns **404**
- [ ] `https://your-domain/uploads/.htaccess` returns **404**
- [ ] HTTP redirects to HTTPS (domain mode)

---

## About the SQL migrations

`database/portfolio.sql` is the **complete, current schema**, including:

- `skills.description`, `skills.level_label`, `skills.level_percent`
- the education and experience seed rows

**Do not run the files in `database/migrations/` on a fresh install.** In particular:

- `2026-11-18-skills-fields.sql` adds columns that already exist →
  `ERROR 1060 (42S21): Duplicate column name 'description'`
- `2026-11-18-resume-data.sql` inserts records that are already present

Migrations are only for upgrading a database created from the **older** schema. On a fresh deploy, importing `portfolio.sql` is all you need.

If you ever need to run a migration deliberately:

```bash
sudo mysql portfolio < /var/www/portfolio/database/migrations/2026-11-18-skills-fields.sql
```

---

## Manual deployment (if you prefer not to use the script)

### Packages

```bash
sudo apt update
sudo apt install -y php-fpm php-mysql php-cli mysql-client unzip rsync
# Install Caddy from the official APT repository:
# https://caddyserver.com/docs/install#debian-ubuntu-raspbian
```

### Files

```bash
sudo mkdir -p /var/www/portfolio
sudo rsync -a --exclude '.git/' /opt/portfolio/ /var/www/portfolio/
sudo mkdir -p /var/www/portfolio/uploads/{profile,projects,achievements}
```

### Database

```bash
sudo mysql <<'SQL'
CREATE DATABASE portfolio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'portfolio'@'localhost' IDENTIFIED BY 'CHANGE-ME';
CREATE USER 'portfolio'@'127.0.0.1' IDENTIFIED BY 'CHANGE-ME';
GRANT ALL PRIVILEGES ON portfolio.* TO 'portfolio'@'localhost';
GRANT ALL PRIVILEGES ON portfolio.* TO 'portfolio'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

sudo mysql portfolio < /var/www/portfolio/database/portfolio.sql
```

Create `includes/config.local.php`:

```php
<?php

return [
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => 3306,
    'DB_NAME' => 'portfolio',
    'DB_USER' => 'portfolio',
    'DB_PASSWORD' => 'CHANGE-ME',
    'ADMIN_SETUP_KEY' => '',
];
```

### Permissions

```bash
sudo chown -R root:www-data /var/www/portfolio
sudo find /var/www/portfolio -type d -exec chmod 755 {} +
sudo find /var/www/portfolio -type f -exec chmod 644 {} +
sudo chown -R www-data:www-data /var/www/portfolio/uploads
sudo find /var/www/portfolio/uploads -type d -exec chmod 775 {} +
sudo find /var/www/portfolio/uploads -type f -exec chmod 664 {} +
sudo chown root:www-data /var/www/portfolio/includes/config.local.php
sudo chmod 640 /var/www/portfolio/includes/config.local.php
```

### PHP-FPM socket access

```bash
sudo usermod -aG www-data caddy
sudo systemctl restart caddy
stat -c '%U %G %a' /run/php/php8.3-fpm.sock
```

### Caddyfile

If Caddy already serves other sites, **add the site block** from `deploy/Caddyfile.example` to your existing `/etc/caddy/Caddyfile` instead of replacing the file. On a fresh server you can copy the whole example. Replace the domain, path, and PHP socket, then:

```bash
sudo caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile
sudo systemctl restart caddy
```

---

## Redeploying updates

1. Pull the latest code:

```bash
cd /opt/portfolio
sudo git pull --ff-only
```

2. Re-run the same deploy command:

```bash
sudo bash /opt/portfolio/deploy/ubuntu-caddy-deploy.sh --domain portfolio.example.com
```

The script copies the updated files, preserves `uploads/` and the existing
database password, skips the schema import because the database is not empty,
and removes `admin/setup.php` once an administrator account exists.

If you changed `includes/config.local.php` manually (for example to set an admin
setup key), back it up first — the script preserves the existing key and
password, but rewrites the file.

If `git pull` reports a conflict because a tracked file changed on the server,
restore it and pull again:

```bash
sudo git -C /opt/portfolio checkout -- .
sudo git -C /opt/portfolio pull --ff-only
```

---

## Troubleshooting

### 502 Bad Gateway

Caddy cannot reach PHP-FPM.

```bash
sudo systemctl status php8.3-fpm --no-pager
ls -l /run/php/php*-fpm.sock
sudo journalctl -u caddy -n 50 --no-pager
```

Usually the socket path in the Caddyfile is wrong for your PHP version, or the `caddy` user is not in the socket's group:

```bash
sudo usermod -aG www-data caddy
sudo systemctl restart caddy
```

### 500 Internal Server Error

```bash
sudo tail -n 50 /var/log/php8.3-fpm.log
sudo journalctl -u php8.3-fpm -n 50 --no-pager
```

Common causes: missing `includes/config.local.php`, wrong DB credentials, or a PHP fatal error. Temporarily enable errors only while debugging:

```bash
echo 'display_errors = On' | sudo tee /etc/php/8.3/fpm/conf.d/98-debug.ini
sudo systemctl restart php8.3-fpm
# remove the file when done
```

### "Database settings are missing"

`includes/config.local.php` is absent or unreadable:

```bash
sudo ls -l /var/www/portfolio/includes/config.local.php
sudo -u www-data php -r 'var_dump(is_array(require "/var/www/portfolio/includes/config.local.php"));'
```

### "Unable to connect to the database"

Test the app user directly:

```bash
mysql -h 127.0.0.1 -u portfolio -p portfolio -e 'SHOW TABLES;'
```

Check the host (`127.0.0.1`), the password, and that the user was created for both `localhost` and `127.0.0.1`.

### 404 on `about.html`

The Caddyfile's redirect block is missing or the site was not reloaded:

```bash
sudo grep -n 'path_regexp legacy' /etc/caddy/Caddyfile
sudo systemctl reload caddy
```

### Uploads fail

```bash
sudo chown -R www-data:www-data /var/www/portfolio/uploads
sudo chmod -R u+rwX,g+rwX /var/www/portfolio/uploads
sudo tail -n 50 /var/log/php8.3-fpm.log
```

Also confirm `upload_max_filesize` / `post_max_size` in `/etc/php/8.3/fpm/conf.d/99-portfolio.ini` after `phpinfo()` or `php -i`.

### Automatic HTTPS did not work

```bash
dig +short portfolio.example.com
sudo journalctl -u caddy -n 100 --no-pager | grep -i -E 'acme|tls|certificate'
```

Check that DNS points to this server and that ports 80/443 are open in **both** UFW and the cloud security group. If you hit Let's Encrypt rate limits while testing, use `--domain :80` temporarily.

### Caddy fails to start

```bash
sudo caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile
sudo systemctl status caddy --no-pager
```

---

## Backups

Database:

```bash
mysqldump --single-transaction portfolio | gzip > ~/portfolio-$(date +%F).sql.gz
```

Uploads and config:

```bash
sudo tar czf ~/portfolio-files-$(date +%F).tar.gz \
  -C /var/www/portfolio uploads includes/config.local.php
```

Restore the database:

```bash
gunzip -c ~/portfolio-2026-11-19.sql.gz | sudo mysql portfolio
```

---

## Security notes

- `includes/config.local.php` is `640 root:www-data`; only root and PHP can read it.
- Application code is root-owned and read-only to PHP; only `uploads/` is writable.
- `database/`, `includes/`, `dist/`, dotfiles, `.sql`, and `.md` files return 404 via Caddy.
- PHP execution is blocked inside `uploads/` by the Caddy matcher.
- The firewall only opens 80/443; SSH is left untouched.
- After setup, delete `admin/setup.php` and clear `ADMIN_SETUP_KEY`.
- Run `sudo apt update && sudo apt upgrade` regularly; Caddy renews certificates automatically.
