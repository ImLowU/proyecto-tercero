#!/bin/bash
# ============================================================
# server_management.sh — Gestión del servidor SportTime
# ============================================================

set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "$0")/.." && pwd)"

usage() {
  echo "Uso: $0 {start|stop|restart|status|logs|backup|shell-app|shell-db}"
  exit 1
}

case "${1:-}" in
  start)
    echo "Levantando contenedores SportTime..."
    cd "$SCRIPT_DIR" && docker compose up -d
    echo "Aplicación disponible en http://localhost:8082"
    echo "phpMyAdmin disponible en http://localhost:8083"
    ;;
  stop)
    echo "Deteniendo contenedores..."
    cd "$SCRIPT_DIR" && docker compose down
    ;;
  restart)
    echo "Reiniciando contenedores..."
    cd "$SCRIPT_DIR" && docker compose restart
    ;;
  status)
    cd "$SCRIPT_DIR" && docker compose ps
    ;;
  logs)
    cd "$SCRIPT_DIR" && docker compose logs -f --tail=50
    ;;
  backup)
    bash "$SCRIPT_DIR/scripts/backup.sh"
    ;;
  shell-app)
    docker exec -it sporttime_app /bin/bash
    ;;
  shell-db)
    docker exec -it sporttime_db mysql -u sporttime_user -p sporttime
    ;;
  *)
    usage
    ;;
esac
