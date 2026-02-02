# API Integration Check - Setup Guide

Quick setup guide for the automated integration check improvements.

---

## 🚀 Quick Setup (2 minutes)

### Step 1: Install Pre-commit Hook

```bash
./scripts/install-pre-commit-hook.sh
```

This will:
- Install the pre-commit hook
- Ask if you want to backup/replace existing hooks
- Make the hook executable

### Step 2: Test It Works

```bash
# Run the integration check manually
./scripts/check-api-integration.sh
```

You should see:
```
✓ OpenAPI schema generated
✓ No mismatches found
✓ api.json is valid
✓ PHPStan passed
✓ All API tests passed
```

### Step 3: That's It!

The hook will now run automatically before each commit. CI/CD will run on every PR.

---

## 📋 What Was Added

### Files Created

1. **`scripts/check-api-integration.sh`**
   - Single command to run all checks
   - Usage: `./scripts/check-api-integration.sh [--no-tests] [--no-phpstan]`

2. **`.github/workflows/api-contract-check.yml`**
   - GitHub Actions workflow
   - Runs automatically on PRs and pushes to main/develop
   - No setup needed - just commit and push

3. **`.git/hooks/pre-commit-api-check`**
   - Pre-commit hook source
   - Installed via `install-pre-commit-hook.sh`

4. **`scripts/install-pre-commit-hook.sh`**
   - Helper to install the pre-commit hook
   - Handles existing hooks gracefully

5. **Documentation**
   - `INTEGRATION_CHECK_IMPROVEMENTS.md` - Full documentation
   - `INTEGRATION_CHECK_SETUP.md` - This file

---

## 🔄 Daily Workflow

### Before (Manual - 12 steps, 5-10 minutes)
```bash
# Edit Resource
# Generate schema
php artisan scramble:export --path=api.json
# Check mismatches
php check-api-mismatches.php
# Fix issues
# Update tests
# Run tests
php artisan test --filter=ApiTest
# ... 6 more steps
```

### After (Automated - 1 command, 30 seconds)
```bash
# Edit Resource
./scripts/check-api-integration.sh
# Commit (hook runs automatically)
git commit -m "Update API"
```

---

## ✅ Verification Checklist

- [ ] Pre-commit hook installed: `ls -la .git/hooks/pre-commit`
- [ ] Integration script works: `./scripts/check-api-integration.sh`
- [ ] GitHub Actions workflow exists: `.github/workflows/api-contract-check.yml`
- [ ] Test commit triggers hook (make a test commit)

---

## 🐛 Troubleshooting

### Script fails with "Failed to generate OpenAPI schema"

**Cause:** Environment not set up (missing .env, vendor, etc.)

**Fix:** This is expected if you're not in a fully set up environment. The script will work fine in your development environment.

### Pre-commit hook not running

**Check:**
```bash
ls -la .git/hooks/pre-commit
```

**Reinstall:**
```bash
./scripts/install-pre-commit-hook.sh
```

### CI/CD not running

**Check:**
1. Workflow file is committed: `.github/workflows/api-contract-check.yml`
2. GitHub Actions is enabled for your repo
3. Check the Actions tab in GitHub

---

## 📚 Next Steps

After setup, you can:

1. **Use the script regularly:**
   ```bash
   ./scripts/check-api-integration.sh
   ```

2. **Rely on automation:**
   - Pre-commit hook catches issues before commit
   - CI/CD validates on every PR

3. **Move to Priority 2 improvements:**
   - Response validation middleware
   - Contract testing
   - See `INTEGRATION_CHECK_CRITIQUE.md`

---

## 💡 Tips

- Run `./scripts/check-api-integration.sh` before committing to catch issues early
- Don't skip the pre-commit hook - it saves time
- Check CI/CD status before asking for PR review
- Always update `api.json` when modifying API Resources

---

## 📞 Need Help?

1. Check `INTEGRATION_CHECK_IMPROVEMENTS.md` for detailed docs
2. Check `INTEGRATION_CHECK_CRITIQUE.md` for improvement ideas
3. Review script output for specific error messages
