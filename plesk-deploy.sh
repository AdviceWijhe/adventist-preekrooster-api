#!/bin/sh
# Draait op de API-server na een Plesk Git-deploy. Eén regel in post-deployment:
# sh plesk-deploy.sh
set -eu
cd "$(dirname "$0")"

if command -v php >/dev/null 2>&1; then
    PHP=$(command -v php)
elif [ -x /opt/plesk/php/8.3/bin/php ]; then
    PHP=/opt/plesk/php/8.3/bin/php
elif [ -x /opt/plesk/php/8.2/bin/php ]; then
    PHP=/opt/plesk/php/8.2/bin/php
else
    echo "php not found" >&2
    exit 1
fi

PHAR="${HOME}/.config/preekrooster-composer.phar"
if [ ! -f "$PHAR" ]; then
    mkdir -p "$(dirname "$PHAR")"
    if command -v curl >/dev/null 2>&1; then
        curl -fsSL https://getcomposer.org/download/latest-stable/composer.phar -o "$PHAR"
    elif command -v wget >/dev/null 2>&1; then
        wget -qO "$PHAR" https://getcomposer.org/download/latest-stable/composer.phar
    else
        echo "curl or wget required to install composer" >&2
        exit 1
    fi
fi

"$PHP" "$PHAR" install --no-dev --optimize-autoloader --no-interaction

"$PHP" artisan down --retry=15 || true
"$PHP" artisan migrate --force
# Symlink is optional. If symlink() is disabled, /storage still serves the public disk.
if ! "$PHP" artisan storage:link; then
    echo "storage:link failed; profile photos stay available via the /storage route" >&2
fi
"$PHP" artisan config:cache
"$PHP" artisan view:cache
"$PHP" artisan up
