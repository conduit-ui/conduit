# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Conduit is a Laravel Zero CLI application that serves as a personal developer API gateway and MCP (Model Context Protocol) integration engine. It provides a microkernel architecture for integrating multiple developer tools through a component system.

## Development Commands

```bash
# Install dependencies
composer install

# Core development tasks
./vendor/bin/pest                            # Run tests (uses PHPUnit/Pest)
./vendor/bin/pint                           # Code formatting (Laravel Pint)

# Initialize storage (first time only)
php conduit storage:init                     # Initialize SQLite database

# Component management
php conduit components list                  # List installed components
php conduit components discover             # Find new components via GitHub
php conduit components install <name>       # Install a component
php conduit components uninstall <name>     # Uninstall a component

# Context-aware operations
php conduit context                         # Show current context information
php conduit components --show-context       # Show components with activation status

# Interactive mode management
php conduit interactive enable|disable      # Toggle interactive mode

# Build and distribution
php -d phar.readonly=off vendor/bin/box compile  # Build PHAR executable
php conduit [command]                        # Run application locally
```

## Conduit-Powered Development Workflow

### Component Management
- **Components**: Modular functionality via `conduit components`
- **Discovery**: Find new tools via GitHub topics and Packagist
- **Installation**: Automated setup with `conduit components install`
- **Registry**: Curated vs community components

### Future Development Commands
Once component ecosystem is built, development will use:
```bash
# Version control (via github-zero component)
conduit github repos                        # List repositories
conduit github clone <repo>                 # Clone repositories
conduit github pr create                    # Create pull requests

# Package management (via conduit-composer component)  
conduit composer require <package>          # Smart package installation
conduit composer audit                      # Security and dependency analysis

# Testing and quality (via conduit-laravel component)
conduit laravel test                        # Run Laravel tests
conduit laravel migrate                     # Database migrations
```

## Architecture

### Microkernel Design
- **Core**: Minimal Laravel Zero framework with component system only
- **Components**: All functionality via installable components
- **Discovery**: Automated component finding via GitHub search (topic: `conduit-component`)
- **Storage**: SQLite database for component metadata and settings (~/.conduit/conduit.sqlite)
- **Registry**: Tiered components (core/certified/community)

### Component Management System
- **ComponentManager**: Core service for component lifecycle management
- **ComponentDiscoveryService**: GitHub-based component discovery using `jordanpartridge/github-client`
- **ComponentInstallationService**: Handles secure package installation and registration
- **ComponentStorage**: SQLite-based persistence for component metadata
- **Context-Aware Activation**: Components can have conditional activation based on environment

### Component Structure
Components are standard Composer packages with:
- Laravel Zero service provider registration
- GitHub topic: `conduit-component` for discoverability
- Optional context-aware activation configuration
- Self-validation capabilities
- Metadata provision (commands, env vars, etc.)

### Development Philosophy
- **Conduit builds Conduit**: Use Conduit itself for development tasks
- **Component-first**: Everything is a discoverable, installable component
- **AI-ready**: MCP integration for AI tool collaboration
- **Microkernel**: Core remains minimal and focused
- **Security-focused**: Secure package installation with validation

## Testing Strategy
- **Unit Tests**: Individual component testing
- **Integration Tests**: Component installation and interaction
- **End-to-end**: Full workflow testing with multiple components
- **Self-validation**: Components can test their own health

## Key Dependencies
- `laravel-zero/framework`: CLI framework foundation
- `jordanpartridge/github-zero`: GitHub API integration
- `jordanpartridge/laravel-say-logger`: Enhanced logging
- `symfony/process`: Process execution for component installation
- `humbug/box`: PHAR compilation for distribution
- `pestphp/pest`: Testing framework

## Development Workflow

### First-Time Setup
1. `composer install` - Install dependencies
2. `php conduit storage:init` - Initialize SQLite database
3. `php conduit components discover` - Find available components
4. `php conduit components install <name>` - Install desired components

### Testing & Quality
- Tests use Pest framework with PHPUnit under the hood
- Run `./vendor/bin/pest` for full test suite
- Code formatting with Laravel Pint: `./vendor/bin/pint`
- Component installation validation includes security checks

### Component Development
- Components must be Composer packages with Laravel Zero service providers
- Add `conduit-component` GitHub topic for discoverability
- Use `app/Contracts/ComponentInterface.php` for standard interface
- Database storage migrations in `database/migrations/`

### Build & Distribution
- PHAR building via Box: `php -d phar.readonly=off vendor/bin/box compile`
- Configuration in `box.json`
- Binary output: `conduit` executable