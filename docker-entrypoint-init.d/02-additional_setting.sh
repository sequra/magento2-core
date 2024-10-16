#!/bin/bash
cd /bitnami/magento/ || exit 0
bin/magento config:set general/country/default $MAGENTO_COUNTRY