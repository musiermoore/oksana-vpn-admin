#!/bin/sh

set -e

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
PROJECT_ROOT="$(CDPATH= cd -- "$SCRIPT_DIR/../.." && pwd)"

# Configuration
DOCKERHUB_USERNAME="musiermoore"
IMAGE_NAME="oksana-vpn-app"
TAG="${1:-latest}"

FULL_IMAGE_NAME="$DOCKERHUB_USERNAME/$IMAGE_NAME:$TAG"

echo "Building $FULL_IMAGE_NAME..."
docker build \
    -f "$PROJECT_ROOT/docker/app/Dockerfile.base" \
    -t "$FULL_IMAGE_NAME" \
    "$PROJECT_ROOT"

echo "Logging in to Docker Hub..."
docker login

echo "Pushing $FULL_IMAGE_NAME..."
docker push "$FULL_IMAGE_NAME"

echo "Done!"
echo "Image pushed: $FULL_IMAGE_NAME"
