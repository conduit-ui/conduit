# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with this Conduit component.

## Component: env-manager

A Conduit component for env-manager functionality

## Development Commands

```bash
# Install dependencies
composer install

# Code quality and testing
./vendor/bin/pint          # Laravel PHP formatter
./vendor/bin/phpstan analyze   # Static analysis  
./vendor/bin/pest          # Run tests (Pest framework)
```

## Architecture

This is a Conduit component that follows the standard patterns:
- **ServiceProvider**: Registers commands with Laravel/Conduit
- **Commands/**: Contains CLI command implementations
- **Tests/**: Pest-based test suite

## Integration

This component integrates with Conduit through:
- Service provider auto-discovery
- Command registration via Conduit's component system
- Standard Laravel Zero command patterns

## Configuration

Add any configuration requirements here.