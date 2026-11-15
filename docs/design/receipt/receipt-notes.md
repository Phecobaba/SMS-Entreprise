# Receipt design notes

Files: four receipt variants (application, tuition full, tuition part, manual cash), `receipt-email.html`, `receipt-screens.html` (mock pieces), one shared `receipt.css`. Sample data only; names and amounts are invented. The logo is the landing-page JPG at `../landing/assets/brand/`; when the SVG/PNG arrives, change the one `<img>` source (the PDF needs PNG or JPG; dompdf SVG support is limited).

## Fields (in order of importance)
School name, logo, address (phone and email only when set in settings; the line simply drops out when missing). Title "Receipt" and PAID box. Receipt number (RCT-YYYY-000000). Date and time (Africa/Lagos, shown with WAT). Received from. For (child full name and class; application shows "Application for [Level]"). Payment lines (description, amount). Total paid (large, with the naira sign, two decimals). Payment method: "Paystack (online)" plus the reference in small type, or "Cash" / "Bank transfer" plus "Received by [staff name]". Tuition only: "Balance remaining on this invoice: X" or "Fully paid". Footer line. No card details anywhere.

## dompdf cautions
- Tables and plain blocks only. No flex, grid, CSS variables, calc(), shadows, gradients or animation in `receipt.css`.
- **Naira sign:** the core fonts (Helvetica, Times) do not contain the naira glyph. The CSS uses "DejaVu Sans" and "DejaVu Serif", which do. Keep DejaVu for every amount, and write the sign as `&#8358;`.
- Image path: set dompdf's chroot (and base path) so `../landing/...` resolves, or pass the logo as an absolute path or data URI from the template.
- PAID is a double-bordered box with the word PAID, so it reads in black and white. It is not rotated (dompdf rotation is unreliable).
- Amount row uses a heavy rule above and below, again so the total stands out without colour.
- Long invoices: the payment table can grow; rows do not split, so a very long invoice may run to a second page, which is fine.
- `receipt-screens.html` has its own style block (flex, hover, focus) because it is a screen mock, not PDF input.

## Checked
Each receipt prints to exactly one A4 page in Chromium. The email (600px max, inline styles, tables) has no horizontal overflow at 390px. Not yet run through dompdf itself or a real mail client: please render one sample through dompdf first and compare. Outlook ignores the rounded corners on the email button; it stays readable.

## Not included
The surrounding pages are not redesigned, as requested.
