# GitHub Zero Integration Guide

This guide helps AI agents integrate the github-zero package into Conduit and troubleshoot any issues.

## Quick Setup

1. **Install the package**:
   ```bash
   composer require jordanpartridge/github-zero
   ```

2. **Register the service provider** in `config/app.php`:
   ```php
   'providers' => [
       // ... existing providers
       JordanPartridge\GitHubZero\GitHubZeroServiceProvider::class,
   ],
   ```

3. **Verify installation**:
   ```bash
   php conduit list
   ```
   You should see `clone` and `repos` commands in the list.

4. **Test the commands**:
   ```bash
   # List repositories interactively
   php conduit repos --interactive

   # Clone with interactive selection
   php conduit clone --interactive
   ```

## Troubleshooting Steps

### 1. Check Package Installation
```bash
# Verify package is installed
composer show jordanpartridge/github-zero

# Check autoloader
composer dump-autoload
```

### 2. Verify Service Provider Registration
- Check `config/app.php` has the service provider in the `providers` array
- Ensure no typos in the class name: `JordanPartridge\GitHubZero\GitHubZeroServiceProvider::class`

### 3. Check Command Registration
```bash
# List all available commands
php conduit list

# Look for 'clone' and 'repos' in the output
# If missing, the service provider isn't loading correctly
```

### 4. Environment Setup
```bash
# GitHub Zero requires a GitHub token
export GITHUB_TOKEN=your_token_here

# Test token access
php conduit repos --limit=5
```

### 5. Debug Service Provider Loading
Add this to `app/Providers/AppServiceProvider.php` boot method to debug:
```php
public function boot(): void
{
    if (class_exists(\JordanPartridge\GitHubZero\GitHubZeroServiceProvider::class)) {
        $this->app->make('log')->info('GitHub Zero service provider found');
    } else {
        $this->app->make('log')->info('GitHub Zero service provider NOT found');
    }
}
```

## Common Issues & Solutions

### Issue: Commands not showing in `php conduit list`
**Solution**: Service provider not registered correctly
- Double-check `config/app.php` providers array
- Run `php conduit config:cache` to clear config cache
- Restart any running processes

### Issue: "Class not found" errors
**Solution**: Autoloader issue
- Run `composer dump-autoload`
- Check `composer.json` has correct autoload configuration
- Verify package is in `vendor/` directory

### Issue: GitHub API errors
**Solution**: Token/authentication issue
- Verify `GITHUB_TOKEN` environment variable is set
- Test token with: `curl -H "Authorization: token $GITHUB_TOKEN" https://api.github.com/user`
- Ensure token has required permissions (repo access)

### Issue: Package not found during installation
**Solution**: Package not yet on Packagist or version issue
- Check if package exists: [jordanpartridge/github-zero on Packagist](https://packagist.org/packages/jordanpartridge/github-zero)
- Try: `composer require jordanpartridge/github-zero:dev-main` for development version

## Reporting Issues

If you encounter issues with github-zero integration into Conduit:

1. **Check existing issues**: [GitHub Issues](https://github.com/jordanpartridge/github-zero/issues)

2. **Create a new issue** with:
   - **Title**: "Conduit Integration: [Brief description]"
   - **Environment**:
     - Conduit version
     - PHP version
     - OS
   - **Steps to reproduce**
   - **Expected vs actual behavior**
   - **Error messages** (full stack traces)
   - **Configuration**: Relevant parts of `config/app.php`

3. **Include debugging info**:
   ```bash
   # Package version
   composer show jordanpartridge/github-zero

   # Laravel Zero version
   php conduit --version

   # Available commands
   php conduit list

   # Any error output
   php conduit repos --limit=1 -v
   ```

## Advanced Configuration

### Custom Configuration
Publish the config file for customization:
```bash
php conduit vendor:publish --tag=github-zero-config
```

This creates `config/github-zero.php` for customizing defaults:
```php
return [
    'token' => env('GITHUB_TOKEN'),
    'default_limit' => 10,
    'auto_open_editor' => true,
    'editor_command' => 'code',
];
```

### Adding Custom Commands
Extend github-zero by creating custom commands in your Conduit project that use the GitHub client.

## Success Indicators

✅ Package installed without errors
✅ Service provider registered in config
✅ Commands appear in `php conduit list`
✅ Commands execute without fatal errors
✅ GitHub API responses work with valid token
✅ Interactive prompts display correctly

When all indicators pass, github-zero is successfully integrated into Conduit!
