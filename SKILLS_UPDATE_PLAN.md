# Skills Update Plan

## Current State

**22 skill files** in `.claude/skills/enter365/`:
- SKILL.md (main entry - 980 lines)
- RESOURCES.md (API Resources patterns)
- TESTING_PATTERNS.md (Testing workflows)
- 19 other domain-specific skills

## What Needs Updating

### High Priority (Workflow-Related)

1. **SKILL.md** - Main entry point
   - Update "Useful Commands" section (line 961-979)
   - Add API contract validation workflow
   - Add automated tools references

2. **RESOURCES.md** - API Resources
   - Add contract validation section
   - Add field naming conventions (`total_amount` standard)
   - Add workflow for updating Resources

3. **TESTING_PATTERNS.md** - Testing
   - Add API contract testing patterns
   - Update workflow references

### Medium Priority (Reference Updates)

4. **FILE_ORGANIZATION.md** - If it mentions API docs
5. **CONFIGURATION.md** - If it mentions Scramble/PHPStan config

### Low Priority (Domain-Specific)

- Other skills (EVENTS.md, MODELS.md, etc.) likely don't need updates
- They focus on domain patterns, not workflows

---

## Best Approach

### Option A: Systematic Update (Recommended)

**Strategy:** Update skills that reference workflows/tools, leave domain skills unchanged.

**Steps:**
1. Update SKILL.md (main entry - most important)
2. Update RESOURCES.md (API-specific)
3. Update TESTING_PATTERNS.md (if needed)
4. Check other skills for workflow references
5. Create summary of changes

**Pros:**
- ✅ Focused on what matters
- ✅ Preserves domain knowledge
- ✅ Clear, targeted updates

**Time:** ~30 minutes

---

### Option B: Comprehensive Audit

**Strategy:** Read all 22 skills, identify what needs updating, update everything.

**Pros:**
- ✅ Complete coverage
- ✅ No missed references

**Cons:**
- ⚠️ Time-consuming (2-3 hours)
- ⚠️ Most skills don't need updates
- ⚠️ Risk of unnecessary changes

---

### Option C: Create New Skill

**Strategy:** Create `API_CONTRACT_VALIDATION.md` skill, reference from SKILL.md.

**Pros:**
- ✅ Keeps existing skills clean
- ✅ Centralized documentation
- ✅ Easy to maintain

**Cons:**
- ⚠️ Information scattered
- ⚠️ Need to update references

---

## Recommendation: Option A (Systematic Update)

**Why:**
1. Most skills are domain-specific (don't need workflow updates)
2. Only 3-4 skills need updates
3. Fast and focused
4. Preserves existing knowledge

**Files to Update:**
1. ✅ SKILL.md - Add workflow section
2. ✅ RESOURCES.md - Add contract validation
3. ✅ TESTING_PATTERNS.md - Add API contract testing (if needed)
4. ⚠️ Check FILE_ORGANIZATION.md, CONFIGURATION.md (quick scan)

---

## Implementation Plan

### Phase 1: Update SKILL.md (Main Entry)
- Add "API Contract Validation" section
- Update "Useful Commands" with automated tools
- Add workflow references

### Phase 2: Update RESOURCES.md
- Add contract validation workflow
- Add field naming standards
- Add mismatch detection reference

### Phase 3: Quick Scan
- Check other skills for workflow references
- Update only if found

### Phase 4: Summary
- Document what was updated
- Note any breaking changes

---

## What to Add

### To SKILL.md:
```markdown
## API Contract Validation

After modifying API Resources:
1. Run `./scripts/check-api-integration.sh`
2. Pre-commit hook validates automatically
3. CI/CD validates on PR

See `docs/04-api/integration-check/` for details.
```

### To RESOURCES.md:
```markdown
## Contract Validation

**IMPORTANT:** After modifying Resources:
1. Run `./scripts/check-api-integration.sh`
2. Ensures Resource fields match OpenAPI schema
3. Pre-commit hook enforces validation

**Field Naming:**
- Use `_amount` suffix: `total_amount`, `discount_amount`
- Be consistent across all Resources
```

---

## Execution

Ready to proceed with **Option A (Systematic Update)**?

This will:
- ✅ Update 3-4 key skills
- ✅ Preserve domain knowledge
- ✅ Add workflow automation references
- ✅ Take ~30 minutes
