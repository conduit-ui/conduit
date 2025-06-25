# AGENTS.md

## Build/Lint/Test Commands
- **Test all**: `./vendor/bin/pest`
- **Test single**: `./vendor/bin/pest tests/Unit/YourTest.php`
- **Lint**: `./vendor/bin/pint`
- **Build**: `php -d phar.readonly=off vendor/bin/box compile`

## Code Style
- **Imports**: PSR-12 order (standard library, external packages, local files)
- **Formatting**: Run `./vendor/bin/pint --stubs` to auto-format
- **Naming**: PascalCase for classes, snake_case for functions/files
- **Error Handling**: Use `try/catch` blocks + logging via `Log::error()`

## Additional Rules
- No Cursor/Copilot rules found in standard directories (`.cursor/rules/`, `.github/copilot-instructions.md`)