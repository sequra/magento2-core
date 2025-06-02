#!/bin/bash

CONFIG_FILE="/usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini"

rm -f "$CONFIG_FILE"
touch "$CONFIG_FILE" && echo "zend_extension=xdebug" >> "$CONFIG_FILE"

MODE="debug"
# Parse arguments:
# --mode=debug|profile: Use debug or profile mode. Default is debug.
while [[ "$#" -gt 0 ]]; do
    if [[ "$1" == --mode=* ]]; then
        MODE="${1#*=}"
    fi
    shift
done

echo "xdebug.mode=$MODE" >> "$CONFIG_FILE"

if [[ "$MODE" == "off" ]]; then
    echo "🔴 Disabling XDebug"
else
    echo "🟢 Enabling XDebug in $MODE mode"
    echo "xdebug.start_with_request=yes" >> "$CONFIG_FILE"

    if [[ "$MODE" == "profile" ]]; then
        echo "xdebug.output_dir=/tmp/xdebug" >> "$CONFIG_FILE"
        echo "xdebug.profiler_output_name=cachegrind.out.%p.gz" >> "$CONFIG_FILE"
    elif [[ "$MODE" == "debug" ]]; then
        echo "xdebug.client_host=host.docker.internal" >> "$CONFIG_FILE"
        echo "xdebug.log_level=1" >> "$CONFIG_FILE"
    fi
fi

(apachectl -k graceful && echo "✅ Apache was restarted") || echo "❌ Failed to restart Apache"