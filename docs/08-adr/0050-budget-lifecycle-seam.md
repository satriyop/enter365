---
adr: "0050"
title: "Budget lifecycle behind the service seam"
status: accepted
date: 2026-08-17
deciders: [Engineering]
tags: [accounting, architecture, seam]
related_adrs: [0003]
related_modules: [accounting]
impact: medium
supersedes: null
superseded_by: null
---

# ADR-0050: Budget lifecycle behind the service seam

## AI Agent Quick Reference

**Use this ADR when:**
- Approving, reopening, closing, copying, or deleting a budget
- Adding, updating, or deleting budget lines
- Changing a draft budget header

**Key takeaway:** All budget writes go through `BudgetServiceInterface`. HTTP only authorizes and maps. The `Budget` model keeps read-only status checks (`isEditable` / `isApproved` / `isClosed`) and must not mutate status.

## Context

Budget create/lines/reports lived on `BudgetService`, while approve/reopen/close/destroy and draft-only / uniqueness rules lived in `BudgetController` and model mutators that returned `false` and used `auth()->id()`.

## Decision

Deepen the existing `BudgetServiceInterface`. Controllers inject the interface. Failures throw `BusinessRuleException` with Indonesian messages. Approver comes from `getUserId()`.

## Consequences

- Lifecycle can be tested without HTTP.
- API rule failures that used HTTP 422 now render as domain 409.
- No `BudgetStateMachine`; three statuses do not justify another machine.
