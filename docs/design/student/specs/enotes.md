# Spec: E-Notes — `screens/enotes.html`

States: Normal · No notes.

## Today
Staff only. `ENoteService::listing/accessible` both begin with `authorize('upload_enotes')`; there is no student path and `ENoteController::download` uses the same check.

## Changes (new access path, trust-sensitive: test required)
- `GET /my-enotes`: notes where `class_id` = the student's class (derive from `class_arm_id`), optional `?subject=`. Paginated 20. Subject chips come from the page's subjects.
- `GET /my-enotes/{note}/download`: allowed only when the note's `class_id` matches the student's class **and** the file exists. Never expose other classes' notes. Add a feature test.
- `link` notes open in a new tab with `rel="noopener noreferrer"`, and say so for screen readers. File notes download from the private `local` disk as today.
- No file size or type metadata exists; the design shows only title, subject and age.
