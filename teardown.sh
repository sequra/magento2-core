#!/bin/bash
set -o allexport
source .env
set +o allexport

# search_engine_profile="elasticsearch"
# if [ "$M2_SEARCH_ENGINE_HOST" == 'opensearch' ]; then
#     search_engine_profile="opensearch"
# fi

docker compose down --volumes --remove-orphans
docker rm -f $NGROK_CONTAINER_NAME || true