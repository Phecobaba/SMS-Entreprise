# Landing design notes (Home, Apply, Status, Privacy)

Each HTML file is responsive: one file covers 1280px and 390px (single breakpoint at 820px). Mobile first. No framework. One shared CSS file and one JS file (`assets/js/home.js`, about 1.5 KB, Home only).

## Screens
index, index-closed, apply, apply-nofee, apply-errors, apply-username-taken, apply-closed, apply-status-{awaiting,confirming,registered,expired,closed}, privacy. Links between files are relative for preview; Codex should swap them for routes (/apply, /apply/status, /privacy, /login).

## Tokens
Colours: teal #0E4A47, deep teal #0A3836, cream #FBF6EA, cream-2 #F3EAD3, gold #E3B853 (text on cream: #7A5A12), navy #1C2B4D, coral #E07A5F (accent, unused so far). Fonts: Georgia serif headlines, system sans body (no web fonts, for mobile data). Body 17px/1.6; h1 clamp 2 to 3.4rem. Radius 14px. Tap targets 44 to 48px. Page width 1160px, form column 640px.

## Motion (Home only; Apply, Status, Privacy have none)
| Animation | Trigger | Duration, easing | Reduced motion |
|---|---|---|---|
| Section and card reveal (fade and rise 18px) | scrolls 15% into view | 0.5s, ease | no movement, instant |
| Pine-needle line art grows (scaleY, staggered 60ms) | scrolls into view | 0.5s | shown complete |
| Hero crossfade, 2 photos (building first, assembly second) | every 6s; paused when tab hidden or by the Pause button | 1.2s ambient, ease | starts paused |
| Hero parallax (transform only) | scroll | rAF-throttled | off |
| Mosaic photo zoom 1.06 | hover or focus | 0.5s | none |
| Buttons lift 2px | hover, press | 0.2s | none |
| Header shadow fades in | scroll past 40px | 0.3s opacity | none |
Without JavaScript everything is visible; the first hero photo shows; the Pause button is hidden.

## Build notes for Blade
Hero needs the original at 1920px or wider (current 1080px is a stand-in). Preload is set. All photos have width and height and lazy loading except the hero. Page weight estimate: about 1.1 MB on Home. Status pages should poll or meta-refresh (the "refreshes by itself" cue is text only). Apply fee line and closed states are driven by settings, shown here as separate files.

## Search and sharing tags (Home, in the page head)
Title and description are from `landing_copy_draft.md`. `og:title`, `og:description`, `og:image` (the building exterior; needs an absolute URL, currently `https://[PLACEHOLDER-domain]/`) and `twitter:card` are set. Favicon is `assets/brand/favicon-temp.png`, cropped from the 447px JPG; replace it when the emblem SVG/PNG arrives. Other pages have a title only.

## Logo slots
Header and footer use `<img class="logo" ...>`. When the transparent SVG arrives, change the `src` and add class `svg` (removes the rounded dark-square treatment). One constant in `gen.py` is not needed by Codex; edit the two `<img class="logo">` tags.

## Review fixes (2026-11-13)
Header fits on one line at 390px (brand shows logo only below 420px). All links are at least 44px tall (checked in Chromium on every screen). Leadership text contrast fixed; the section has class `hideable` and an id (`#leadership`): hide it at launch if portraits are not ready. Mosaic is now one big plus four small and fills the grid at 390px and 1280px. Division tags removed. Motto is cream with a stronger gradient: worst-case contrast 5.2:1 on the building photo and 4.7:1 on the assembly photo. Copy from the draft is in place; only [SCHOOL TO SUPPLY] items still show a marker. [CONFIRM] lines in the copy are used as written and still need the school's sign-off before launch. The "2 Pay" step is hidden when no fee applies (steps become 1 Apply, 2 Exam day). Directions button links to Google Maps, no embedded map. Alt text now describes what each photo shows (checked against the images); owner to verify.

## Checked in Chromium
No horizontal overflow on Home, Apply (all variants), Status and Privacy at 390px and 1280px. Crossfade runs and the Pause button stops it. The second hero photo loads after the page loads. Weight on Home with the current 1080px photos: about 0.5 MB above the fold (HTML, CSS, JS, logo, hero) and about 1.8 MB if the whole page is scrolled (all photos). A 1920px hero original will add roughly 150 to 300 KB; to stay under 1.5 MB, serve the mosaic and division photos resized to about 600px wide.

## Needs owner
Emblem is the 447px reference image until the SVG arrives. Classroom photo sections are provisional; I only used 21 (Primary) and 23 (Secondary).

## Placeholders to supply
Leadership portraits, names and titles (or hide the section); key admission dates; vans' routes (optional); phone, email, WhatsApp, hours; application fee amount; when applications reopen; privacy contact email, retention for non-admitted children, last-updated date; site domain for the social image.

## Not done
Sports and Transport blocks, further desktop spacing polish, extra animation ideas (as agreed).
