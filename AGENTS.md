# AGENTS.md

## Build/Lint/Test Commands
- **Test all**: `./vendor/bin/pest`
- **Test single**: `./vendor/bin/pest tests/Unit/YourTest.php`
- **Test coverage**: `composer test:coverage`
- **Lint**: `./vendor/bin/pint`
- **Build PHAR**: `php -d phar.readonly=off vendor/bin/box compile`

## Code Style
- **PHP Version**: ^8.2.0 with `declare(strict_types=1);` in all PHP files
- **Namespace**: PSR-4 autoloading (`App\` for app/, `Tests\` for tests/)
- **Imports**: PSR-12 order (standard library, external packages, local files)
- **Formatting**: Run `./vendor/bin/pint` to auto-format (Laravel preset)
- **Naming**: PascalCase for classes, camelCase for methods, snake_case for functions/config
- **Types**: Use strict types, type hints for parameters/returns, and property types

## Error Handling
- **Commands**: Use `$this->error()`, `$this->warn()`, `$this->info()` for output
- **Services**: Use try/catch blocks with specific exception handling
- **Testing**: Mock external dependencies, use Pest for BDD-style tests

## Architecture
- **Framework**: Laravel Zero (CLI-focused Laravel)
- **Commands**: Extend `LaravelZero\Framework\Commands\Command`
- **DI**: Use constructor injection and Laravel service container
- **Components**: Modular system in `conduit-components/` directory