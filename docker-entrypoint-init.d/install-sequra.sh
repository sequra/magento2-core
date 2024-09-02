#!/bin/bash
echo "Mount Sequra Core module"
chown -R daemon /Sequra
cd /bitnami/magento/ || exit 0
if [ "$SQ_M2_CORE_VERSION" = "local" ]; then
    php ./vendor/bin/composer config repositories.sequra/magento2-core path /Sequra/Core
    COMPOSER_MIRROR_PATH_REPOS=1 php ./vendor/bin/composer require sequra/magento2-core:^2.5
    # mkdir -p ./app/code
    # ln -s /Sequra ./app/code/Sequra
    # composer update
else
    composer require sequra/magento2-core:"$SQ_M2_CORE_VERSION"
fi
bin/magento config:set dev/template/allow_symlink 1
# bin/magento config:set general/locale/code "$MAGENTO_LOCALE" 
# bin/magento config:set general/locale/timezone "$MAGENTO_TIMEZONE"
# bin/magento config:set general/country/default "$MAGENTO_COUNTRY"
# bin/magento config:set currency/options/base "$MAGENTO_CURRENCY"
# bin/magento config:set currency/options/default "$MAGENTO_CURRENCY"
bin/magento module:enable Sequra_Core
bin/magento deploy:mode:set developer
bin/magento setup:upgrade
bin/magento sequra:configure --merchant_ref="$SQ_MERCHANT_REF" --username="$SQ_USER_NAME" --password="$SQ_USER_SECRET" --assets_key="$SQ_ASSETS_KEY" --endpoint="$SQ_ENDPOINT"

chown -R daemon ./*
