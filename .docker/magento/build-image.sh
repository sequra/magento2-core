#!/bin/bash
# Build the Docker image
PUSH=0
M2_VERSION=""
PHP_VERSION=""
BASEDIR="$(dirname $(realpath $0))"
ENV_FILE="$BASEDIR/../../.env"

# Check if .env file exists
if [ ! -f "$ENV_FILE" ]; then
    echo "❌ .env file not found. Please create a .env file using .env.sample as a template and set the required environment variables"
    exit 1
fi

set -o allexport
source $ENV_FILE
set +o allexport

if [ -z "$M2_COMPOSER_REPO_KEY" ]; then
    echo "❌ Please set M2_COMPOSER_REPO_KEY with your Magento repo public key in your .env file"
    exit 1
fi

if [ -z "$M2_COMPOSER_REPO_SECRET" ]; then
    echo "❌ Please set M2_COMPOSER_REPO_SECRET with your Magento repo private key in your .env file"
    exit 1
fi

# Parse arguments:
# --push: Push the image to the registry
# --magento=VERSION: Supported versions are 2.4.3-p3, 2.4.4-p11, 2.4.5-p10, 2.4.6-p8, 2.4.7-p3, 2.4.7-p4
# --php=VERSION: Supported versions are 7.4, 8.1, 8.2
while [[ "$#" -gt 0 ]]; do
    if [ "$1" == "--push" ]; then
        PUSH=1
    elif [ "$1" == "--build" ]; then
        build=1
    elif [[ "$1" == --magento=* ]]; then
        M2_VERSION="${1#*=}"
    elif [[ "$1" == --php=* ]]; then
        PHP_VERSION="${1#*=}"
    fi
    shift
done

if [ -z "$M2_VERSION" ]; then
    echo "❌ Please set the Magento version using either --magento=VERSION or defining M2_VERSION in your .env file"
    exit 1
fi

if [ -z "$PHP_VERSION" ]; then
    echo "❌ Please set the PHP version using either --php=VERSION or defining PHP_VERSION in your .env file"
    exit 1
fi

# Check if the PHP version is supported
if [ "$PHP_VERSION" != "7.4" ] && [ "$PHP_VERSION" != "8.1" ] && [ "$PHP_VERSION" != "8.2" ]; then
    echo "❌ PHP version $PHP_VERSION is not supported. Supported versions are 7.4, 8.1, 8.2"
    exit 1
fi

# Check if the Magento version is supported
if [ "$M2_VERSION" != "2.4.3-p3" ] && [ "$M2_VERSION" != "2.4.4-p11" ] && [ "$M2_VERSION" != "2.4.5-p10" ] && [ "$M2_VERSION" != "2.4.6-p8" ] && [ "$M2_VERSION" != "2.4.7-p3" ] && [ "$M2_VERSION" != "2.4.7-p4" ]; then
    echo "❌ Magento version $M2_VERSION is not supported. Supported versions are 2.4.3-p3, 2.4.4-p11, 2.4.5-p10, 2.4.6-p8, 2.4.7-p3, 2.4.7-p4"
    exit 1
fi

# Check if the PHP version is compatible with the Magento version
if [ "$M2_VERSION" == "2.4.3-p3" ] && [ "$PHP_VERSION" != "7.4" ]; then
    echo "❌ Magento version $M2_VERSION is not compatible with PHP version $PHP_VERSION. Please use PHP version 7.4"
    exit 1
fi

if [ "$M2_VERSION" == "2.4.4-p11" ] && [ "$PHP_VERSION" != "8.1" ]; then
    echo "❌ Magento version $M2_VERSION is not compatible with PHP version $PHP_VERSION. Please use PHP version 8.1"
    exit 1
fi

if [ "$M2_VERSION" == "2.4.5-p10" ] && [ "$PHP_VERSION" != "8.1" ]; then
    echo "❌ Magento version $M2_VERSION is not compatible with PHP version $PHP_VERSION. Please use PHP version 8.1"
    exit 1
fi

if [ "$M2_VERSION" == "2.4.6-p8" ] && [ "$PHP_VERSION" != "8.2" ]; then
    echo "❌ Magento version $M2_VERSION is not compatible with PHP version $PHP_VERSION. Please use PHP version 8.2"
    exit 1
fi

if [ "$M2_VERSION" == "2.4.7-p3" ] && [ "$PHP_VERSION" != "8.2" ]; then
    echo "❌ Magento version $M2_VERSION is not compatible with PHP version $PHP_VERSION. Please use PHP version 8.2"
    exit 1
fi

if [ "$M2_VERSION" == "2.4.7-p4" ] && [ "$PHP_VERSION" != "8.2" ]; then
    echo "❌ Magento version $M2_VERSION is not compatible with PHP version $PHP_VERSION. Please use PHP version 8.2"
    exit 1
fi

build_args="--secret id=M2_COMPOSER_REPO_KEY --secret id=M2_COMPOSER_REPO_SECRET"
build_args+=" --build-arg PHP_VERSION=$PHP_VERSION"
build_args+=" --build-arg M2_VERSION=$M2_VERSION"

# Build the Docker image
echo "Building Docker image for Magento $M2_VERSION with PHP $PHP_VERSION..."

if [ $PUSH -eq 1 ]; then
    echo "Login to the GitHub Container Registry..."
    if [ -z "$GITHUB_TOKEN" ]; then
        echo "❌ Please, set an environment variable named GITHUB_TOKEN with your GitHub token. You can define it in your .env file"
        exit 1
    fi

    echo $GITHUB_TOKEN | docker login ghcr.io -u sequra --password-stdin || (echo "❌ Login failed" && exit 1)
    echo "The resulting image will be pushed to the GitHub Container Registry"
    build_args+=" --platform linux/amd64,linux/arm64 --push"
else
    build_args+=" --load"
fi

build_args+=" --tag ghcr.io/sequra/magento2-core:$M2_VERSION-$PHP_VERSION"

DOCKERFILE="Dockerfile.php8"
if [ "$PHP_VERSION" == "7.4" ]; then
    DOCKERFILE="Dockerfile.php74"
fi

build_args+=" -f $BASEDIR/$DOCKERFILE $BASEDIR"

BUILDER_NAME="sequra-builder"
EXISTING_BUILDER=$(docker buildx ls --format '{{.Name}}' | grep -w "$BUILDER_NAME")

export DOCKER_BUILDKIT=1
if [ -z "$EXISTING_BUILDER" ]; then
  docker buildx create --name "$BUILDER_NAME" --use || (echo "❌ Builder creation failed" && exit 1)
  docker buildx inspect "$BUILDER_NAME" --bootstrap || (echo "❌ Builder bootstrap failed" && exit 1)
else
  # Check if the builder is already in use
  ACTIVE_BUILDER=$(docker buildx ls | grep -w "$BUILDER_NAME" | awk '/\*/ {print $1}')

  if [ "$ACTIVE_BUILDER" != "*" ]; then
    # Use the builder if it's not the active one
    docker buildx use "$BUILDER_NAME" || (echo "❌ Builder use failed" && exit 1)
  fi
fi

docker buildx build $build_args