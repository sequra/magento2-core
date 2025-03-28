#!/bin/bash

# Check if need to install WordPress
if [ ! -f /var/www/html/.post-install-complete ]; then

    function handle_failure {
        touch /var/www/html/.post-install-failed
        echo "❌ Magento 2 installation failed"
        exit 1
    }

    rm -f /var/www/html/.post-install-failed
    
    cd /var/www/html
    export XDEBUG_MODE=off

    # Override WP_URL if PUBLIC_URL is set
    if [ -n "$PUBLIC_URL" ]; then
        M2_URL="$PUBLIC_URL"
    fi

    session_save="--session-save=$M2_SESSION_SAVE"
    if [ "$M2_SESSION_SAVE" == 'redis' ]; then
        session_save="$session_save\
        --session-save-redis-host=$M2_SESSION_SAVE_REDIS_HOST \
        --session-save-redis-port=$M2_SESSION_SAVE_REDIS_PORT \
        --session-save-redis-password=$M2_SESSION_SAVE_REDIS_PASSWORD \
        --session-save-redis-timeout=$M2_SESSION_SAVE_REDIS_TIMEOUT \
        --session-save-redis-persistent-id=$M2_SESSION_SAVE_REDIS_PERSISTENT_IDENTIFIER \
        --session-save-redis-db=$M2_SESSION_SAVE_REDIS_DB \
        --session-save-redis-compression-threshold=$M2_SESSION_SAVE_REDIS_COMPRESSION_THRESHOLD \
        --session-save-redis-compression-lib=$M2_SESSION_SAVE_REDIS_COMPRESSION_LIB \
        --session-save-redis-log-level=$M2_SESSION_SAVE_REDIS_LOG_LEVEL \
        --session-save-redis-max-concurrency=$M2_SESSION_SAVE_REDIS_MAX_CONCURRENCY \
        --session-save-redis-break-after-frontend=$M2_SESSION_SAVE_REDIS_BREAK_AFTER_FRONTEND \
        --session-save-redis-break-after-adminhtml=$M2_SESSION_SAVE_REDIS_BREAK_AFTER_ADMINHTML \
        --session-save-redis-first-lifetime=$M2_SESSION_SAVE_REDIS_FIRST_LIFETIME \
        --session-save-redis-bot-first-lifetime=$M2_SESSION_SAVE_REDIS_BOT_FIRST_LIFETIME \
        --session-save-redis-bot-lifetime=$M2_SESSION_SAVE_REDIS_BOT_LIFETIME \
        --session-save-redis-disable-locking=$M2_SESSION_SAVE_REDIS_DISABLE_LOCKING \
        --session-save-redis-min-lifetime=$M2_SESSION_SAVE_REDIS_MIN_LIFETIME \
        --session-save-redis-max-lifetime=$M2_SESSION_SAVE_REDIS_MAX_LIFETIME \
        --session-save-redis-sentinel-master=$M2_SESSION_SAVE_REDIS_SENTINEL_MASTER \
        --session-save-redis-sentinel-servers=$M2_SESSION_SAVE_REDIS_SENTINEL_SERVERS \
        --session-save-redis-sentinel-verify-master=$M2_SESSION_SAVE_REDIS_SENTINEL_VERIFY_MASTER \
        --session-save-redis-sentinel-connect-retries=$M2_SESSION_SAVE_REDIS_SENTINEL_CONNECT_RETRIES"
    fi

    disable_modules=""
    if [ -n "$M2_DISABLE_MODULES" ]; then
        disable_modules="--disable-modules=$M2_DISABLE_MODULES"
    fi

    search_engine_arg_prefix="--elasticsearch-"
    if [ "$M2_SEARCH_ENGINE" == 'opensearch' ]; then
        search_engine_arg_prefix="--opensearch-"
    fi
    search_engine="--search-engine=$M2_SEARCH_ENGINE \
    ${search_engine_arg_prefix}host=$M2_SEARCH_ENGINE_HOST \
    ${search_engine_arg_prefix}port=$M2_SEARCH_ENGINE_PORT \
    ${search_engine_arg_prefix}enable-auth=0 \
    ${search_engine_arg_prefix}index-prefix=$M2_SEARCH_ENGINE_INDEX_PREFIX \
    ${search_engine_arg_prefix}timeout=$M2_SEARCH_ENGINE_TIMEOUT"

    # Install Magento 2 
    su -s /bin/bash www-data -c "bin/magento setup:install \
    --base-url=$M2_URL \
    --db-host=$M2_DB_HOST \
    --db-name=$M2_DB_NAME \
    --db-user=$M2_DB_USER \
    --db-password=$M2_DB_PASSWORD \
    --skip-db-validation \
    --backend-frontname=$M2_BACKEND_FRONTNAME \
    --admin-firstname=$M2_ADMIN_FIRSTNAME \
    --admin-lastname=$M2_ADMIN_LASTNAME \
    --admin-email=$M2_ADMIN_EMAIL \
    --admin-user=$M2_ADMIN_USER \
    --admin-password=$M2_ADMIN_PASSWORD \
    --language=$M2_LANGUAGE \
    --currency=$M2_CURRENCY \
    --timezone=$M2_TIMEZONE \
    --use-rewrites=1 \
    --search-engine=elasticsearch7 \
    --elasticsearch-host=$M2_ELASTICSEARCH_HOST \
    --elasticsearch-port=$M2_ELASTICSEARCH_PORT \
    --elasticsearch-enable-auth=0 \
    --elasticsearch-index-prefix=$M2_ELASTICSEARCH_INDEX_PREFIX \
    --elasticsearch-timeout=$M2_ELASTICSEARCH_TIMEOUT \
    $session_save $search_engine $disable_modules" \
    && su -s /bin/bash www-data -c "composer config http-basic.repo.magento.com $M2_COMPOSER_REPO_KEY $M2_COMPOSER_REPO_SECRET" \
    && su -s /bin/bash www-data -c "bin/magento deploy:mode:set developer" \
    || handle_failure

    if [ -n "$M2_SAMPLE_DATA" ]; then
        su -s /bin/bash www-data -c "bin/magento sampledata:deploy && bin/magento setup:upgrade && bin/magento cache:flush" || handle_failure
    fi
    
    # Set auto increment to current timestamp for Order Sequence tables
    echo "ALTER TABLE sequence_order_0 AUTO_INCREMENT = $(date +%s);ALTER TABLE sequence_order_1 AUTO_INCREMENT = $(date +%s);" \
    | mysql -h $M2_DB_HOST -P 3306 -u $M2_DB_USER -p$M2_DB_PASSWORD $M2_DB_NAME || handle_failure

    # Install seQura plugin
    su -s /bin/bash www-data -c "composer config repositories.sequra/magento2-core path /Sequra/Core" \
    && su -s /bin/bash www-data -c "COMPOSER_MIRROR_PATH_REPOS=1 composer require sequra/magento2-core:^2.5" \
    && su -s /bin/bash www-data -c "bin/magento config:set dev/template/allow_symlink $M2_ALLOW_SYMLINK" \
    && su -s /bin/bash www-data -c "bin/magento module:enable Sequra_Core" \
    && su -s /bin/bash www-data -c "bin/magento setup:upgrade" || handle_failure

    if [ -n "$M2_SAMPLE_DATA" ]; then
        su -s /bin/bash www-data -c "bin/magento sequra-helper:setup" || handle_failure
    else
        echo "⚠️ Setup skipped. Please set M2_SAMPLE_DATA in your .env file."
    fi

    if [ -n "$SQ_MERCHANT_REF" ] || [ -n "$SQ_USER_NAME" ] || [ -n "$SQ_USER_SECRET" ] || [ -n "$SQ_ASSETS_KEY" ] || [ -n "$SQ_ENDPOINT" ]; then
        su -s /bin/bash www-data -c "bin/magento sequra:configure --merchant_ref="$SQ_MERCHANT_REF" --username="$SQ_USER_NAME" --password="$SQ_USER_SECRET" --assets_key="$SQ_ASSETS_KEY" --endpoint="$SQ_ENDPOINT"" || \
        echo "⚠️ seQura plugin not configured. An error occurred, please check your credentials and try again."
    else
        echo "⚠️ seQura plugin not configured. Please set SQ_MERCHANT_REF, SQ_USER_NAME, SQ_USER_SECRET, SQ_ASSETS_KEY and SQ_ENDPOINT in your .env file."
    fi

    touch /var/www/html/.post-install-complete && echo "✅ Magento 2 installed and configured."
fi