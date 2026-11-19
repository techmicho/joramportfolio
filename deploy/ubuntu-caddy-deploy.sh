#!/usr/bin/env bash
#
# Deploy the Joram Rusiana portfolio on Ubuntu with Caddy + PHP-FPM + MySQL.
#
# Target: Ubuntu 22.04 / 24.04, Caddy 2.x, PHP 8.1+, MySQL 8 / MariaDB.
# Run with sudo. Re-running is safe: uploads and config.local.php are preserved.
#
# Usage:
#   sudo bash deploy/ubuntu-caddy-deploy.sh --domain portfolio.example.com
#   sudo bash deploy/ubuntu-caddy-deploy.sh --domain :80          # IP-only, no HTTPS
#
set -Eeuo pipefail

APP_DIR="/var/www/portfolio"
APP_USER="www-data"
SITE_ADDRESS=""
SOURCE=""
DB_NAME="portfolio"
DB_USER="portfolio"
DB_PASS=""
SKIP_APT=0
SKIP_FIREWALL=0
FORCE_CADDYFILE=0

log()  { printf '\033[1;34m==>\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m[!]\033[0m %s\n' "$*" >&2; }
die()  { printf '\033[1;31m[x]\033[0m %s\n' "$*" >&2; exit 1; }

usage() {
    cat <<'EOF'
Deploy the portfolio on Ubuntu with Caddy, PHP-FPM, and MySQL.

Options:
  --domain <host>    Public domain, e.g. portfolio.example.com.
                     Use :80 (or :8080) for HTTP-only/IP access.
  --app-dir <path>   Install directory (default: /var/www/portfolio).
  --source <path>    Project folder to copy (default: parent of this script).
  --db-name <name>   Database name (default: portfolio).
  --db-user <name>   Database user (default: portfolio).
  --db-pass <pass>   Database password (default: reuse existing / generate).
  --skip-apt         Do not install packages.
  --skip-firewall    Do not touch UFW even if it is active.
  --force-caddyfile  Replace a custom /etc/caddy/Caddyfile (a backup is made).
  -h, --help         Show this help.

Examples:
  sudo bash deploy/ubuntu-caddy-deploy.sh --domain portfolio.example.com
  sudo bash deploy/ubuntu-caddy-deploy.sh --domain :80
EOF
}

trap 'status=$?; if [[ $status -ne 0 ]]; then printf "\033[1;31m[x]\033[0m Failed at line %s (exit %s)\n" "$LINENO" "$status" >&2; fi' ERR

while [[ $# -gt 0 ]]; do
    case "$1" in
        --domain)        SITE_ADDRESS="${2:-}"; shift 2 ;;
        --app-dir)       APP_DIR="${2:-}"; shift 2 ;;
        --source)        SOURCE="${2:-}"; shift 2 ;;
        --db-name)       DB_NAME="${2:-}"; shift 2 ;;
        --db-user)       DB_USER="${2:-}"; shift 2 ;;
        --db-pass)       DB_PASS="${2:-}"; shift 2 ;;
        --skip-apt)      SKIP_APT=1; shift ;;
        --skip-firewall) SKIP_FIREWALL=1; shift ;;
        --force-caddyfile) FORCE_CADDYFILE=1; shift ;;
        -h|--help)       usage; exit 0 ;;
        *)               die "Unknown option: $1 (use --help)" ;;
    esac
done

# ---------------------------------------------------------------- preflight --

[[ "${EUID:-$(id -u)}" -eq 0 ]] || die "Run this script with sudo."

if [[ -r /etc/os-release ]]; then
    # shellcheck disable=SC1091
    . /etc/os-release
    if [[ "${ID:-}" != "ubuntu" ]]; then
        warn "This script targets Ubuntu (detected: ${PRETTY_NAME:-unknown}). Continuing."
    fi
fi

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
if [[ -z "$SOURCE" ]]; then
    SOURCE="$(cd -- "$SCRIPT_DIR/.." && pwd)"
fi
[[ -f "$SOURCE/index.php" ]] || die "Source folder does not contain index.php: $SOURCE"
SOURCE="$(realpath "$SOURCE")"
APP_DIR="$(realpath -m "$APP_DIR")"

# Normalise the Caddy site address.
SITE_ADDRESS="${SITE_ADDRESS#http://}"
SITE_ADDRESS="${SITE_ADDRESS#https://}"
SITE_ADDRESS="${SITE_ADDRESS%%/}"
if [[ -z "$SITE_ADDRESS" ]]; then
    SITE_ADDRESS=":80"
    warn "No --domain given; serving on port 80 without automatic HTTPS."
fi
if [[ "$SITE_ADDRESS" != :* ]]; then
    [[ "$SITE_ADDRESS" =~ ^[A-Za-z0-9.-]+$ ]] || die "Invalid domain: $SITE_ADDRESS"
    [[ "$SITE_ADDRESS" == *.* ]] || die "Use a full domain (e.g. portfolio.example.com) or :80."
fi

# Database identifiers are interpolated into SQL; keep them strict.
[[ "$DB_NAME" =~ ^[A-Za-z0-9_]+$ ]] || die "DB name may only contain letters, digits, and underscores."
[[ "$DB_USER" =~ ^[A-Za-z0-9_]+$ ]] || die "DB user may only contain letters, digits, and underscores."
[[ "$DB_USER" != "root" ]] || die "Do not use the MySQL root account for the application."
if [[ -n "$DB_PASS" ]]; then
    PW_RE='^[A-Za-z0-9._~!@#%^&*+=:-]+$'
    [[ "$DB_PASS" =~ $PW_RE ]] || die "DB password may only use letters, digits, and ._~!@#%^&*+=:- (no quotes, spaces, or backslashes)."
fi

# ---------------------------------------------------------------- packages ---

if [[ $SKIP_APT -eq 0 ]]; then
    export DEBIAN_FRONTEND=noninteractive
    log "Installing packages (Caddy, PHP-FPM, MySQL client, tools)"
    apt-get update -y
    apt-get install -y \
        ca-certificates curl gnupg debian-keyring debian-archive-keyring apt-transport-https \
        unzip rsync mysql-client php-cli php-fpm php-mysql openssl

    if ! command -v caddy >/dev/null 2>&1; then
        log "Adding the official Caddy APT repository"
        curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' \
            | gpg --batch --yes --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
        curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' \
            > /etc/apt/sources.list.d/caddy-stable.list
        chmod o+r /usr/share/keyrings/caddy-stable-archive-keyring.gpg \
                   /etc/apt/sources.list.d/caddy-stable.list
        apt-get update -y
        apt-get install -y caddy
    fi
else
    command -v caddy   >/dev/null 2>&1 || die "caddy is not installed (and --skip-apt was used)."
    command -v php     >/dev/null 2>&1 || die "php is not installed (and --skip-apt was used)."
    command -v mysql   >/dev/null 2>&1 || die "mysql client is not installed (and --skip-apt was used)."
    command -v rsync   >/dev/null 2>&1 || die "rsync is required (and --skip-apt was used)."
    command -v curl    >/dev/null 2>&1 || die "curl is required (and --skip-apt was used)."
    command -v openssl >/dev/null 2>&1 || die "openssl is required (and --skip-apt was used)."
fi

# --------------------------------------------------------------- copy files --

if [[ "$SOURCE" != "$APP_DIR" ]]; then
    log "Copying application files to $APP_DIR"
    mkdir -p "$APP_DIR"
    rsync -a --delete \
        --exclude '.git/' \
        --exclude 'dist/' \
        --exclude 'includes/config.local.php' \
        --exclude 'uploads/profile/*' \
        --exclude 'uploads/projects/*' \
        --exclude 'uploads/achievements/*' \
        "$SOURCE"/ "$APP_DIR"/
else
    log "Source and install directory are the same; skipping copy."
fi
mkdir -p "$APP_DIR"/uploads/{profile,projects,achievements}

[[ -f "$APP_DIR/database/portfolio.sql" ]] || die "database/portfolio.sql is missing from $APP_DIR"

# ----------------------------------------------------------------- database ---

command -v mysql >/dev/null 2>&1 || die "The mysql client is required."
mysql -e 'SELECT 1' >/dev/null 2>&1 \
    || die "Cannot log in to MySQL as root over the local socket. Run 'sudo mysql' to verify access."

CONFIG="$APP_DIR/includes/config.local.php"
EXISTING_KEY=""
if [[ -f "$CONFIG" ]]; then
    EXISTING_KEY=$(php -r '$c = require $argv[1]; echo is_array($c) ? ($c["ADMIN_SETUP_KEY"] ?? "") : "";' "$CONFIG" 2>/dev/null || true)
    if [[ -z "$DB_PASS" ]]; then
        DB_PASS=$(php -r '$c = require $argv[1]; echo is_array($c) ? ($c["DB_PASSWORD"] ?? "") : "";' "$CONFIG" 2>/dev/null || true)
        [[ -n "$DB_PASS" ]] && log "Reusing the database password from the existing config."
    fi
fi
if [[ -z "$DB_PASS" ]]; then
    DB_PASS="$(openssl rand -hex 16)"
    log "Generated a new database password."
fi

log "Creating database '$DB_NAME' and user '$DB_USER'"
mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

TABLE_COUNT=$(mysql -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}'")
if [[ "${TABLE_COUNT:-0}" -eq 0 ]]; then
    log "Importing database/portfolio.sql"
    mysql "$DB_NAME" < "$APP_DIR/database/portfolio.sql"
else
    log "Database already has $TABLE_COUNT tables; skipping schema import."
fi

log "Writing includes/config.local.php"
DB_HOST=127.0.0.1 DB_NAME="$DB_NAME" DB_USER="$DB_USER" DB_PASS="$DB_PASS" \
ADMIN_KEY="$EXISTING_KEY" CONFIG_PATH="$CONFIG" \
php -r '
$data = [
    "DB_HOST"       => getenv("DB_HOST"),
    "DB_PORT"       => 3306,
    "DB_NAME"       => getenv("DB_NAME"),
    "DB_USER"       => getenv("DB_USER"),
    "DB_PASSWORD"   => getenv("DB_PASS"),
    "ADMIN_SETUP_KEY" => getenv("ADMIN_KEY"),
];
file_put_contents(getenv("CONFIG_PATH"), "<?php\n\nreturn " . var_export($data, true) . ";\n");
'

# ------------------------------------------------------------------- php-fpm --

PHP_VER=$(ls -d /etc/php/*/fpm 2>/dev/null | sed -E 's|/etc/php/([^/]+)/fpm|\1|' | sort -V | tail -n1 || true)
if [[ -z "$PHP_VER" ]]; then
    PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || true)
fi
[[ -n "$PHP_VER" ]] || die "Could not determine the installed PHP version."

# Make sure the selected PHP version has MySQL support.
if [[ $SKIP_APT -eq 0 ]] && apt-cache show "php${PHP_VER}-mysql" >/dev/null 2>&1; then
    apt-get install -y "php${PHP_VER}-mysql" >/dev/null
fi
if command -v "php${PHP_VER}" >/dev/null 2>&1; then
    "php${PHP_VER}" -m | grep -qi '^pdo_mysql$' \
        || die "PHP ${PHP_VER} is missing the pdo_mysql extension."
fi

log "Starting PHP $PHP_VER FPM"
systemctl enable --now "php${PHP_VER}-fpm"

PHP_SOCK="/run/php/php${PHP_VER}-fpm.sock"
for _ in $(seq 1 15); do
    [[ -S "$PHP_SOCK" ]] && break
    sleep 1
done
[[ -S "$PHP_SOCK" ]] || die "PHP-FPM socket not found at $PHP_SOCK."

log "Applying PHP upload/session limits"
INI_DIR="/etc/php/${PHP_VER}/fpm/conf.d"
mkdir -p "$INI_DIR"
cat > "$INI_DIR/99-portfolio.ini" <<'INI'
; Portfolio-specific PHP settings
upload_max_filesize = 10M
post_max_size = 12M
memory_limit = 256M
max_execution_time = 60
session.gc_maxlifetime = 3600
display_errors = Off
log_errors = On
INI
systemctl restart "php${PHP_VER}-fpm"

# Caddy runs as the 'caddy' user; give it access to the FPM socket group.
SOCK_GROUP=$(stat -c '%G' "$PHP_SOCK")
if id caddy >/dev/null 2>&1; then
    usermod -aG "$SOCK_GROUP" caddy || warn "Could not add caddy to group $SOCK_GROUP."
fi

# -------------------------------------------------------------------- caddy --

CADDYFILE="/etc/caddy/Caddyfile"
CADDY_MARKER="# Managed by deploy/ubuntu-caddy-deploy.sh"

if [[ -f "$CADDYFILE" ]] && ! grep -qF "$CADDY_MARKER" "$CADDYFILE"; then
    if grep -qF "/usr/share/caddy" "$CADDYFILE" \
            && grep -qF "Set this path to your site" "$CADDYFILE"; then
        log "Replacing the stock Caddy welcome Caddyfile."
    elif [[ $FORCE_CADDYFILE -eq 0 ]]; then
        die "Existing $CADDYFILE looks customized. Back it up and re-run with --force-caddyfile, or merge deploy/Caddyfile.example manually."
    fi
fi

log "Writing $CADDYFILE for $SITE_ADDRESS"
if [[ -f "$CADDYFILE" ]]; then
    cp -a "$CADDYFILE" "${CADDYFILE}.bak.$(date +%Y%m%d%H%M%S)"
fi

cat > "$CADDYFILE" <<'CADDY'
# Managed by deploy/ubuntu-caddy-deploy.sh
__SITE_ADDRESS__ {
	root * __APP_DIR__
	encode zstd gzip

	# Legacy static URLs -> PHP pages
	@legacy path_regexp legacy ^/(index|about|skills|projects|resume|contact)\.html$
	redir @legacy /{re.legacy.1}.php 301

	# /admin has no index file; send visitors to the login screen
	@adminRoot path /admin /admin/
	redir @adminRoot /admin/login.php 302

	# Never expose internals, SQL dumps, dotfiles, or executable uploads
	@blocked path_regexp blocked `^/(?:includes|database|dist|deploy)(?:/|$)|^/uploads/.*\.(?:php|phtml|phar)(?:/|$)|\.sql$|\.md$|(?:^|/)\.[^/]+`
	handle @blocked {
		respond 404
	}

	@static path /css/* /js/* /images/* /uploads/*
	header @static Cache-Control "public, max-age=604800"

	@nocache path /css/style.css /js/script.js
	header @nocache Cache-Control "no-cache, no-store, must-revalidate"

	handle {
		php_fastcgi unix/__PHP_SOCK__ {
			try_files {path} {path}/index.php =404
		}
		file_server
	}
}
CADDY

sed -i \
    -e "s|__SITE_ADDRESS__|${SITE_ADDRESS}|g" \
    -e "s|__APP_DIR__|${APP_DIR}|g" \
    -e "s|__PHP_SOCK__|${PHP_SOCK}|g" \
    "$CADDYFILE"

caddy validate --config "$CADDYFILE" --adapter caddyfile >/dev/null \
    || die "Caddyfile validation failed. Inspect $CADDYFILE."

systemctl cat caddy.service >/dev/null 2>&1 \
    || die "The caddy.service unit was not found. Install Caddy from the official APT repository (Snap/Docker installs need manual setup)."

systemctl enable caddy
systemctl restart caddy

# -------------------------------------------------------------- permissions --

log "Setting file permissions"
chown -R root:"$APP_USER" "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 755 {} +
find "$APP_DIR" -type f -exec chmod 644 {} +

# Only uploads are writable by PHP.
chown -R "$APP_USER":"$APP_USER" "$APP_DIR/uploads"
find "$APP_DIR/uploads" -type d -exec chmod 775 {} +
find "$APP_DIR/uploads" -type f -exec chmod 664 {} +

chown root:"$APP_USER" "$CONFIG"
chmod 640 "$CONFIG"

# Remove the setup endpoint once an administrator exists.
ADMIN_COUNT=$(mysql -N -B "$DB_NAME" -e "SELECT COUNT(*) FROM admin_users" 2>/dev/null || echo 0)
if [[ "${ADMIN_COUNT:-0}" -gt 0 && -f "$APP_DIR/admin/setup.php" ]]; then
    rm -f "$APP_DIR/admin/setup.php"
    log "An administrator already exists; removed admin/setup.php."
fi

# ----------------------------------------------------------------- firewall ---

if [[ $SKIP_FIREWALL -eq 0 ]] && command -v ufw >/dev/null 2>&1 \
        && ufw status 2>/dev/null | grep -q '^Status: active'; then
    log "Opening ports 80 and 443 in UFW"
    ufw allow 80/tcp  >/dev/null
    ufw allow 443/tcp >/dev/null
    ufw allow 443/udp >/dev/null
fi

# -------------------------------------------------------------- health check --

sleep 2
if [[ "$SITE_ADDRESS" == :* ]]; then
    PORT="${SITE_ADDRESS#:}"
    HEALTH_URL="http://127.0.0.1:${PORT}/"
    CURL_ARGS=(-s)
    if [[ "$PORT" == "80" ]]; then
        PUBLIC_URL="http://<server-ip>"
    else
        PUBLIC_URL="http://<server-ip>:${PORT}"
    fi
else
    # Connect to local Caddy with the real SNI/Host so PHP and MySQL are exercised.
    HEALTH_URL="https://${SITE_ADDRESS}/"
    CURL_ARGS=(-sk --resolve "${SITE_ADDRESS}:443:127.0.0.1")
    PUBLIC_URL="https://${SITE_ADDRESS}"
fi

HTTP_CODE=""
for _ in $(seq 1 5); do
    HTTP_CODE=$(curl "${CURL_ARGS[@]}" -o /dev/null -w '%{http_code}' "$HEALTH_URL" || true)
    [[ "$HTTP_CODE" == "200" ]] && break
    sleep 2
done

cat <<EOF

────────────────────────────────────────────────────────────
 Deployment finished
────────────────────────────────────────────────────────────
 App directory : ${APP_DIR}
 Caddyfile     : /etc/caddy/Caddyfile
 PHP-FPM       : php${PHP_VER}-fpm (${PHP_SOCK})
 Database      : ${DB_NAME} (user: ${DB_USER}, host: 127.0.0.1)
 DB password   : ${DB_PASS}
 Site URL      : ${PUBLIC_URL}
 Local check   : HTTP ${HTTP_CODE:-no response}

 Next steps:
 1. Open ${PUBLIC_URL}/admin/setup.php
 2. Create the administrator account.
 3. Set ADMIN_SETUP_KEY back to '' in
    ${APP_DIR}/includes/config.local.php
    and delete ${APP_DIR}/admin/setup.php.
 4. Sign in at ${PUBLIC_URL}/admin/login.php and fill in your content.

 If HTTP is 500 or 502:
   journalctl -u caddy -n 50 --no-pager
   journalctl -u php${PHP_VER}-fpm -n 50 --no-pager
   tail -n 50 /var/log/php${PHP_VER}-fpm.log
────────────────────────────────────────────────────────────
EOF

if [[ "$HTTP_CODE" != "200" ]]; then
    warn "The local health check returned HTTP ${HTTP_CODE:-<none>}. See the log commands above."
fi
