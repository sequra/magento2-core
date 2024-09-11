#!/bin/bash
DIR="$( cd "$( dirname "$0" )" && pwd )" # Get the current directory
# Colors for the output
GREEN=$(tput setaf 2)
RED=$(tput setaf 1)
YELLOW=$(tput setaf 3)
WHITE=$(tput setaf 7)
NC=$(tput sgr0) # No color
unameOut="$(uname -s)"
case "${unameOut}" in
    Linux*)     open_cmd=xdg-open;;
    Darwin*)    open_cmd=open;;
    *)          open_cmd=start
esac

set -o allexport
# shellcheck source=.env.sample
source .env.sample
if [ -f .env ]; then
    source .env
else
    echo "⚠️ No .env file found, coping from .env.sample"
    cp .env.sample .env
fi
set +o allexport

docker compose up -d --build || { echo "❌ Failed to start docker compose" ; exit 1; }

echo "🚀 Waiting for installation to complete..."

retry=600
timeout=10
start=$(date +%s)
while [ $(($(date +%s) - $start)) -lt $retry ]; do
    # Check if Magento is up and running against exposed http port just in case varnish or anything else is set in front.
    response_code="$(curl -s -o /dev/null -w ''%{http_code}'' "http://localhost:${MAGENTO_HTTP_PORT}")"
    if [[ $response_code == "000" ]] ; then
        echo -ne "⏳ Waiting for Magento to be up and running... $(($(date +%s) - $start)) / $retry "\\r
        sleep $timeout
        docker compose ps --services magento > /dev/null || { echo "❌ Magento container failed" ; exit 1; } 
        continue
    fi
    if [[ $response_code == "500" ]] ; then
        echo "❌ Something went wrong and Magento returned a 500 error"
        exit 1;
    fi

    echo $GREEN
    echo " ✅ Magento installed"
    echo " Magento is up and running at http://${MAGENTO_HOST}"
    if [[ $MAGENTO_SAMPLEDATA == "true" || $MAGENTO_SAMPLEDATA == "yes" ]] ; then
        $DIR/bin/install-sampledata || { echo "❌ Failed to install sample-data" ; exit 1; }
    fi
    echo "🚀 Openning the browser..."
    $open_cmd "http://${MAGENTO_HOST}"
    echo $NC
    exit 0;
done
echo $RED
echo "❌ Timeout after $retry seconds"
echo $NC
exit 1