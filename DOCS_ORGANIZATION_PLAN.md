# Documentation Organization Plan

## Current State

### Root Directory (Cluttered)
- 6 Integration Check files
- API_MISMATCHES_REPORT.md
- PHPSTAN_NETWORK_PERMISSIONS.md
- README_PHPSTAN.md
- SCRAMBLE_USAGE_CLARIFICATION.md
- AGENTS.md, CLAUDE.md, GEMINI.md (AI config - keep in root)
- README.md (keep in root)
- session-ses_43d8.md (temporary - delete)

### Existing Structure
- `docs/` - Well-organized numbered sections (00-08)
- `docs/04-api/` - API documentation (has README.md)

---

## Proposed Organization

### Option A: Subdirectory in docs/04-api/ (Recommended)

```
docs/
  04-api/
    README.md (existing)
    integration-check/
      README.md (index/overview)
      flow.md (original 12-step flow)
      quick-reference.md
      setup.md
      improvements.md (Priority 1)
      roadmap.md (next steps)
      critique.md (assessment)
    tools/
      scramble.md (Scramble usage clarification)
      phpstan.md (combine README_PHPSTAN + PHPSTAN_NETWORK_PERMISSIONS)
    reports/
      api-mismatches.md (API_MISMATCHES_REPORT)
```

**Pros:**
- ✅ Follows existing docs structure
- ✅ Groups related content
- ✅ Easy to find
- ✅ Can be linked from docs/INDEX.md

**Cons:**
- ⚠️ Slightly deeper nesting

---

### Option B: New docs/09-dev-tools/ Section

```
docs/
  09-dev-tools/
    integration-check/
      (same as Option A)
    phpstan.md
    scramble.md
    api-mismatches-report.md
```

**Pros:**
- ✅ Clear separation of dev tools
- ✅ Could expand with other tools

**Cons:**
- ⚠️ Breaks numbered sequence (would be 09)
- ⚠️ Less discoverable

---

### Option C: Keep in Root, But Organize

```
docs/
  api/
    integration-check/
      (same structure)
    tools/
      (same structure)
```

**Pros:**
- ✅ Simple structure
- ✅ Easy access

**Cons:**
- ⚠️ Doesn't follow existing docs/ pattern
- ⚠️ Less consistent

---

## Recommendation: Option A

**Move to:** `docs/04-api/integration-check/` and `docs/04-api/tools/`

**Why:**
1. Follows existing numbered structure
2. API-related docs belong in API section
3. Easy to reference from `docs/04-api/README.md`
4. Can update `docs/INDEX.md` to include them

---

## File Mapping

| Current Location | New Location | Action |
|-----------------|--------------|--------|
| `INTEGRATION_CHECK_FLOW.md` | `docs/04-api/integration-check/flow.md` | Move & rename |
| `INTEGRATION_CHECK_QUICK_REFERENCE.md` | `docs/04-api/integration-check/quick-reference.md` | Move & rename |
| `INTEGRATION_CHECK_SETUP.md` | `docs/04-api/integration-check/setup.md` | Move & rename |
| `INTEGRATION_CHECK_IMPROVEMENTS.md` | `docs/04-api/integration-check/improvements.md` | Move & rename |
| `INTEGRATION_CHECK_ROADMAP.md` | `docs/04-api/integration-check/roadmap.md` | Move & rename |
| `INTEGRATION_CHECK_CRITIQUE.md` | `docs/04-api/integration-check/critique.md` | Move & rename |
| `SCRAMBLE_USAGE_CLARIFICATION.md` | `docs/04-api/tools/scramble.md` | Move & rename |
| `README_PHPSTAN.md` | `docs/04-api/tools/phpstan.md` | Move & rename |
| `PHPSTAN_NETWORK_PERMISSIONS.md` | `docs/04-api/tools/phpstan.md` | Merge into phpstan.md |
| `API_MISMATCHES_REPORT.md` | `docs/04-api/reports/api-mismatches.md` | Move & rename |
| `session-ses_43d8.md` | (delete) | Remove |

---

## New Files to Create

### 1. `docs/04-api/integration-check/README.md`
Index/overview of integration check documentation

### 2. `docs/04-api/tools/README.md`
Index of API development tools

### 3. Update `docs/04-api/README.md`
Add links to new subdirectories

### 4. Update `docs/INDEX.md`
Add references to new sections

---

## Implementation Steps

1. Create directories
2. Move files (with renames)
3. Merge PHPStan docs
4. Create README files
5. Update existing README files
6. Delete temporary files
7. Update links in moved files (if any)

---

## Keep in Root

These should stay in root:
- `README.md` - Project overview
- `CLAUDE.md` - AI instructions
- `AGENTS.md` - AI agent config
- `GEMINI.md` - Gemini-specific config

---

## Benefits

1. ✅ Cleaner root directory
2. ✅ Better discoverability
3. ✅ Follows existing structure
4. ✅ Easy to maintain
5. ✅ Can be indexed in docs/INDEX.md
