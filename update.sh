#!/bin/bash
#
# PSV Tracker - update
#
# Run on the server after new code is pushed:
#   sudo /opt/psv-tracker/update.sh
#
set -euo pipefail

APP_DIR="/opt/psv-tracker"
log() { echo -e "\n[psv-tracker] $*"; }

if [ "$(id -u)" != "0" ]; then
    echo "This script must be run as root (use sudo)." >&2
    exit 1
fi

log "Pulling latest code..."
git config --global --add safe.directory "$APP_DIR" 2>/dev/null || true
git -C "$APP_DIR" pull --ff-only

CONFIG="$APP_DIR/api/config.php"
if [ ! -f "$CONFIG" ]; then
    echo "Missing $CONFIG - run install.sh first." >&2
    exit 1
fi
DB_NAME="$(php -r "\$c=require '$CONFIG'; echo \$c['db_name'];")"
DB_USER="$(php -r "\$c=require '$CONFIG'; echo \$c['db_user'];")"
DB_PASS="$(php -r "\$c=require '$CONFIG'; echo \$c['db_pass'];")"

log "Applying any new migrations..."
# shellcheck source=/dev/null
source "$APP_DIR/schema/migrate.sh"
apply_migrations "$DB_NAME" "$DB_USER" "$DB_PASS" "$APP_DIR/schema"

log "Reloading services..."
systemctl reload nginx || true

log "Update complete."
