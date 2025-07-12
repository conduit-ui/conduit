#!/bin/bash

# Conduit Development Environment Setup Script
# This script sets up the development environment with all necessary tools and hooks

set -e

echo "🚀 Setting up Conduit development environment..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if we're in the right directory
if [[ ! -f "composer.json" ]] || [[ ! -f "conduit" ]]; then
    print_error "This script must be run from the Conduit project root directory!"
    exit 1
fi

print_status "Checking system requirements..."

# Check PHP version
if ! command -v php &> /dev/null; then
    print_error "PHP is not installed or not in PATH"
    exit 1
fi

PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
if [[ ! "$PHP_VERSION" =~ ^8\.[2-9]|^[9-9]\.[0-9] ]]; then
    print_error "PHP 8.2+ is required, found PHP $PHP_VERSION"
    exit 1
fi

print_success "PHP $PHP_VERSION detected"

# Check Composer
if ! command -v composer &> /dev/null; then
    print_error "Composer is not installed or not in PATH"
    exit 1
fi

print_success "Composer detected"

# Install PHP dependencies
print_status "Installing Composer dependencies..."
composer install --dev
print_success "Composer dependencies installed"

# Initialize storage if not exists
if [[ ! -f "database/database.sqlite" ]]; then
    print_status "Initializing Conduit storage..."
    php conduit storage:init
    print_success "Storage initialized"
fi

# Check Python and pip for pre-commit
print_status "Checking Python environment for pre-commit..."
if ! command -v python3 &> /dev/null; then
    print_warning "Python3 not found. Pre-commit requires Python."
    print_warning "Please install Python3 and run this script again."
else
    # Install pre-commit if not already installed
    if ! command -v pre-commit &> /dev/null; then
        print_status "Installing pre-commit..."
        pip3 install pre-commit
        print_success "Pre-commit installed"
    else
        print_success "Pre-commit already installed"
    fi

    # Install pre-commit hooks
    print_status "Installing pre-commit hooks..."
    pre-commit install
    pre-commit install --hook-type commit-msg
    print_success "Pre-commit hooks installed"

    # Run pre-commit on all files to ensure everything works
    print_status "Running initial pre-commit check..."
    if pre-commit run --all-files; then
        print_success "All pre-commit checks passed!"
    else
        print_warning "Some pre-commit checks failed. This is normal for the first run."
        print_warning "Files have been automatically formatted. Please review changes."
    fi
fi

# Create .env file if it doesn't exist
if [[ ! -f ".env" ]]; then
    print_status "Creating .env file..."
    cat > .env << 'EOF'
# Conduit Development Environment

# Application
APP_ENV=local
APP_DEBUG=true

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=debug

# Laravel Say Logger (macOS only)
SAY_LOGGER_ENABLED=true

# GitHub Integration (optional)
# GITHUB_TOKEN=your_token_here

# Interactive Mode
CONDUIT_INTERACTIVE=true
EOF
    print_success ".env file created"
    print_warning "Don't forget to set your GITHUB_TOKEN in .env for GitHub features"
fi

# Run tests to verify everything works
print_status "Running test suite..."
if ./vendor/bin/pest; then
    print_success "All tests passed!"
else
    print_warning "Some tests failed. Please check the output above."
fi

# Final status
echo ""
print_success "🎉 Development environment setup complete!"
echo ""
echo "Next steps:"
echo "1. Review and commit any changes made by pre-commit hooks"
echo "2. Set your GITHUB_TOKEN in .env for GitHub integration"
echo "3. Run 'php conduit' to start using Conduit"
echo "4. Run 'php conduit components discover' to find available components"
echo ""
echo "Development commands:"
echo "  ./vendor/bin/pest          # Run tests"
echo "  ./vendor/bin/pint          # Format code"
echo "  pre-commit run --all-files # Run all pre-commit hooks"
echo "  php conduit                # Use Conduit CLI"
echo ""
