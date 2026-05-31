#!/usr/bin/env bash
set -euo pipefail
BACKUP_DIR="${1:-./backups}"
mkdir -p "$BACKUP_DIR"
FILE="$BACKUP_DIR/sgdm_$(date +%Y%m%d_%H%M%S).sql"
mysqldump -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "${DB_USER:-springstudent}" -p"${DB_PASSWORD:-springstudent}" "${DB_NAME:-sgdm}" > "$FILE"
echo "Backup creado: $FILE"
