---
name: Ajaklah News
description: A demo Malaysian news portal and its share-analytics admin, light by default, in the layout language of local portals such as mStar.
colors:
  brand: "#0A33FF"
  brand-strong: "#0626C7"
  brand-soft: "#EBEFFF"
  hot: "#C2185B"
  live: "#E5397F"
  bg: "#FFFFFF"
  surface: "#FFFFFF"
  surface-2: "#F5F6F9"
  surface-3: "#ECEEF3"
  ink: "#0F1222"
  ink-soft: "#474D62"
  ink-faint: "#676D82"
  line: "#E4E6EC"
  line-strong: "#CDD1DB"
  topbar: "#0F1222"
  dark-bg: "#0D0F17"
  dark-surface: "#151826"
  dark-ink: "#F2F3F8"
  dark-brand-ink: "#8EA4FF"
typography:
  display:
    fontFamily: "\"Poppins\", \"Segoe UI\", system-ui, sans-serif"
    fontSize: "clamp(1.875rem, 4.4vw, 3rem)"
    fontWeight: 700
    lineHeight: 1.15
    letterSpacing: "-0.02em"
  section:
    fontFamily: "\"Poppins\", \"Segoe UI\", system-ui, sans-serif"
    fontSize: "clamp(1.375rem, 2.4vw, 1.75rem)"
    fontWeight: 800
    lineHeight: 1.2
    letterSpacing: "-0.02em"
  card-title:
    fontFamily: "\"Poppins\", \"Segoe UI\", system-ui, sans-serif"
    fontSize: "1.0625rem"
    fontWeight: 700
    lineHeight: 1.35
  body:
    fontFamily: "\"Albert Sans\", system-ui, -apple-system, \"Segoe UI\", sans-serif"
    fontSize: "1.0625rem"
    fontWeight: 400
    lineHeight: 1.55
  reading:
    fontFamily: "\"Albert Sans\", system-ui, -apple-system, \"Segoe UI\", sans-serif"
    fontSize: "1.1875rem"
    fontWeight: 400
    lineHeight: 1.75
  label:
    fontFamily: "\"Albert Sans\", system-ui, -apple-system, \"Segoe UI\", sans-serif"
    fontSize: "0.6875rem"
    fontWeight: 600
    letterSpacing: "0.08em"
rounded:
  sm: "6px"
  md: "10px"
  pill: "999px"
---

# Design System: Ajaklah News

## Overview

**Creative North Star: "The local front page"**

The public site reads like a Malaysian news portal (the reference is mStar): a thin dark utility bar, a centred wordmark masthead, a bold section nav underlined in brand blue, then an editor's pick, a live "Most shared" ranking and a grid of story cards. The admin is a calm analytics workspace on a light grey ground, with white cards, KPI tiles and charts. Both are light by default; dark mode swaps the same tokens.

**Key Characteristics:**
- White pages, ink-black headlines in Poppins, body copy in Albert Sans.
- One brand colour (cobalt `#0A33FF`) for the wordmark, nav rule, chips, links and primary buttons. Rose (`#C2185B`) marks share counts and the live lamp only.
- Platform brand colours appear only on share buttons, filter chips, mix bars and chart series.
- Small radii (6px images and buttons, 10px admin cards); square category chips.

## Colors

Tokens live in `public/assets/css/theme.css` on `:root` (light) and `[data-theme="dark"]`. The admin maps Bootstrap's `--bs-*` variables onto them in `admin.css`, so Bootstrap components follow the theme too.

- **Brand** `#0A33FF`: wordmark "News", section rule, chips, primary buttons, rank numbers. On dark it is used as `#8EA4FF` for text.
- **Hot** `#C2185B`: share counts in story meta.
- **Neutrals:** ink `#0F1222`, ink-soft `#474D62`, ink-faint `#676D82`, lines `#E4E6EC` / `#CDD1DB`, surfaces `#FFFFFF` / `#F5F6F9` / `#ECEEF3`.
- **Topbar/footer** `#0F1222` in both themes.

## Typography

- **Poppins** 600/700/800 (self-hosted) for the wordmark, headlines, section titles, card titles, nav and KPI values.
- **Albert Sans** 400–700 (self-hosted) for body, meta, forms and tables.
- Section titles (Poppins 800) carry a 2.5rem × 3px brand underline (`.block-title`).
- Numbers in KPIs and the platform mix use tabular figures.

## Layout

- **Site:** topbar → masthead → sticky section nav (3px brand rule) → content in Bootstrap's `.container`.
- **Front page:** `Editor's picks` lead (text panel on a dotted ground overlapping a 4:3 cover) beside a `Most shared` aside (ranked list with thumbnails, collapses under the lead below 992px); then a 3/2/1-column card grid; then a `Pass it on` share panel.
- **Sections:** `?section=food` narrows the front page to one section; the nav marks it current.
- **Article** (after mStar's story page): a strip of the latest stories under the nav (current one on a dotted ground; scrolls sideways below 992px); then main column + sticky "Most shared" sidebar. The headline sits in a box over a dotted band, with a dateline and filled square share buttons (`share-bar--solid`); the cover has a caption box (photo credit) lifted over its lower edge; the body opens with the standfirst as a pull-quote and a bold capital lead-in word; then the share panel and previous/next article cards.
- **Admin:** white top nav with the wordmark and an "Analytics" pill; page header; filter card; four KPI tiles (4/2/1 columns); timeline (8 cols) beside the platform mix (4 cols); top pages beside latest shares; page breakdown table.
- **Sign in:** split screen. Left, the front-page cover (KL at dusk) under an ink gradient carries the wordmark, a brand-ruled "Share analytics" kicker, the line "What got shared, where, and when." and the photo credit. Right, the form on a plain surface pane with the setup hint and a back link. Below 992px the photo becomes an 11rem band above the form.
- **Mobile:** the section nav scrolls sideways with a fade; cards stack; the article gets a fixed share dock that parks while the inline share panel is visible.

## Motion

- Page-to-page navigation uses cross-document View Transitions (`@view-transition` in `theme.css`): the topbar, masthead, section nav and admin nav are named so they stay still; the page body fades out and rises in (0.38s ease-out).
- `transitions.js` names a story's cover only for the length of one transition (`pageswap` / `pagereveal`), so the front-page card image morphs into the article hero.
- Speculation rules prerender same-origin links on hover, so most navigations are instant. The CSP allows them with `'inline-speculation-rules'` only.
- Admin cards rise in once on load, staggered by 40ms.
- `prefers-reduced-motion` turns all of it off.

## Elevation & Depth

- `--shadow-sm` on admin cards; `--shadow-md` on the lead text panel. The sign-in form sits flat on its pane, with no card.
- Overlaps (lead panel over cover, card text box over image) rely on grid paint order, not z-index, so the stretched link covers the whole card.

## Components

- **Chip** (`.chip`): square, brand fill, white uppercase 11px label.
- **Story card** (`templates/site/card.php`): 16:10 image, text box lifted 1.75rem over its lower edge and inset from the right.
- **Share button** (`.gel`): white, 1px border, brand-coloured icon; hover tints with the platform colour; a click plays a short bloom. Icon-only below 576px.
- **KPI tile** (`.kpi`): label with icon, Poppins value, note; the total shows a green/red change pill against the previous period.
- **Platform mix** (`.mix`): 100% split bar, then platforms ranked by shares with a bar scaled to the leader; click a row to show only that platform.

## Do's and Don'ts

### Do:
- Keep the brand blue for structure (nav rule, chips, links, primary actions) and platform colours for platform data.
- Use Poppins for anything a reader scans as a heading and Albert Sans for anything read or operated.
- Test both themes and 390px width after any change.

### Don't:
- Don't add a second accent colour to the public site.
- Don't put `position` or `z-index` on the overlapping card panels; it breaks the whole-card link.
- Don't hard-code the site name in templates; use `wordmark()`.
