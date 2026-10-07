#!/bin/bash
# =====================================================================
# ARRANQUE DEL CONTENEDOR "web"
# Prepara el certificado HTTPS y después inicia Apache.
# Si en docker/ssl/ están certificado.crt y clave.key (el certificado
# real del servidor), se usan esos. Si no, se genera uno autofirmado para
# probar: el navegador avisa "no seguro" hasta que se ponga el real. Se
# guarda en esa misma carpeta, así no cambia en cada reinicio.
# =====================================================================
set -eo pipefail

SSL=/etc/apache2/ssl

if [ ! -f "$SSL/certificado.crt" ] || [ ! -f "$SSL/clave.key" ]; then
    # El nombre del sitio sale de NL_URL_PUBLICA (https://localhost:8443 → localhost)
    host=$(printf '%s' "${NL_URL_PUBLICA:-https://localhost}" | sed -E 's#^[a-z]+://##; s#[:/].*##')
    nombres="DNS:localhost,IP:127.0.0.1"
    if [[ "$host" =~ ^[0-9.]+$ ]]; then
        if [ "$host" != "127.0.0.1" ]; then nombres="IP:$host,$nombres"; fi
    elif [ "$host" != "localhost" ]; then
        nombres="DNS:$host,$nombres"
    fi
    echo "NextLeague: no hay certificado en docker/ssl/; se genera uno autofirmado para $host."
    # CA:FALSE: es el certificado de un sitio, no de una autoridad que firma
    # otros (openssl pone CA:TRUE por defecto y Apache lo advierte).
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout "$SSL/clave.key" -out "$SSL/certificado.crt" \
        -subj "/CN=$host" -addext "basicConstraints=critical,CA:FALSE" \
        -addext "subjectAltName=$nombres" 2>/dev/null
    chmod 600 "$SSL/clave.key"
fi

# Sigue el arranque normal de la imagen de PHP (Apache en primer plano).
exec docker-php-entrypoint "$@"
