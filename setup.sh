#!/bin/bash
unameOut="$(uname -s)"
case "${unameOut}" in
    Linux*)     open_cmd=xdg-open;;
    Darwin*)    open_cmd=open;;
    *)          open_cmd=start
esac

if [ ! -f .env ]; then
    cp .env.sample .env
fi

ngrok=0
cloudflared=0
open_browser=0
install=0
BASEDIR="$(dirname $(realpath $0))"

# Parse arguments:
# --ngrok-token=YOUR_NGROK_TOKEN: Override the ngrok token in .env
# --ngrok: Use ngrok to expose the site
# --cloudflared-token=YOUR_CLOUDFLARED_TOKEN: Override the cloudflared token in .env
# --cloudflared: Use cloudflared to expose the site
# --open-browser: Open the browser after the installation is complete
# --install: Run the installation process before starting the containers
while [[ "$#" -gt 0 ]]; do
    if [ "$1" == "--ngrok" ]; then
        ngrok=1
    elif [[ "$1" == --ngrok-token=* ]]; then
        ngrok_token="${1#*=}"
        sed -i.bak "s|NGROK_AUTHTOKEN=.*|NGROK_AUTHTOKEN=$ngrok_token|" .env
        rm .env.bak
    elif [ "$1" == "--cloudflared" ]; then
        cloudflared=1
    elif [[ "$1" == --cloudflared-token=* ]]; then
        cloudflared_token="${1#*=}"
        sed -i.bak "s|CLOUDFLARED_TUNNEL_TOKEN=.*|CLOUDFLARED_TUNNEL_TOKEN=$cloudflared_token|" .env
        rm .env.bak
    elif [ "$1" == "--open-browser" ]; then
        open_browser=1
    elif [ "$1" == "--install" ]; then
        install=1
    fi
    shift
done

# Reset PUBLIC_URL inside .env
sed -i.bak "s|PUBLIC_URL=.*|PUBLIC_URL=|" .env
rm .env.bak

set -o allexport
source .env
set +o allexport

if [ -z "$M2_COMPOSER_REPO_KEY" ]; then
    echo "❌ Please set M2_COMPOSER_REPO_KEY with your Magento repo public key in your .env file"
    exit 1
fi

if [ -z "$M2_COMPOSER_REPO_SECRET" ]; then
    echo "❌ Please set M2_COMPOSER_REPO_SECRET with your Magento repo private key in your .env file"
    exit 1
fi

if [ $install -eq 1 ]; then
    echo "🚀 Running installation process..."
    "$BASEDIR/bin/update-integration-core-ui" || {
        echo "❌ Failed to update integration core UI. Please check the output for errors."
        exit 1
    }
fi

if [ $ngrok -eq 1 ]; then

    if [ -z "$NGROK_AUTHTOKEN" ]; then
        echo "❌ Please set NGROK_AUTHTOKEN with your ngrok auth token in your .env file (get it from https://dashboard.ngrok.com/)"
        exit 1
    fi
    
    echo "🚀 Starting ngrok..."

    docker run -d -e NGROK_AUTHTOKEN=$NGROK_AUTHTOKEN \
        -p $NGROK_PORT:4040 \
        --name $NGROK_CONTAINER_NAME \
        --add-host=host:host-gateway \
        ngrok/ngrok:alpine \
        http host:$M2_HTTP_PORT
    
    M2_URL=""
    retry=10
    timeout=1
    start=$(date +%s)
    while [ -z "$M2_URL" ]; do
        sleep $timeout
        M2_URL=$(curl -s http://localhost:$NGROK_PORT/api/tunnels | grep -o '"public_url":"[^"]*"' | sed 's/"public_url":"\(.*\)"/\1/' | head -n 1)
        if [ $(($(date +%s) - $start)) -gt $retry ]; then
            docker rm -f $NGROK_CONTAINER_NAME || true
            echo "❌ Error getting public url from ngrok after ${retry} seconds"
            exit 1
        fi
    done

    # Overwrite PUBLIC_URL inside .env
    sed -i.bak "s|PUBLIC_URL=.*|PUBLIC_URL=$M2_URL|" .env
    rm .env.bak

    echo "✅ Ngrok started. Public URL: $M2_URL"
elif [ $cloudflared -eq 1 ]; then

    if [ -z "$CLOUDFLARED_TUNNEL_TOKEN" ]; then
        echo "❌ Please set CLOUDFLARED_TUNNEL_TOKEN with your cloudflared tunnel token in your .env file (get it from https://developers.cloudflare.com/cloudflare-one/networks/connectors/cloudflare-tunnel/get-started/create-remote-tunnel/)"
        exit 1
    fi

    if [ -z "$CLOUDFLARED_TUNNEL_URL" ]; then
        echo "❌ Please set CLOUDFLARED_TUNNEL_URL with your cloudflared tunnel URL in your .env file"
        exit 1
    fi

    echo "🚀 Starting cloudflared tunnel..."

    if [ -z "$CLOUDFLARED_CONTAINER_NAME" ]; then
        CLOUDFLARED_CONTAINER_NAME=magento-cloudflared
    fi

    docker run -d \
        --name $CLOUDFLARED_CONTAINER_NAME \
        --add-host=host:host-gateway \
        cloudflare/cloudflared:latest \
        tunnel --no-autoupdate run --token $CLOUDFLARED_TUNNEL_TOKEN

    # Overwrite PUBLIC_URL inside .env
    M2_URL=$CLOUDFLARED_TUNNEL_URL
    sed -i.bak "s|PUBLIC_URL=.*|PUBLIC_URL=$M2_URL|" .env
    rm .env.bak

    echo "✅ Cloudflared started. Public URL: $M2_URL"
fi

# search_engine_profile="elasticsearch"
# if [ "$M2_SEARCH_ENGINE_HOST" == 'opensearch' ]; then
#     search_engine_profile="opensearch"
# fi

docker compose up -d || exit 1

echo "🚀 Waiting for installation to complete..."

retry=300 # 5 minutes
timeout=1
start=$(date +%s)
while [ $(($(date +%s) - $start)) -lt $retry ]; do
    seconds=$(($(date +%s) - $start))
    if docker compose exec magento ls /var/www/html/.post-install-complete > /dev/null 2>&1; then
        echo "✅ Done in ${seconds} seconds."
        echo "🔗 Browse products at ${M2_URL}"
        echo "🔗 Access Admin at ${M2_URL}/admin"
        echo "User: $M2_ADMIN_USER"
        echo "Password: $M2_ADMIN_PASSWORD"

        if [ $open_browser -eq 1 ]; then
            echo "🚀 Opening the browser..."
            $open_cmd $M2_URL
        fi

        exit 0
    elif docker compose exec magento ls /var/www/html/.post-install-failed > /dev/null 2>&1; then
        echo "❌ Installation failed after ${seconds} seconds."
        docker compose logs --tail=100 magento
        exit 1
    elif ! docker compose ps --format "{{.Service}} {{.State}}" | grep -q "^magento running" && [ "$seconds" -gt 10 ]; then
        echo "❌ Magento container is not running after ${seconds} seconds."
        docker compose logs --tail=100 magento
        exit 1
    fi
    sleep $timeout
done
echo "❌ Timeout after ${retry} seconds"
exit 1