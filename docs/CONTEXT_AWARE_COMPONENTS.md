# Context-Aware Components in Conduit

Conduit components can be configured to activate only in specific contexts, improving performance and providing a more tailored developer experience.

## Context Detection

Conduit automatically detects the following context information:

- **Version Control**: Git repository status, branch, GitHub integration
- **Project Type**: Laravel, Symfony, WordPress, Node.js, Python, etc.
- **Languages**: PHP, JavaScript, TypeScript, Python, Ruby, Go, etc.
- **Frameworks**: Laravel, React, Vue, Angular, Express, etc.
- **Package Managers**: Composer, npm, yarn, pip, cargo, etc.
- **CI/CD**: GitHub Actions, GitLab CI, Jenkins, etc.
- **Containerization**: Docker, Docker Compose, Kubernetes

## Activation Events

Components can declare activation events to control when they're loaded:

### Event Types

- `context:git` - Activate in Git repositories
- `context:github` - Activate in GitHub repositories
- `context:laravel` - Activate in Laravel projects
- `language:php` - Activate when PHP files are present
- `framework:laravel` - Activate when Laravel framework is detected
- `package:composer` - Activate when Composer is used

## Component Configuration

### In composer.json

```json
{
    "name": "vendor/laravel-tools",
    "extra": {
        "laravel": {
            "providers": ["Vendor\\LaravelTools\\ServiceProvider"]
        },
        "conduit": {
            "activation": {
                "activation_events": [
                    "context:laravel",
                    "framework:laravel"
                ],
                "exclude_events": [
                    "context:wordpress"
                ]
            }
        }
    }
}
```

### In Component Registration

```php
$manager->register('laravel-tools', [
    'package' => 'vendor/laravel-tools',
    'description' => 'Laravel development tools',
    'activation' => [
        'activation_events' => [
            'context:laravel',
            'framework:laravel'
        ],
        'always_active' => false,
        'exclude_events' => []
    ]
]);
```

## Examples

### Laravel-Specific Component

```json
{
    "conduit": {
        "activation": {
            "activation_events": ["context:laravel", "framework:laravel"]
        }
    }
}
```

This component only activates in Laravel projects.

### Git-Only Component

```json
{
    "conduit": {
        "activation": {
            "activation_events": ["context:git"]
        }
    }
}
```

This component activates in any Git repository.

### Language-Specific Component

```json
{
    "conduit": {
        "activation": {
            "activation_events": ["language:php", "language:javascript"]
        }
    }
}
```

This component activates when PHP or JavaScript files are present.

### Always Active Component

```json
{
    "conduit": {
        "activation": {
            "always_active": true
        }
    }
}
```

This component is always active regardless of context.

## Viewing Context

To see the current directory context:

```bash
# Show detailed context information
conduit context

# Show context as JSON
conduit context --json

# Show component activation status
conduit components list --show-context
```

## Performance Benefits

1. **Faster Startup**: Only relevant components are loaded
2. **Reduced Memory**: Inactive components don't consume resources
3. **Better UX**: Commands are contextually relevant
4. **Cleaner Interface**: Only see commands that make sense for your project

## Future Enhancements

- Dynamic activation based on file changes
- User-defined custom activation events
- Component dependencies and conflicts
- Performance metrics for activation decisions
