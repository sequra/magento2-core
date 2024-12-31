#!/bin/bash
# XDebug configuration file.
CONFIG_FILE="/usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini"

if [[ -f "$CONFIG_FILE" ]]; then
    if grep -q "^;" "$CONFIG_FILE"; then
        echo "✅ Enabling XDebug"
        sed -i 's/^;//' "$CONFIG_FILE"
    else
        echo "✅ Disabling XDebug"
        sed -i 's/^/;/' "$CONFIG_FILE"
    fi
else
    echo "❌ The XDebug configuration file does not exist. Make sure the XDebug PHP extension is installed."
    exit 1
fi

echo "✅ Restarting Apache"
apachectl -k graceful