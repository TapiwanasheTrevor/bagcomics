#!/bin/bash

echo "🚀 Testing BAG Comics Docker Setup"
echo "=================================="

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker is not running. Please start Docker and try again."
    exit 1
fi

echo "✅ Docker is running"

# Build the Docker image
echo "🔨 Building Docker image..."
docker build -t bagcomics:test .

if [ $? -eq 0 ]; then
    echo "✅ Docker image built successfully"
else
    echo "❌ Docker build failed"
    exit 1
fi

# Run the container
echo "🚀 Starting container..."
docker run -d \
    --name bagcomics-test \
    -p 8080:80 \
    -e APP_NAME="BAG Comics" \
    -e APP_ENV=local \
    -e APP_DEBUG=true \
    -e APP_KEY=base64:$(openssl rand -base64 32) \
    -e APP_URL=http://localhost:8080 \
    -e DB_CONNECTION=sqlite \
    -e DB_DATABASE=/var/www/html/database/database.sqlite \
    bagcomics:test

if [ $? -eq 0 ]; then
    echo "✅ Container started successfully"
    echo ""
    echo "🌐 Your app should be available at: http://localhost:8080"
    echo "🔧 Admin panel: http://localhost:8080/admin"
    echo ""
    echo "📋 To view logs: docker logs bagcomics-test"
    echo "🛑 To stop: docker stop bagcomics-test"
    echo "🗑️  To remove: docker rm bagcomics-test"
    echo ""
    echo "⏳ Waiting for app to start (this may take a minute)..."
    sleep 30
    echo "🎉 App should be ready now!"
else
    echo "❌ Failed to start container"
    exit 1
fi
