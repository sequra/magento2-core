#!/bin/bash
cd /bitnami/magento/ || exit 0
bin/magento deploy:mode:set developer