#!/bin/sh

set -e

# Configuration
DOCKERHUB_USERNAME="musiermoore"
IMAGE_NAME="oksana-vpn-app"
TAG="${1:-latest}"

FULL_IMAGE_NAME="$DOCKERHUB_USERNAME/$IMAGE_NAME:$TAG"

echo "Building $FULL_IMAGE_NAME..."
docker build \
    -f docker/app/Dockerfile.base \
    -t "$FULL_IMAGE_NAME" \
    .

echo "Logging in to Docker Hub..."
docker login

echo "Pushing $FULL_IMAGE_NAME..."
docker push "$FULL_IMAGE_NAME"

echo "Done!"
echo "Image pushed: $FULL_IMAGE_NAME"
