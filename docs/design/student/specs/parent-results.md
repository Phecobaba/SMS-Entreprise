# Spec: Parent results list — `screens/parent-results.html`

States: Normal · Latest term locked · No report cards.

## Who can open
Proposal: signed-in user with a linked child. Only that child's cards.

## Today
No list page. `results.mine.show` and `results.mine.pdf` take a card id; `ResultPaymentAccess::authorize` gates by payment and ownership. **Test required:** a parent cannot open a card that belongs to a child who is not linked to them by changing the card id or `?student=`.

## Design
Same table as the student version (term, class that term, compiled date, View result or "Payment required"). Locked row: pill plus a Pay button for **that term's** invoice. Paginated. Class is the class **at that term** (`result_cards.class_arm_id`), not the child's current class.

## NEW
Route for the list (name to be chosen by Codex, e.g. `results.parent.index`), ward-scoped.
