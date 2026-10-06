#!/bin/sh
set -eu

: "${APP_SECRET:?Defina APP_SECRET no ambiente do Render}"
: "${DATABASE_URL:?Defina DATABASE_URL no ambiente do Render}"
: "${JWT_PASSPHRASE:?Defina JWT_PASSPHRASE no ambiente do Render}"
: "${OPENAI_API_KEY:?Defina OPENAI_API_KEY no ambiente do Render}"

: "${MYSQL_SSL_CA_BASE64:?Defina MYSQL_SSL_CA_BASE64 no ambiente do Render}"

MYSQL_SSL_CA=/var/www/html/config/ssl/aiven-ca.pem
export MYSQL_SSL_CA

install -d -m 755 -o www-data -g www-data "$(dirname "$MYSQL_SSL_CA")"
umask 077
printf '%s' "$MYSQL_SSL_CA_BASE64" | base64 -d > "$MYSQL_SSL_CA"
chown www-data:www-data "$MYSQL_SSL_CA"
chmod 644 "$MYSQL_SSL_CA"

if [ -n "${JWT_PRIVATE_KEY_BASE64:-}" ] || [ -n "${JWT_PUBLIC_KEY_BASE64:-}" ]; then
    : "${JWT_PRIVATE_KEY_BASE64:?Defina as duas chaves JWT em Base64}"
    : "${JWT_PUBLIC_KEY_BASE64:?Defina as duas chaves JWT em Base64}"

    mkdir -p "$(dirname "$JWT_SECRET_KEY")" "$(dirname "$JWT_PUBLIC_KEY")"
    umask 077
    printf '%s' "$JWT_PRIVATE_KEY_BASE64" | base64 -d > "$JWT_SECRET_KEY"
    printf '%s' "$JWT_PUBLIC_KEY_BASE64" | base64 -d > "$JWT_PUBLIC_KEY"
    chown www-data:www-data "$JWT_SECRET_KEY" "$JWT_PUBLIC_KEY"
    chmod 600 "$JWT_SECRET_KEY"
    chmod 644 "$JWT_PUBLIC_KEY"
fi

private_key_path=${JWT_SECRET_KEY#file://}
public_key_path=${JWT_PUBLIC_KEY#file://}

if [ ! -r "$private_key_path" ] || [ ! -r "$public_key_path" ]; then
    echo "Chaves JWT indisponiveis. Configure JWT_PRIVATE_KEY_BASE64/JWT_PUBLIC_KEY_BASE64 ou caminhos validos." >&2
    exit 1
fi

php bin/console cache:clear --env="$APP_ENV" --no-debug
chown -R www-data:www-data var

exec "$@"
