#!/bin/bash

# Conduit Quality Check Script
# Runs all quality checks that would run in pre-commit hooks

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

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

echo "🔍 Running Conduit quality checks..."
echo ""

# Track if any checks fail
CHECKS_FAILED=0

# 1. PHP Syntax Check
print_status "Checking PHP syntax..."
if find . -name "*.php" -not -path "./vendor/*" -not -path "./storage/*" -exec php -l {} \; > /dev/null; then
    print_success "PHP syntax check passed"
else
    print_error "PHP syntax errors found"
    CHECKS_FAILED=1
fi

# 2. Composer Validation
print_status "Validating composer.json..."
if composer validate --quiet; then
    print_success "Composer validation passed"
else
    print_error "Composer validation failed"
    CHECKS_FAILED=1
fi

# 3. Laravel Pint (Code Formatting)
print_status "Checking code formatting with Laravel Pint..."
if ./vendor/bin/pint --test; then
    print_success "Code formatting check passed"
else
    print_warning "Code formatting issues found. Run './vendor/bin/pint' to fix automatically."
    CHECKS_FAILED=1
fi

# 4. Run Tests
print_status "Running test suite..."
if ./vendor/bin/pest --quiet; then
    print_success "All tests passed"
else
    print_error "Some tests failed"
    CHECKS_FAILED=1
fi

# 5. Pre-commit hooks (if available)
if command -v pre-commit &> /dev/null; then
    print_status "Running pre-commit hooks..."
    if pre-commit run --all-files; then
        print_success "All pre-commit hooks passed"
    else
        print_warning "Some pre-commit hooks failed"
        CHECKS_FAILED=1
    fi
else
    print_warning "Pre-commit not installed. Run scripts/dev-setup.sh to install."
fi

# 6. Check for large files
print_status "Checking for large files..."
LARGE_FILES=$(find . -type f -size +1M -not -path "./vendor/*" -not -path "./.git/*" -not -path "./storage/*" -not -path "./builds/*")
if [[ -z "$LARGE_FILES" ]]; then
    print_success "No large files found"
else
    print_warning "Large files found (consider .gitignore):"
    echo "$LARGE_FILES"
fi

# 7. Check for TODO/FIXME comments
print_status "Checking for TODO/FIXME comments..."
TODO_COUNT=$(grep -r -i --include="*.php" --exclude-dir=vendor "TODO\|FIXME\|XXX\|HACK" . | wc -l | tr -d ' ')
if [[ "$TODO_COUNT" -eq "0" ]]; then
    print_success "No TODO/FIXME comments found"
else
    print_warning "Found $TODO_COUNT TODO/FIXME comments in code"
fi

echo ""

# Final result
if [[ $CHECKS_FAILED -eq 0 ]]; then
    print_success "🎉 All quality checks passed!"
    echo ""
    echo "Your code is ready for commit!"
    exit 0
else
    print_error "❌ Some quality checks failed!"
    echo ""
    echo "Please fix the issues above before committing."
    echo ""
    echo "Quick fixes:"
    echo "  ./vendor/bin/pint          # Fix code formatting"
    echo "  ./vendor/bin/pest          # Run tests with details"
    echo "  pre-commit run --all-files # Run all pre-commit hooks"
    exit 1
fi
