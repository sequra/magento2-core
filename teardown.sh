#!/bin/bash
GREEN=$(tput setaf 2)
NC=$(tput sgr0) # No color
set -o allexport
source .env
set +o allexport

# search_engine_profile="elasticsearch"
# if [ "$M2_SEARCH_ENGINE_HOST" == 'opensearch' ]; then
#     search_engine_profile="opensearch"
# fi

docker compose down --volumes --remove-orphans

if docker ps -a --format '{{.Names}}' | grep -q "^${NGROK_CONTAINER_NAME}$"; then
  docker rm -f $NGROK_CONTAINER_NAME > /dev/null 2>&1
  echo " ${GREEN}✔${NC} Ngrok container removed"
fi

if docker ps -a --format '{{.Names}}' | grep -q "^${CLOUDFLARED_CONTAINER_NAME}$"; then
  docker rm -f $CLOUDFLARED_CONTAINER_NAME > /dev/null 2>&1
  echo " ${GREEN}✔${NC} Cloudflared container removed"
fi