#!/usr/bin/env bash
#
# Nightly OyaFind backup: database dump plus the private storage tree.
#
# Installed by provision-oracle.sh and run from cron, but it works by hand too:
#   OYAFIND_DIR=/opt/oyafind ./backup.sh
#
# Backups are local to the VM. Copy them somewhere else if the data matters:
# a backup on the same disk as the database only protects against mistakes,
# not against losing the instance.
#
set -euo pipefail

APP_DIR="${OYAFIND_DIR:-/opt/oyafind}"
BACKUP_DIR="${OYAFIND_BACKUP_DIR:-/var/backups/oyafind}"
KEEP_DAYS="${OYAFIND_BACKUP_KEEP_DAYS:-14}"

cd "$APP_DIR/deploy"
mkdir -p "$BACKUP_DIR"

stamp="$(date -u +%Y%m%dT%H%M%SZ)"

echo "dumping database"
docker compose exec -T mysql sh -c \
    'exec mysqldump --single-transaction --quick --routines --events \
        -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
    | gzip -9 > "$BACKUP_DIR/db-$stamp.sql.gz"

echo "archiving application storage"
docker compose exec -T app tar -czf - -C /var/www/html/storage/app . \
    > "$BACKUP_DIR/storage-$stamp.tar.gz"

echo "pruning anything older than $KEEP_DAYS days"
find "$BACKUP_DIR" -type f -mtime "+$KEEP_DAYS" -delete

echo "done: $(du -sh "$BACKUP_DIR" | cut -f1) in $BACKUP_DIR"
