# PHPStan Network Permissions Guide - macOS

## What Processes Need Network Access?

When PHPStan runs, it spawns multiple processes that need network permissions to create TCP sockets for parallel processing.

### In System Settings > Privacy & Security > Network, look for:

#### 1. **PHP Binary** (CRITICAL)
- **Path**: `/opt/homebrew/bin/php`
- **Shows as**: `php` or the full path
- **Why**: This is the PHP interpreter that runs PHPStan and its worker processes
- **Action**: ✅ Enable network access

#### 2. **Terminal/IDE** (Where you run commands)
- **Terminal.app** - If running from Terminal
- **Cursor** - If running from Cursor IDE
- **VS Code** - If running from VS Code
- **Why**: The parent process that spawns PHPStan
- **Action**: ✅ Enable network access

#### 3. **PHPStan Worker Processes** (Dynamic)
These are spawned on-demand and might appear as:
- Generic `php` processes
- Processes with `phpstan` in the command line
- **Note**: These appear dynamically when PHPStan runs, so you might see a permission prompt popup

### How to Grant Permissions

#### Method 1: Pre-emptive (Recommended)
1. Open **System Settings** (or System Preferences on older macOS)
2. Go to **Privacy & Security** → **Network**
3. Look for and enable:
   - ✅ `php` or `/opt/homebrew/bin/php`
   - ✅ `Terminal` (if using Terminal)
   - ✅ `Cursor` (if using Cursor IDE)
4. **Restart Terminal/IDE completely**
5. Try PHPStan again

#### Method 2: When Prompted
1. When PHPStan runs, macOS might show a popup:
   ```
   "php" would like to accept incoming network connections
   ```
2. Click **"Allow"** or **"OK"**
3. If you clicked "Deny", go to System Settings and enable it manually

### Verification

After granting permissions, test:
```bash
# Test if PHP can create sockets
php -r "\$s = @stream_socket_server('tcp://127.0.0.1:9999'); echo \$s ? '✓ OK' : '✗ Blocked';"

# Try PHPStan
vendor/bin/phpstan analyse app/Http/Resources/Api/V1/QuotationResource.php --level=5
```

### Troubleshooting

**If permissions still don't work:**

1. **Check Full Disk Access** (sometimes needed):
   - System Settings → Privacy & Security → Full Disk Access
   - Enable for Terminal/IDE if needed

2. **Reset Network Permissions**:
   ```bash
   tccutil reset Network com.apple.Terminal
   # Then grant permissions again
   ```

3. **Check for Multiple PHP Installations**:
   ```bash
   which php
   # Make sure you grant permissions to the correct one
   ```

4. **Run in CI/CD Instead**:
   - Most reliable solution
   - CI/CD environments typically have proper permissions

### Why This Happens

PHPStan 2.x uses a TCP server for parallel processing:
- Main process creates a TCP server on localhost
- Worker processes connect to this server
- macOS requires explicit permission for network sockets, even on localhost
- This is a security feature, not a bug

### Alternative: Disable Parallel Processing

If you can't get permissions working, you can disable parallel processing in `phpstan.neon`:

```yaml
parameters:
    parallel:
        maximumNumberOfProcesses: 1
```

However, this makes analysis slower and still might not work if the main process can't create the TCP server.
