#!/bin/bash
# =====================================================================
# PRIMERA INSTALACIÓN DE LA BASE DENTRO DE DOCKER (contenedor "bd")
# La imagen de MariaDB corre este script UNA sola vez: cuando el volumen
# de datos está vacío (docker compose down -v lo vacía y se vuelve a
# instalar). Ejecuta los mismos scripts que database/instalar.php, en el
# mismo orden, sobre la base que ya creó la imagen (MARIADB_DATABASE).
#
# El 05 se adapta: en Docker la aplicación se conecta desde otro
# contenedor de la red, así que las cuentas son 'nl_app'@'%' (y no
# @'localhost'), y las claves son las del archivo .env, no las de
# desarrollo. La base no se publica fuera de la red nextleague_red.
#
# Todo va entre paréntesis para que funcione igual si la imagen lo
# ejecuta o lo incluye ("source") en su propio script.
# =====================================================================
(
set -eo pipefail

SQL=/nextleague/database

# Las claves van dentro de comillas en SQL: se aceptan solo caracteres
# que no necesitan escaparse. Y no se instala con las claves de ejemplo
# de .env.example, que son públicas.
for clave in "$MARIADB_ROOT_PASSWORD" "$NL_BD_CLAVE" "$NL_BD_CLAVE_LECTURA"; do
    if [[ ! "$clave" =~ ^[A-Za-z0-9_.@%+=:-]{12,}$ ]] || [[ "$clave" == cambiar_* ]]; then
        echo "NextLeague: las claves del archivo .env (NL_BD_CLAVE_ROOT, NL_BD_CLAVE y" >&2
        echo "  NL_BD_CLAVE_LECTURA) tienen que ser propias, de 12 caracteres o más:" >&2
        echo "  letras, números y _ . @ % + = : -" >&2
        echo "  Después de corregirlas: docker compose down -v && docker compose up -d" >&2
        exit 1
    fi
done

bd() {
    MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb --user=root --default-character-set=utf8mb4 "$MARIADB_DATABASE"
}

for archivo in 01_esquema.sql 02_datos_base.sql 03_vistas.sql 04_triggers.sql; do
    echo "NextLeague: $archivo"
    bd < "$SQL/$archivo"
done

echo "NextLeague: 05_usuarios_dcl.sql (cuentas para la red de Docker)"
sed -e "s|'localhost'|'%'|g" \
    -e "s|NlApp_Desarrollo_2026|$NL_BD_CLAVE|" \
    -e "s|NlLectura_Desarrollo_2026|$NL_BD_CLAVE_LECTURA|" \
    "$SQL/05_usuarios_dcl.sql" | bd

# Datos de prueba (cuando exista database/06_datos_prueba.sql)
if [ "${NL_DATOS_PRUEBA:-0}" = "1" ]; then
    if [ -f "$SQL/06_datos_prueba.sql" ]; then
        echo "NextLeague: 06_datos_prueba.sql"
        bd < "$SQL/06_datos_prueba.sql"
    else
        echo "NextLeague: NL_DATOS_PRUEBA=1, pero todavía no existe database/06_datos_prueba.sql"
    fi
fi

echo "NextLeague: base instalada."
)
