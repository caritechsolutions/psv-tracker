#!/bin/bash
#
# PSV Tracker - installer
#
# Run on a fresh Ubuntu server:
#   curl -sSL "https://raw.githubusercontent.com/caritechsolutions/psv-tracker/main/install.sh?v=$(date +%s)" | sudo bash
#
set -euo pipefail

REPO_GIT="https://github.com/caritechsolutions/psv-tracker.git"
APP_DIR="/opt/psv-tracker"
DB_NAME="psv_tracker"
DB_USER="psv_user"
DB_PASS=""   # set below

log() { echo -e "\n[psv-tracker] $*"; }

check_root() {
    if [ "$(id -u)" != "0" ]; then
        echo "This script must be run as root (use sudo)." >&2
        exit 1
    fi
}

install_dependencies() {
    log "Installing packages..."
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -y
    apt-get install -y git curl openssl nginx mariadb-server \
        php-fpm php-mysql php-cli php-mbstring php-xml php-curl
}

clone_or_update_repo() {
    if [ -d "$APP_DIR/.git" ]; then
        log "Repo already present, pulling latest..."
        git config --global --add safe.directory "$APP_DIR" 2>/dev/null || true
        git -C "$APP_DIR" pull --ff-only
    else
        log "Cloning repo to $APP_DIR..."
        git clone "$REPO_GIT" "$APP_DIR"
    fi
}

setup_database() {
    log "Ensuring MariaDB is running..."
    systemctl enable --now mariadb

    log "Creating database..."
    mysql -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

    local CONFIG="$APP_DIR/api/config.php"
    if [ ! -f "$CONFIG" ]; then
        log "Generating DB user and api/config.php..."
        DB_PASS="$(openssl rand -hex 16)"
        mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
        mysql -e "ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
        mysql -e "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost'; FLUSH PRIVILEGES;"
        cat > "$CONFIG" <<EOF
<?php
return [
    'db_host'    => '127.0.0.1',
    'db_name'    => '$DB_NAME',
    'db_user'    => '$DB_USER',
    'db_pass'    => '$DB_PASS',
    'db_charset' => 'utf8mb4',
];
EOF
        chown root:www-data "$CONFIG"
        chmod 640 "$CONFIG"
    else
        log "api/config.php already exists, keeping existing credentials."
        DB_PASS="$(php -r "\$c=require '$CONFIG'; echo \$c['db_pass'];")"
    fi
}

run_migrations() {
    log "Running database migrations..."
    # shellcheck source=/dev/null
    source "$APP_DIR/schema/migrate.sh"
    apply_migrations "$DB_NAME" "$DB_USER" "$DB_PASS" "$APP_DIR/schema"
}

setup_nginx() {
    log "Configuring nginx..."
    local SOCK
    SOCK="$(ls /run/php/php*-fpm.sock 2>/dev/null | head -1)"
    if [ -z "$SOCK" ]; then
        echo "Could not find a php-fpm socket under /run/php/." >&2
        exit 1
    fi
    sed "s#PHP_FPM_SOCK#$SOCK#g" "$APP_DIR/nginx/psv-tracker.conf" \
        > /etc/nginx/sites-available/psv-tracker
    ln -sf /etc/nginx/sites-available/psv-tracker /etc/nginx/sites-enabled/psv-tracker
    rm -f /etc/nginx/sites-enabled/default
    chown root:www-data "$APP_DIR/api/config.php"
    chmod 640 "$APP_DIR/api/config.php"
    nginx -t
    systemctl reload nginx
}

setup_uploads() {
    # Ad images are stored here, OUTSIDE the repo, so they survive a re-clone.
    # nginx serves them via the /uploads/ alias; php-fpm (www-data) writes them.
    local UPLOAD_DIR="/var/lib/psv-tracker/uploads"
    log "Ensuring upload directory $UPLOAD_DIR..."
    mkdir -p "$UPLOAD_DIR"
    chown -R www-data:www-data "$UPLOAD_DIR"
    chmod 755 "$UPLOAD_DIR"
}

main() {
    check_root
    install_dependencies
    clone_or_update_repo
    setup_database
    run_migrations
    setup_uploads
    setup_nginx
    log "Done. Capture API is live on port 80."
    echo
    echo "  Test sign-on:"
    echo "    curl -X POST http://localhost/api/signon.php \\"
    echo "      -H 'Authorization: Bearer test-token-abc123' \\"
    echo "      -d '{\"vehicle_id\":1,\"route_id\":1}'"
    echo
}

main "$@"
