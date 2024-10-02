#!/bin/bash
source /opt/bitnami/scripts/apache/status.sh
if is_apache_running; then
    if [ -e /opt/bitnami/php/etc/conf.d/xdebug.ini ]; then
        echo "Disabling xdebug"
        rm -f /opt/bitnami/php/etc/conf.d/xdebug.ini
    else
        echo "Enabling xdebug"
        cat <<EOF> /opt/bitnami/php/etc/conf.d/xdebug.ini
zend_extension = xdebug.so

xdebug.mode = debug
xdebug.start_with_request = yes
xdebug.discover_client_host = 0
xdebug.client_port = 9003
xdebug.client_host=host.docker.internal
EOF
    fi
    /opt/bitnami/scripts/php/reload.sh
fi
