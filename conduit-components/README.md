# Conduit Components - Local Development

This directory contains components under local development, similar to Laravel Nova's approach.

## Structure

```
conduit-components/
├── github-intelligence/     # Enhanced GitHub integration
├── workflow-automation/     # Cross-tool workflow integration  
├── ai-assistant/           # AI-powered development insights
└── conduit-component/      # Base component interface
```

## Command Namespace Strategy

### Unambiguous Commands (No Prefix)
- `repos` - GitHub repositories (clear context)
- `clone` - Git clone operation
- `pr` - Pull request (GitHub context)
- `issues` - GitHub issues

### Conflict Prevention
- Components register their commands in `composer.json`
- Conflict detection during installation
- Automatic prefixing when conflicts occur
- User choice for preferred commands

### Examples
```bash
# Preferred short forms (when unambiguous)
conduit repos              # Browse GitHub repositories
conduit clone owner/repo   # Clone repository
conduit pr "fix auth"      # Create pull request

# Explicit namespacing (when needed)
conduit github:repos       # Explicit GitHub repos
conduit docker:images      # Docker images
conduit aws:buckets        # S3 buckets
```

## Development Workflow

1. **Local Development**: Components in this directory use path repositories
2. **Testing**: Test components individually and integrated
3. **Publishing**: Publish to Packagist when ready
4. **Release**: Update main app dependencies for releases

## Component Registration

Each component declares its commands in `composer.json`:

```json
{
    "extra": {
        "conduit": {
            "commands": {
                "repos": "GitHub\\Commands\\Repos",
                "clone": "GitHub\\Commands\\Clone",
                "pr": "GitHub\\Commands\\PullRequest"
            },
            "namespace_priority": 100,
            "conflict_resolution": "prefix"
        }
    }
}
```