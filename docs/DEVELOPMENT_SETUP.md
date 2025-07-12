# Conduit Development Setup Guide

**Professional development environment with automated quality checks**

---

## Quick Start

### Automated Setup (Recommended)

```bash
# Clone and setup in one command
git clone https://github.com/jordanpartridge/conduit.git
cd conduit
./scripts/dev-setup.sh
```

The setup script will:
- ✅ Install PHP dependencies via Composer
- ✅ Initialize SQLite database storage
- ✅ Install pre-commit framework and hooks
- ✅ Create `.env` file with sensible defaults
- ✅ Run initial quality checks
- ✅ Verify everything works with test suite

### Manual Setup

If you prefer manual setup or need to troubleshoot:

```bash
# 1. Install dependencies
composer install

# 2. Initialize storage
php conduit storage:init

# 3. Install pre-commit (requires Python)
pip3 install pre-commit
pre-commit install
pre-commit install --hook-type commit-msg

# 4. Create .env file
cp .env.example .env  # Edit as needed

# 5. Run initial checks
./scripts/quality-check.sh
```

---

## Quality Assurance System

### Pre-commit Hooks

Conduit uses **pre-commit hooks** to maintain code quality automatically:

```yaml
# .pre-commit-config.yaml highlights:

🔍 General Checks:
  - Trailing whitespace removal
  - File ending normalization
  - YAML/JSON validation
  - Large file prevention
  - Merge conflict detection

🐘 PHP Quality:
  - PHP syntax validation
  - Laravel Pint code formatting
  - Pest test suite execution
  - Composer package validation

📝 Commit Standards:
  - Conventional commit message format
  - YAML file linting
```

### Development Commands

```bash
# Code Quality
./vendor/bin/pint           # Format PHP code (auto-fix)
./vendor/bin/pest           # Run test suite
./scripts/quality-check.sh  # Run all quality checks

# Pre-commit Management
pre-commit run --all-files  # Run hooks on all files
pre-commit run php-syntax-check  # Run specific hook
pre-commit autoupdate       # Update hook versions

# Conduit Development
php conduit                 # Run CLI application
php conduit storage:init    # Initialize database
php conduit components discover  # Find components
```

---

## Development Workflow

### 1. Feature Development

```bash
# Start new feature
git checkout -b feature/your-feature-name

# Make changes
# ... edit code ...

# Quality checks run automatically on commit
git add .
git commit -m "feat: add awesome new feature"
# ↳ Triggers: syntax check, formatting, tests, commit msg validation

# Push when ready
git push origin feature/your-feature-name
```

### 2. Commit Message Format

Conduit enforces **conventional commits** for consistency:

```bash
# Format: type(scope): description

✅ Good examples:
feat: add Spotify component integration
fix: resolve component activation bug
docs: update installation guide
test: add component manager tests
refactor: improve context detection performance

❌ Bad examples:
added new stuff
fix bug
updated files
```

**Allowed types**: `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`, `perf`, `ci`, `build`, `revert`

### 3. Quality Gates

Every commit must pass:

1. **PHP Syntax** - No syntax errors
2. **Code Formatting** - Laravel Pint standards
3. **Test Suite** - All Pest tests pass
4. **Composer Validation** - Valid package configuration
5. **Commit Message** - Conventional format

---

## Tool Configuration

### Laravel Pint

Code formatting configuration in `pint.json`:

```json
{
    "preset": "laravel",
    "rules": {
        "simplified_null_return": true,
        "not_operator_with_successor_space": false,
        "concat_space": {
            "spacing": "one"
        }
    }
}
```

### Pest Testing

Test configuration in `phpunit.xml.dist`:

- **Unit Tests**: `tests/Unit/` - Fast, isolated tests
- **Feature Tests**: `tests/Feature/` - Integration tests
- **Coverage**: Tracks code coverage metrics

```bash
# Test commands
./vendor/bin/pest                    # Run all tests
./vendor/bin/pest --coverage        # With coverage report
./vendor/bin/pest tests/Unit/        # Unit tests only
./vendor/bin/pest --filter=Component # Specific test pattern
```

### Pre-commit Configuration

Hook configuration in `.pre-commit-config.yaml`:

- **Fast Fail**: `fail_fast: false` - Run all hooks even if one fails
- **Stages**: Hooks run on `pre-commit` and `commit-msg` stages
- **Local Hooks**: Custom PHP-specific hooks for Laravel/Conduit
- **External Hooks**: Standard file and format validation

---

## Environment Configuration

### Required Software

- **PHP 8.2+** - Core language requirement
- **Composer** - PHP package management
- **Python 3.7+** - For pre-commit framework
- **Git** - Version control

### Optional Software

- **pre-commit** - Automated quality checks (recommended)
- **GitHub CLI (`gh`)** - Enhanced GitHub integration

### Environment Variables

Create `.env` file with:

```bash
# Application
APP_ENV=local
APP_DEBUG=true

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=debug
SAY_LOGGER_ENABLED=true  # macOS text-to-speech (macOS only)

# GitHub Integration
GITHUB_TOKEN=your_token_here  # For github-zero component

# Interactive Mode
CONDUIT_INTERACTIVE=true  # Enhanced CLI experience
```

---

## Troubleshooting

### Common Issues

#### 1. Pre-commit Installation Fails

```bash
# Error: pip3 not found
# Solution: Install Python 3
brew install python3  # macOS
apt install python3-pip  # Ubuntu

# Error: Permission denied
# Solution: Use user installation
pip3 install --user pre-commit
```

#### 2. Laravel Pint Formatting Issues

```bash
# Error: Pint fails to format
# Debug: Check PHP syntax first
php -l app/Services/ComponentManager.php

# Fix: Run Pint manually
./vendor/bin/pint --verbose

# Alternative: Skip formatting temporarily
git commit --no-verify -m "wip: debugging formatting"
```

#### 3. Test Failures

```bash
# Error: Tests fail in pre-commit
# Debug: Run tests manually
./vendor/bin/pest --verbose

# Check: Database state
php conduit storage:init

# Reset: Clear test database
rm database/database.sqlite
php conduit storage:init
```

#### 4. Commit Message Validation

```bash
# Error: Conventional commit format required
# Bad: "fix bug"
# Good: "fix: resolve component activation bug"

# Bypass: Emergency commits only
git commit --no-verify -m "fix: emergency hotfix"
```

### Development Environment Reset

```bash
# Complete reset (nuclear option)
rm -rf vendor/ .pre-commit-config.yaml database/database.sqlite
composer install
./scripts/dev-setup.sh
```

---

## CI/CD Integration

### GitHub Actions (Future)

Planned CI/CD pipeline:

```yaml
# .github/workflows/ci.yml (planned)
name: CI
on: [push, pull_request]
jobs:
  quality:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2
      - run: composer install
      - run: ./vendor/bin/pest
      - run: ./vendor/bin/pint --test
```

### Local Development Server

```bash
# Run Conduit in development mode
php conduit --env=local

# Debug mode with verbose output
php conduit --verbose components list

# Interactive mode for testing
php conduit interactive enable
php conduit  # Shows enhanced interface
```

---

## Contributing

### Code Style

- **PSR-12** compliance via Laravel Pint
- **Type hints** required for all method parameters and returns
- **Docblocks** required for all public methods and classes
- **Test coverage** required for all new features

### Pull Request Process

1. **Create feature branch** from `main`
2. **Implement changes** with tests
3. **Run quality checks** via `./scripts/quality-check.sh`
4. **Commit with conventional format**
5. **Push and create PR**
6. **Pass CI checks** (when implemented)
7. **Code review** and merge

### Development Guidelines

- **Small commits** with clear purposes
- **Test-driven development** where appropriate
- **Documentation updates** for user-facing changes
- **Component-based architecture** for new features

---

**Ready to contribute? Run `./scripts/dev-setup.sh` and start building!**
