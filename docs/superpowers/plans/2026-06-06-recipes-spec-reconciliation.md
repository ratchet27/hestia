# Recipes Spec Reconciliation — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `docs/home_erp_specification.md` and `docs/features_overview.md` accurately reflect the shipped recipe feature (issue #62 / D1) — recipes are fully built (backend + integrated frontend), not a roadmap placeholder.

**Architecture:** Pure documentation edits. Add a dedicated `## 13. Recipes` section to the spec (placed after §12 Shopping List), renumber the following sections §13–§18 → §14–§19 and their 10 `§18` cross-references → `§19`, fix the stale §12 / UI / Roadmap recipe claims, and remove the stale "recipe fulfillment UI" line from `features_overview.md`. No code or config changes.

**Tech Stack:** Markdown only. "Tests" are `grep` assertions over the docs (there is no build step).

**Spec:** `docs/superpowers/specs/2026-06-06-recipes-spec-reconciliation-design.md`

**Branch:** `docs/recipes-spec-reconciliation` (already created; design doc already committed).

---

## Reference: verified ground truth (already checked against code)

- Backend `RecipeService` / `RecipeController`: CRUD + `cook` (fulfillment check → consume `consumeOnCook` ingredients → reconcile shopping list, or block with missing list) + `addMissingToShoppingList` (adds shortfall items as `source = RECIPE`).
- Frontend `RecipesPage.tsx` + `api/queries/recipes.ts`: fully integrated (`useRecipes`, `useCookRecipe`, `useAddMissingToShoppingList`, …) — not mock.
- Genuinely unbuilt: meal-planning calendar (stays in roadmap / Postponed).

Do NOT re-verify by editing code. These facts are settled; the work is doc text only.

---

## Task 1: Fix the stale §12 Shopping List recipe note

**Files:**
- Modify: `docs/home_erp_specification.md` (the §12 Shopping List recipe note, currently ~lines 232–233)

- [ ] **Step 1: Replace the "not wired" note**

Use Edit. Old string (exact, including the `>` quote markers):

```markdown
> A "recipe missing items" source exists in the data model (`source = recipe`)
> but is **not wired** — recipes are a roadmap item (§18).
```

New string:

```markdown
> A `recipe` shopping-list source is **active** — a recipe's "add missing"
> action adds shortfall items as `source = recipe`. See §13 Recipes.
```

- [ ] **Step 2: Verify the old claim is gone and the new one is present**

Run:
```bash
grep -n "not wired" docs/home_erp_specification.md; echo "exit=$?"
grep -n "is \*\*active\*\* — a recipe" docs/home_erp_specification.md
```
Expected: first grep prints nothing (exit=1, no "not wired" left); second grep prints the new line.

- [ ] **Step 3: Do NOT commit yet** — Tasks 1–5 form one logical spec change, committed together in Task 6.

---

## Task 2: Remove the stale UI Structure placeholder note and list the Recipes page

**Files:**
- Modify: `docs/home_erp_specification.md` (§15 UI Structure — Pages list ~line 277–282 and placeholder note ~lines 284–285)

- [ ] **Step 1: Add Recipes to the Pages list**

Use Edit. Old string:

```markdown
- Tasks & Chores
- Settings (language switch is live; other controls are placeholders — §18)
```

New string:

```markdown
- Tasks & Chores
- Recipes (CRUD, fulfillment check, cook, add missing to shopping list)
- Settings (language switch is live; other controls are placeholders — §18)
```

- [ ] **Step 2: Delete the placeholder/mock note**

Use Edit. Old string (note plus the surrounding blank lines so no double blank remains):

```markdown
> A Recipes page exists as a **placeholder** running on mock data; it is not
> backend-integrated — §18.

```

New string: empty (delete it entirely — replace with nothing).

> Note: if the Edit tool rejects an empty `new_string`, instead replace the two note lines plus their trailing blank line with a single newline so the `Telegram acts as a lightweight awareness layer…` paragraph follows the Pages list cleanly with one blank line.

- [ ] **Step 3: Verify**

Run:
```bash
grep -n "placeholder.*mock data\|mock data" docs/home_erp_specification.md; echo "exit=$?"
grep -n "^- Recipes (CRUD" docs/home_erp_specification.md
```
Expected: first grep prints nothing (no mock-data recipe claim left); second grep prints the new Pages entry.

---

## Task 3: Remove the built Recipes item from the Roadmap

**Files:**
- Modify: `docs/home_erp_specification.md` (§18 Roadmap, v2 list, ~lines 316–317)

- [ ] **Step 1: Delete the shipped Recipes roadmap line**

Use Edit. Old string:

```markdown
- richer correction UI / undo of stock actions
- **Recipes**: fulfillment check, shopping-list generation, stock consumption
  (wires up the existing `recipe` shopping-list source and the placeholder UI)
- chore reminders and optional product consumption on chore completion
```

New string:

```markdown
- richer correction UI / undo of stock actions
- chore reminders and optional product consumption on chore completion
```

- [ ] **Step 2: Verify**

Run:
```bash
grep -n "fulfillment check, shopping-list generation" docs/home_erp_specification.md; echo "exit=$?"
```
Expected: prints nothing (exit=1) — the built recipe line is gone from the roadmap.

---

## Task 4: Renumber sections §13–§18 → §14–§19 and update cross-references

**Files:**
- Modify: `docs/home_erp_specification.md` (six section headings + the remaining `§18` cross-reference tokens)

Context: a cross-ref audit confirmed the ONLY numbered section referenced in prose is §18 (Roadmap). Tasks 1 and 2 already removed two `§18` references (the §12 note and the deleted placeholder note); the remaining `§18` tokens all refer to the Roadmap and must become `§19`.

- [ ] **Step 1: Rename the six headings (bottom-up to keep edits unambiguous)**

Apply these six Edits (each old → new), one at a time:

```
## 18. Roadmap            → ## 19. Roadmap
## 17. Testing & Quality  → ## 18. Testing & Quality
## 16. Localization       → ## 17. Localization
## 15. UI Structure       → ## 16. UI Structure
## 14. Telegram Integration → ## 15. Telegram Integration
## 13. Tasks & Chores     → ## 14. Tasks & Chores
```

- [ ] **Step 2: Update the remaining `§18` cross-references to `§19`**

Use Edit with `replace_all: true`:
- old_string: `§18`
- new_string: `§19`
- replace_all: true

This is safe: every remaining `§18` token is a Roadmap cross-reference (the renamed heading is `## 19. Roadmap`, which contains no `§18` token).

- [ ] **Step 3: Verify renumber**

Run:
```bash
grep -nE "^## [0-9]+\. " docs/home_erp_specification.md
grep -n "§18" docs/home_erp_specification.md; echo "no-stale-ref-exit=$?"
```
Expected: headings read `## 1.` … `## 12.` then `## 14.` … `## 19.` (a 13 gap — filled by Recipes in Task 5); second grep prints nothing (exit=1, no `§18` left).

---

## Task 5: Insert the new `## 13. Recipes` section

**Files:**
- Modify: `docs/home_erp_specification.md` (insert a new section between §12 Shopping List and §14 Tasks & Chores)

- [ ] **Step 1: Insert the Recipes section before the Tasks & Chores heading**

Use Edit. Old string (the heading renamed in Task 4):

```markdown
## 14. Tasks & Chores
```

New string:

```markdown
## 13. Recipes

Recipes are ingredient sets (product + required count, each with a
`consume on cook` flag) for checking what you can make and restocking for what
you can't.

Features:
- CRUD (name, ingredients)
- **fulfillment check** — required count vs. current stock, per ingredient
- **cook** — allowed only when every ingredient is in stock; consumes stock
  (across locations) for ingredients flagged `consume on cook`, then reconciles
  the shopping list. Blocks with the missing-product list if any ingredient is
  short.
- **add missing to shopping list** — adds each ingredient's shortfall as a
  shopping-list item with `source = recipe` (skips products already listed)

Backend-integrated end to end, with a dedicated Recipes page in the SPA.

---

## 14. Tasks & Chores
```

- [ ] **Step 2: Verify section numbering is now contiguous 1–19**

Run:
```bash
grep -nE "^## [0-9]+\. " docs/home_erp_specification.md
```
Expected: headings read `## 1.` through `## 19.` with no gaps and no duplicates; `## 13. Recipes` is present.

- [ ] **Step 3: Verify no stale recipe claims remain in the spec**

Run:
```bash
grep -niE "not wired|placeholder.*mock|mock data|recipes are a roadmap" docs/home_erp_specification.md; echo "exit=$?"
```
Expected: prints nothing (exit=1).

---

## Task 6: Commit the spec reconciliation

- [ ] **Step 1: Stage only the spec file and commit**

```bash
git add docs/home_erp_specification.md
git commit -s -m "docs(spec): reflect shipped recipe feature; add Recipes section (D1, #62)"
```

- [ ] **Step 2: Verify the commit**

Run: `git show --stat HEAD`
Expected: one file changed — `docs/home_erp_specification.md`.

---

## Task 7: Reconcile `features_overview.md`

**Files:**
- Modify: `docs/features_overview.md` (Postponed (v2+) list, ~line 43)

- [ ] **Step 1: Remove the stale "recipe fulfillment UI" postponed line**

Use Edit. Old string:

```markdown
- undo system
- recipe fulfillment UI
- meal planning calendar
```

New string:

```markdown
- undo system
- meal planning calendar
```

(Do NOT add a recipes entry under "Included in v1" — recipes shipped as a v2 feature, and this doc is a scope guardrail, not an as-built inventory. "meal planning calendar" stays — genuinely unbuilt.)

- [ ] **Step 2: Verify**

Run:
```bash
grep -n "recipe fulfillment UI" docs/features_overview.md; echo "exit=$?"
grep -n "meal planning calendar" docs/features_overview.md
```
Expected: first grep prints nothing (exit=1); second grep still prints the meal-planning line (untouched).

- [ ] **Step 3: Commit**

```bash
git add docs/features_overview.md
git commit -s -m "docs(features): drop shipped recipe feature from postponed list (D1, #62)"
```

---

## Task 8: Final consistency sweep

- [ ] **Step 1: Confirm spec ↔ features_overview agree and no stale claims survive**

Run:
```bash
grep -niE "not wired|mock data|placeholder.*mock" docs/home_erp_specification.md; echo "spec-clean-exit=$?"
grep -n "recipe fulfillment UI" docs/features_overview.md; echo "features-clean-exit=$?"
grep -nE "^## [0-9]+\. " docs/home_erp_specification.md
grep -n "§18" docs/home_erp_specification.md; echo "no-stale-section-ref-exit=$?"
```
Expected: both "clean" exits are 1 (no matches); headings contiguous `## 1.`–`## 19.`; no `§18` left.

- [ ] **Step 2: Review the full diff against the spec design doc**

Run: `git diff master --stat`
Expected: only `docs/home_erp_specification.md`, `docs/features_overview.md`, and the design/plan docs under `docs/superpowers/` changed. No code/config files.

- [ ] **Step 3 (optional): Open a PR**

```bash
gh pr create --fill --base master
```
Reference issue #62 in the PR body (e.g. "Closes #62").

---

## Done criteria (maps to spec acceptance)

- [ ] New `## 13. Recipes` section accurately describes the shipped feature.
- [ ] §12 source note, UI Structure, and Roadmap reflect the built feature; no "not wired" / "mock data" / "placeholder" recipe claims remain.
- [ ] Section numbering contiguous 1–19; all `§N` cross-references resolve (no `§18`).
- [ ] `features_overview.md` no longer lists recipe fulfillment as postponed; no contradiction with the spec.
- [ ] No code or config changes.
