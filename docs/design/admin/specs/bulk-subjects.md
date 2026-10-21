# Spec: Bulk subject assignment — `screens/bulk-subjects.html`

States: 1 Level · 2 Department · 3 Classes · 4 Subjects · 5 Review · Result report · Nothing to add · School with no departments · Error (no class ticked) · Adding · No permission. **Head version (`screens/head-bulk-subjects.html`)**: the same states plus **No section assigned (locked)**.

## Who can open
Proposal: `manage_academics` for the Admin (any section). **Decided (owner, 2026-10-18): a head runs it too, for their own section only.** Codex names a section-scoped permission (log it). The **server** refuses any class outside the actor's section.

## Today
Subjects are attached to a class one at a time (`academics.class-subjects.store`, `class_subjects`: unique class/subject).

## Design (D3)
Wizard: **level**, then **department** (only when the school has departments and the level's classes carry them), then **classes** (all ticked, each can be unticked), then **subjects**, then a **review** with "Will add 12 · Already there, skipped 3 · Classes 3" and a per-class table, then confirm and a **result report** (Added / Skipped / Failed).
- Idempotent: running it twice adds nothing new ("Nothing to add" state).
- This action **only adds**. It never removes a subject and never touches results. Removing a class-subject assignment (separate screen) must never delete results already entered.
- Phones show "Step 3 of 5: Classes" instead of the stepper.

## Dependent fields
Department step: only when departments exist. Classes list: only classes in the chosen level (and department). Subjects step: needs at least one class ticked. Continue is disabled with an inline message when nothing is ticked.

## Server rules
The server recomputes the pairs, applies only missing `class_subjects` rows, and returns the counts shown in the preview. Hidden or tampered fields are ignored.

## Tests (batch 4)
Run twice adds nothing; the preview counts equal the applied counts; no result rows change.

## Decided (owner, 2026-10-18)
Heads write within their own section only; the Admin acts in any section. This replaces the 2026-10-11 "Admin only". The head version is the Admin screen with the **section fixed**: no section switcher, the section name in the heading, only that section's levels and classes listed, and the locked state "No section assigned".
