# PHPStan / Larastan Configuration

## Quick Start

```bash
# Run on all configured paths (recommended)
vendor/bin/phpstan analyse

# Or use the helper script
./scripts/phpstan-check.sh

# Run on specific path
./scripts/phpstan-check.sh app/Http/Resources/
```

**Note:** PHPStan is configured to run in single-process mode (`maximumNumberOfProcesses: 0`) to avoid TCP server permission issues on macOS. This is slower but more reliable.

## Configuration

- **Level**: 5 (professional baseline, aim for 8-9)
- **Paths**: app/, config/, database/, routes/
- **Model Properties**: Checked against database schema
- **Baseline**: phpstan-baseline.neon (tracks existing errors)

## TCP Server Issue on macOS - SOLVED

### ✅ Solution: Single-Process Mode

PHPStan is configured to run in single-process mode by setting `maximumNumberOfProcesses: 0` in `phpstan.neon`. This completely disables the TCP server and worker processes.

**Configuration:**
```yaml
parallel:
    maximumNumberOfProcesses: 0
```

**Why this works:** Setting to `0` prevents PHPStan from spawning worker processes and creating the TCP server, running everything in a single process instead.

**Trade-off:** Analysis is slower (no parallelization) but works reliably on macOS without network permission issues.

### Alternative Solutions (if you want parallel processing)

1. **Grant Network Permissions**
   - Open **System Settings** > **Privacy & Security** > **Network**
   - Grant network access to PHP and Terminal/IDE
   - Then set `maximumNumberOfProcesses: 4` (or higher) for faster analysis
   - Open **System Settings** > **Privacy & Security** > **Network** to grant access

2. **Run in CI/CD**
   - CI/CD environments typically have proper permissions configured
   - Can use parallel processing there for faster analysis

3. **Use Docker/Container**
   - Run PHPStan in a container where permissions are more permissive

## Best Practices

1. ✅ Fix new errors immediately (don't add to baseline)
2. ✅ Run before commits
3. ✅ Gradually increase level from 5 to 8-9
4. ✅ Use baseline only for legacy code
5. ✅ Keep PHPDoc types accurate and up-to-date

## Current Baseline Status

- **53 errors** remaining in `phpstan-baseline.neon` (reduced from 134)
- Remaining errors are mostly type system noise (int vs int<0,max>), framework generics limitations, and Resource return type mismatches
- New code must pass PHPStan — only legacy errors are baselined