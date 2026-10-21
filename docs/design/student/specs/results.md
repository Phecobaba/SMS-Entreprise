# Spec: Results list — `screens/results.html`

States: Normal · Latest term locked · No report cards.

## Today
There is no results list page. `PortalOverview` returns the last 5 report cards (`id`, `term`, `compiled_at`, `available`) and the dashboard links `dashboard#results`.

## Changes
- New route `GET /my-results` (name `results.mine.index`) listing the student's `result_cards`, newest first, **paginated** (use `ResultCards::portalSummary` logic without `limit(5)`).
- Add the class (arm label) for each card from `result_cards.class_arm_id`, since a student changes class between terms.
- Each row: View result (`results.mine.show`) when `available`, otherwise a "Payment required" pill. The locked notice appears when any card is locked and its Pay button posts to `payments.mine.begin` for that term's invoice.
- Tab "Results" becomes this page; the dashboard's "All results" links here.
