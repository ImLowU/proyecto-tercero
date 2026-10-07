#!/bin/bash
# =====================================================================
# INSTALACIÓN DE NEXTLEAGUE EN UN SERVIDOR ALMALINUX (producción, Docker)
#
#   git clone -b mejoras-letra <url-del-repositorio> nextleague
#   cd nextleague
#   sudo bash scripts/instalar_almalinux.sh
#
# Instala Docker si hace falta, crea la configuración (.env) con claves
# nuevas, abre los puertos del sitio en el firewall y levanta los
# contenedores. Al terminar, NextLeague queda en https://<servidor>.
#
# Se puede volver a ejecutar sin romper nada: no cambia el .env ni los
# datos. Sirve también para actualizar: git pull y volver a ejecutarlo.
#
# Opciones:
#   --sin-firewall   no toca firewalld
#   --datos-prueba   carga database/06_datos_prueba.sql al instalar la base
# =====================================================================
set -euo pipefail

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$RAIZ"

FIREWALL=1
DATOS_PRUEBA=0
for opcion in "$@"; do
    case "$opcion" in
        --sin-firewall) FIREWALL=0 ;;
        --datos-prueba) DATOS_PRUEBA=1 ;;
        -h|--ayuda) sed -n '3,18p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "Opción desconocida: $opcion (ver --ayuda)" >&2; exit 1 ;;
    esac
done

paso()  { echo; echo "==> $*"; }
error() { echo; echo "ERROR: $*" >&2; exit 1; }
valor() { grep -E "^$1=" .env | cut -d= -f2- || true; }   # valor de una variable del .env

# Las descargas pueden fallar por un corte de red: hasta 3 intentos.
reintentar() {
    local intento
    for intento in 1 2 3; do
        "$@" && return 0
        echo "   (falló el intento $intento de 3; se reintenta en 5 segundos)"
        sleep 5
    done
    return 1
}

# ---------------------------------------------------------------------
# 1. Comprobaciones
# ---------------------------------------------------------------------
paso "Comprobando el sistema"
[ "$(id -u)" -eq 0 ] || error "Hay que ejecutarlo como root: sudo bash scripts/instalar_almalinux.sh"
[ -f docker-compose.yml ] && [ -f .env.example ] || error "No encuentro docker-compose.yml en $RAIZ."
. /etc/os-release
case "${ID:-}" in
    almalinux|rocky|rhel|centos) ;;
    *) error "Este script es para AlmaLinux (este sistema es ${PRETTY_NAME:-desconocido})." ;;
esac
echo "Sistema:  ${PRETTY_NAME:-$ID}"
echo "Proyecto: $RAIZ"

# ---------------------------------------------------------------------
# 2. Docker (repositorio oficial de Docker para la familia de RHEL)
# ---------------------------------------------------------------------
if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
    paso "Docker ya está instalado ($(docker --version))"
else
    paso "Instalando Docker Engine y Docker Compose"
    # Las herramientas que usa este script, solo las que falten. AlmaLinux 9
    # trae curl-minimal: pedir el paquete "curl" chocaría con ese.
    faltan=(dnf-plugins-core)
    command -v git      >/dev/null 2>&1 || faltan+=(git)
    command -v openssl  >/dev/null 2>&1 || faltan+=(openssl)
    command -v curl     >/dev/null 2>&1 || faltan+=(curl)
    command -v ss       >/dev/null 2>&1 || faltan+=(iproute)
    command -v hostname >/dev/null 2>&1 || faltan+=(hostname)
    reintentar dnf -y install "${faltan[@]}" || error "dnf no pudo instalar: ${faltan[*]}"
    reintentar dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo \
        || error "No se pudo agregar el repositorio de Docker."
    # --allowerasing: si el sistema trae podman, su runc se reemplaza por el de Docker
    reintentar dnf -y install --allowerasing docker-ce docker-ce-cli containerd.io \
        docker-buildx-plugin docker-compose-plugin || error "dnf no pudo instalar Docker."
fi
systemctl enable --now docker >/dev/null
for _ in $(seq 1 30); do docker info >/dev/null 2>&1 && break; sleep 2; done
docker info >/dev/null 2>&1 || error "Docker no responde. Revisar con: systemctl status docker"

# ---------------------------------------------------------------------
# 3. Configuración (.env): claves nuevas, puertos 80/443 y producción
# ---------------------------------------------------------------------
if [ -f .env ]; then
    paso "Ya existe .env: se usa el que está (no se cambian claves ni puertos)"
    if [ "$DATOS_PRUEBA" -eq 1 ]; then
        sed -i 's/^NL_DATOS_PRUEBA=.*/NL_DATOS_PRUEBA=1/' .env
        echo "NL_DATOS_PRUEBA=1: se cargan solo si la base se instala de cero."
    fi
else
    paso "Creando .env con claves nuevas"
    sugerida=$(hostname -I 2>/dev/null | awk '{print $1}' || true)
    sugerida=${sugerida:-localhost}
    direccion=""
    if [ -t 0 ]; then
        read -r -p "Dirección del sitio, dominio o IP [$sugerida]: " direccion
    fi
    direccion=${direccion:-$sugerida}
    [[ "$direccion" =~ ^[A-Za-z0-9.-]+$ ]] || error "Dirección inválida: $direccion"

    clave() { openssl rand -hex 16; }
    sed -e "s|^NL_ENTORNO=.*|NL_ENTORNO=produccion|" \
        -e "s|^NL_PUERTO_HTTP=.*|NL_PUERTO_HTTP=80|" \
        -e "s|^NL_PUERTO_HTTPS=.*|NL_PUERTO_HTTPS=443|" \
        -e "s|^NL_URL_PUBLICA=.*|NL_URL_PUBLICA=https://$direccion|" \
        -e "s|^NL_BD_CLAVE_ROOT=.*|NL_BD_CLAVE_ROOT=$(clave)|" \
        -e "s|^NL_BD_CLAVE=.*|NL_BD_CLAVE=$(clave)|" \
        -e "s|^NL_BD_CLAVE_LECTURA=.*|NL_BD_CLAVE_LECTURA=$(clave)|" \
        -e "s|^NL_DATOS_PRUEBA=.*|NL_DATOS_PRUEBA=$DATOS_PRUEBA|" \
        .env.example > .env
    chmod 600 .env   # tiene las claves: solo root lo puede leer
    echo "Sitio: https://$direccion (las claves quedan en .env)"
fi
PUERTO_HTTP=$(valor NL_PUERTO_HTTP)
PUERTO_HTTPS=$(valor NL_PUERTO_HTTPS)

# Si NextLeague todavía no está corriendo, sus puertos tienen que estar libres
# (por ejemplo, que no haya un Apache o un nginx del sistema usándolos).
if [ -z "$(docker ps -q -f name='^nextleague_web$')" ]; then
    for puerto in "$PUERTO_HTTP" "$PUERTO_HTTPS"; do
        if ss -ltnH "sport = :$puerto" | grep -q .; then
            error "El puerto $puerto ya está en uso. Liberalo (ss -ltnp) o cambiá el puerto en .env."
        fi
    done
fi

# ---------------------------------------------------------------------
# 4. Firewall y SELinux
# ---------------------------------------------------------------------
if [ "$FIREWALL" -eq 1 ] && systemctl is-active --quiet firewalld; then
    paso "Abriendo en firewalld los puertos $PUERTO_HTTP y $PUERTO_HTTPS"
    firewall-cmd --permanent --add-port="$PUERTO_HTTP/tcp" --add-port="$PUERTO_HTTPS/tcp" >/dev/null
    firewall-cmd --reload >/dev/null
    echo "phpMyAdmin queda cerrado: solo se entra desde el propio servidor."
else
    paso "No se cambia el firewall (firewalld apagado o --sin-firewall)"
fi
if command -v getenforce >/dev/null 2>&1; then
    echo "SELinux: $(getenforce) (las carpetas que se montan llevan :z en docker-compose.yml)"
fi

# ---------------------------------------------------------------------
# 5. Contenedores
# ---------------------------------------------------------------------
paso "Armando la imagen de la aplicación"
reintentar docker compose build

paso "Levantando los contenedores (la primera vez se instala la base: unos minutos)"
if ! docker compose up -d; then
    # Si la base tarda más que la espera de docker-compose.yml, se espera un
    # poco más y se vuelve a intentar: lo que ya se instaló queda.
    echo "La base todavía se estaba preparando; se reintenta en 30 segundos..."
    sleep 30
    docker compose up -d || error "No se pudo levantar. Ver: docker compose logs bd"
fi
docker compose ps

paso "Probando que el sitio responda"
codigo=""
for _ in $(seq 1 30); do
    codigo=$(curl -sk -o /dev/null -w '%{http_code}' "https://localhost:$PUERTO_HTTPS/home/" || true)
    [ "$codigo" = "200" ] && break
    sleep 2
done
[ "$codigo" = "200" ] || error "El sitio no responde (código $codigo). Ver: docker compose logs web"
echo "El sitio responde."

# ---------------------------------------------------------------------
# 6. Resumen
# ---------------------------------------------------------------------
cat <<FIN

=====================================================================
 NextLeague está funcionando en: $(valor NL_URL_PUBLICA)
=====================================================================
 - Administrador inicial: admin@nextleague.com, con la contraseña de
   "Cuentas para probar" del README. Al entrar pide cambiarla: hacelo
   enseguida.
 - Certificado: hasta poner el real en docker/ssl/ (certificado.crt y
   clave.key) el navegador avisa "no seguro". Después de ponerlo:
     docker compose restart web
 - Las claves de la base están en .env (solo root puede leerlo).
 - phpMyAdmin, desde tu PC con un túnel SSH:
     ssh -L 8091:127.0.0.1:$(valor NL_PUERTO_PHPMYADMIN) usuario@servidor
   y abrir http://localhost:8091
 - Actualizar: git pull && sudo bash scripts/instalar_almalinux.sh
 - Estado: docker compose ps     Registros: docker compose logs -f web
=====================================================================
FIN
