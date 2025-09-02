#!/bin/bash

# EmailListVerify PHP SDK Test Runner Script

echo "================================"
echo "EmailListVerify SDK Test Runner"
echo "================================"
echo ""

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo "Error: PHP is not installed or not in PATH"
    echo "Please install PHP 7.2 or higher"
    exit 1
fi

# Check if Composer is installed
if ! command -v composer &> /dev/null; then
    echo "Error: Composer is not installed or not in PATH"
    echo "Please install Composer from https://getcomposer.org/"
    exit 1
fi

# Install dependencies if vendor directory doesn't exist
if [ ! -d "vendor" ]; then
    echo "Installing dependencies..."
    composer install
    echo ""
fi

# Run PHPUnit tests
echo "Running tests..."
echo "----------------"

if [ -f "vendor/bin/phpunit" ]; then
    ./vendor/bin/phpunit --colors=always
else
    echo "Error: PHPUnit not found. Please run 'composer install' first."
    exit 1
fi

echo ""
echo "================================"
echo "Test run completed!"
echo "================================"