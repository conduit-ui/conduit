# GitHub Actions Setup Guide

## Required Secrets & Tokens

### 1. Codecov Integration
1. Go to [codecov.io](https://codecov.io) and sign in with GitHub
2. Add your repository to Codecov
3. Get your upload token from repository settings
4. Add `CODECOV_TOKEN` to GitHub repository secrets

### 2. Branch Protection (Required for Auto-merge)
1. Go to repository Settings → Branches
2. Add branch protection rule for `master`:
   - ✅ Require status checks to pass before merging
   - ✅ Require branches to be up to date before merging
   - ✅ Include administrators
   - Select required status checks: `tests`, `static-analysis`

### 3. Dependabot Auto-merge Setup
1. Enable "Allow auto-merge" in repository Settings → General
2. The auto-merge workflow will handle the rest automatically

## Optional Configurations

### Security Scanning
- CodeQL will automatically create security alerts in the Security tab
- No additional setup required - works out of the box

### PHAR Building
- Automatically triggers on version tags (e.g., `v1.0.0`)
- Uploads PHAR files to GitHub releases
- No additional setup required

### Rector
- Creates configuration dynamically
- Manual trigger via Actions tab → Rector → Run workflow
- Safe to run - only applies modern PHP patterns

## Testing the Setup

1. **Create a test PR** - All workflows should run
2. **Check coverage report** - Should appear in PR comments
3. **Tag a version** - Should trigger PHAR build
4. **Wait for Dependabot PR** - Should auto-merge if minor/patch

## Workflow Dependencies

```
Tests (ci.yml) ─┐
                ├─ Auto-merge depends on these passing
Security      ─┘
Coverage ────────── Separate reporting
PHAR Build ──────── Only on tags/releases
Rector ─────────── Manual trigger only
```

## Modern Features Used

- **Actions v4** - Latest action versions for better performance
- **PCOV Coverage** - 3x faster than Xdebug
- **Matrix Testing** - PHP 8.2 & 8.3 compatibility  
- **Smart Caching** - Composer cache for faster builds
- **Security-first** - Multiple security scanning layers
- **Auto-merge** - Streamlined dependency updates
- **Artifact Storage** - 30-day PHAR retention