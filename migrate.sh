#!/bin/bash
# Sourced by install.sh and update.sh.
# Applies any schema/NNN_*.sql files not yet recorded, in filename order.

apply_migrations() {
    local DB_NAME="$1" DB_USER="$2" DB_PASS="$3" DIR="$4"

    mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e \
        "CREATE TABLE IF NOT EXISTS schema_migrations (
            filename   VARCHAR(255) PRIMARY KEY,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        );"

    local f base applied
    for f in "$DIR"/[0-9]*.sql; do
        [ -e "$f" ] || continue
        base="$(basename "$f")"
        applied="$(mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -N -B -e \
            "SELECT COUNT(*) FROM schema_migrations WHERE filename='$base';")"
        if [ "$applied" = "0" ]; then
            echo "  applying $base"
            mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$f"
            mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e \
                "INSERT INTO schema_migrations (filename) VALUES ('$base');"
        else
            echo "  skip $base (already applied)"
        fi
    done
}
