#!/bin/bash
set -o allexport
source .env
set +o allexport

docker compose down --volumes --remove-orphans
docker rm -f $NGROK_CONTAINER_NAME || true