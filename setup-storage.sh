#!/bin/bash

# Storage Setup Script for BagComics Production
echo "Setting up storage for BagComics..."

# Create necessary directories
mkdir -p storage/app/public/comics
mkdir -p storage/app/public/covers
mkdir -p storage/logs
mkdir -p public/storage

# Create storage symlink if it doesn't exist
if [ ! -L public/storage ]; then
    echo "Creating storage symlink..."
    php artisan storage:link
else
    echo "Storage symlink already exists"
fi

# Set proper permissions
chmod -R 755 storage/
chmod -R 755 public/storage/ 2>/dev/null || true

# Check if sample PDF exists and copy if needed
if [ ! -f "storage/app/public/comics/sample-comic.pdf" ] && [ -f "sample-comic.pdf" ]; then
    echo "Copying sample PDF to storage..."
    cp sample-comic.pdf storage/app/public/comics/
fi

# List contents for verification
echo "Storage directory contents:"
ls -la storage/app/public/
echo ""
echo "Comics directory contents:"
ls -la storage/app/public/comics/ 2>/dev/null || echo "Comics directory does not exist"

echo ""
echo "Public storage symlink:"
ls -la public/storage 2>/dev/null || echo "Public storage symlink does not exist"

echo "Storage setup completed!"