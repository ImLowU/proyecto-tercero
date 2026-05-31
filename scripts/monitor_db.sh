#!/usr/bin/env bash
set -euo pipefail
mysql -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "${DB_USER:-springstudent}" -p"${DB_PASSWORD:-springstudent}" -e "SELECT COUNT(*) AS usuarios FROM sgdm.usuarios; SELECT COUNT(*) AS torneos FROM sgdm.torneos; SELECT COUNT(*) AS enfrentamientos FROM sgdm.enfrentamientos;"
