# Conduit Core

Core functionality library for the Conduit ecosystem - shared services, interfaces, and component management.

## Installation

```bash
composer require conduit-io/core
```

## Usage

This package provides the foundation for building Conduit components:

- Component interfaces and contracts
- Database storage for component metadata  
- Base services for component management
- Shared utilities and patterns

## Component Development

Extend the base `ComponentInterface` to create new Conduit components:

```php
use ConduitIo\Core\Contracts\ComponentInterface;

class MyComponent implements ComponentInterface
{
    public function getName(): string
    {
        return 'my-component';
    }
    
    // ... implement other methods
}
```

## Architecture

This package is designed to be used by:
- `conduit-io/conduit` - Main CLI application
- `conduit-io/*-connector` - Individual component packages
- Third-party component developers

Co-Authored-By: Conduit Assistant <noreply@conduit.io>