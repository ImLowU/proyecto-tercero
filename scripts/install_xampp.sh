#!/usr/bin/env bash
set -euo pipefail
PROJECT_DIR="/opt/lampp/htdocs/sgdm"
DB_SQL="$PROJECT_DIR/database/schema.sql"

echo "Copiá el proyecto en $PROJECT_DIR antes de ejecutar este script."
if [ ! -f "$DB_SQL" ]; then
  echo "No se encontró $DB_SQL"
  exit 1
fi
sudo /opt/lampp/lampp start
sudo /opt/lampp/bin/mysql -u root < "$DB_SQL"
echo "Instalación lista. Abrí http://127.0.0.1/sgdm/public/login"
