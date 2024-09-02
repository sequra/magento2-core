#!/bin/bash
if [ $PHP_XDEBUG_ENABLED == 'yes' ]; then
    echo "Installing xdebug"
    apt-get update
    apt-get install -y --no-install-recommends php-xdebug
    cat <<EOF> /opt/bitnami/php/etc/conf.d/xdebug.ini
zend_extension = xdebug.so

xdebug.mode = debug
xdebug.start_with_request = yes
xdebug.discover_client_host = 0
xdebug.client_port = 9003
xdebug.client_host=host.docker.internal
EOF

else
    echo "Skip xdebug installation"
fi