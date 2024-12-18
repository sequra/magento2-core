#!/bin/bash

# check if MAGENTO_ENV_SESSION is set and it is different from the default value
if [ -z "$MAGENTO_ENV_SESSION" ] || [ "$MAGENTO_ENV_SESSION" = "['save' => 'files']" ]; then
    echo "MAGENTO_ENV_SESSION is not set or is set use the default value, skipping..."
    exit 0
fi

cd /bitnami/magento/app/etc || exit 0

file="env.php"
sed -i.bak "/'session' => \[/,/\],/c\
    'session' => $MAGENTO_ENV_SESSION \
    ," "$file"

echo "Updated /bitnami/magento/app/etc/$file with MAGENTO_ENV_SESSION. Backup saved as /bitnami/magento/app/etc/$file.bak"