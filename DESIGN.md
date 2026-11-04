# sunnymonkeys.com design system

Written 2026-11-04 for the redesign; sky accent added the same day (version 2), then the sun as a second color, more motion and shorter copy (version 3). Every public page follows this file; the values live as CSS custom properties in `assets/css/sm.css`. The client portal (`portal/`) keeps its own stylesheet.

## Direction

Paper and ink, with two colors. A black-and-white editorial site for a small design studio: white pages for projects, black bands for the in-house designer story, condensed poster type, and drawings instead of stock photos. Sky blue is the blue of the monkey mark (`#2988FC`, sampled from the logo), lightened to `#7FC3FF` in a gradient: it marks the in-house side and anything active or finished (the mark, primary buttons, step numbers, checks, the weekly review on the board, filled timeline bars, focus, the glow on black bands). Sun (`#FFB42B` to `#FFD36A`) is the warm second color for the Build side: the rule above the Build half and above packages, marquee dots, row hovers, the blob behind the hero opposite the sky one. Sky to sun makes the horizon line under the header and above the footer. Neither color ever carries text. The brand sheet at `/brand` (`brand.html`, not indexed, not in the menu) shows all of this on one page.

Compared before deciding (home pages, November 2026): designme.agency, designjoy.co, awesomic.com, superside.com, penji.co, manypixels.co, halo-lab.com, ramotion.com, eleken.co, clay.global, growmodo.com and hirewithnear.com. Nine of the twelve are light sites with one big grotesque headline, one accent color, stat tiles and a pricing table pushed to another page. The dark ones lean on 3D renders or a brand color. A strict black-and-white site with condensed type and drawn, animated boards stands apart from all of them, and it matches the studio's own brand rules.

## Color

| Token | Value | Role |
|---|---|---|
| `--paper` | `#FFFFFF` | Page background |
| `--ink` | `#000000` | Text, buttons, dark bands |
| `--grey-1` | `#F2F2F2` | Quiet surfaces (board backgrounds, table header) |
| `--grey-2` | `#D6D6D6` | Rules and borders on paper |
| `--grey-3` | `#6B6B6B` | Secondary text on paper (5.7:1) |
| `--grey-4` | `#A3A3A3` | Secondary text on ink (5.6:1) |
| `--grey-5` | `#3A3A3A` | Rules and borders on ink |
| `--blue` | `#2988FC` | The mark, step numbers, finished parts of drawings, focus outline, nav underline, input focus border. Large text or shapes only (3.5:1 on white) |
| `--sky` | `#7FC3FF` | Light end of the gradient |
| `--sky-wash` | `#E4F1FF` | Hero wash, table header, selection, the 404 code |
| `--grad` | blue to sky, 135deg | Weekly review bar, filled timeline bars, in-house rules, facts bars. Shapes only, never text |
| `--grad-btn` | blue, lighter blue, blue | Primary buttons (200% wide, shifts on hover) |
| `--sun` | `#FFB42B` | Marquee dots, Build side |
| `--sun-light` | `#FFD36A` | Light end of the sun gradient |
| `--sun-wash` | `#FFF3DB` | Hover on service rows and table rows |
| `--grad-sun` | sun to sun light, 135deg | Rule above the Build half and above each package |
| `--grad-h` | sky to sun, 90deg | The horizon: header line on scroll, footer top line. Thin lines only |
| `--glow`, `--glow-sun` | radial blue top right, radial sun bottom left | Black bands, the footer and the phone menu |

Semantic variables `--bg`, `--fg`, `--fg-muted`, `--rule` and `--surface` point at these and flip inside any element with the class `ink`. Components only use the semantic names. Text contrast is at least 4.5:1 everywhere; large display type on grey is at least 3:1.

## Typography

- Display: **Barlow Condensed** 600 and 700. Headlines are uppercase, tight (line-height 0.95 to 1.0, letter-spacing -0.01em). Also used for buttons, navigation, table headers and prices.
- Body: **Barlow** 400 and 500, 17px, line-height 1.6, measure 60 to 70 characters (`max-width: 66ch`). Italic 400 for one-line quotes.
- Loaded from Google Fonts with `display=swap`.

| Step | Size | Use |
|---|---|---|
| `--t-display` | clamp(3.25rem, 1rem + 7.5vw, 7.5rem) | Page headline (h1) |
| `--t-1` | clamp(2.25rem, 1.25rem + 3.5vw, 4.25rem) | Section headline (h2) |
| `--t-2` | clamp(1.625rem, 1.3rem + 1vw, 2rem) | Card and row titles (h3) |
| `--t-3` | 1.375rem | Small titles (h4), nav, buttons |
| `--t-lead` | clamp(1.125rem, 1rem + 0.5vw, 1.375rem) | Lead paragraphs |
| `--t-body` | 1.0625rem | Body |
| `--t-small` | 0.9375rem | Table cells, captions, footer |
| `--t-fine` | 0.8125rem | Legal fine print (never smaller) |

No eyebrow labels above headings, no all-caps labels, no single accented word in a headline. The headline itself carries the message.

## Spacing and layout

- 8px scale: `--s-1` 0.5rem, `--s-2` 1rem, `--s-3` 1.5rem, `--s-4` 2rem, `--s-5` 3rem, `--s-6` 4rem, `--s-7` 6rem.
- Content width 1200px (`--wrap`), side gutter `clamp(16px, 4vw, 48px)`.
- Sections: `padding-block: clamp(4rem, 8vw, 7.5rem)`. Sections that are lists start with a 1px rule.
- Everything is left-aligned. Two-column layouts (hero, split, process) collapse to one column below 900px; tables collapse to feature-first lists below 720px.

## Shape and depth

- Radius: 6px on buttons, inputs and board chips; 12px on tiles and swatches; 999px on tags; 0 on bands and tables.
- No shadows. Depth comes from paper on ink, rules and spacing.

## Components

- **Buttons**: `.btn` (outline, 1.5px) and `.btn-primary` (sky gradient, white text). Barlow Condensed 600, min height 48px, padding 12px 24px. Secondary hover inverts fill; primary hover shifts the gradient and lifts 1px. Focus: 3px blue outline, 2px offset. Disabled: 50% opacity. Inside `.ink` the colors flip automatically.
- **Links in text**: underlined, 2px offset; hover turns the underline blue.
- **Inputs**: 1.5px border, 6px radius, 48px tall, same font as body; labels in Barlow Condensed 600. Error and success messages appear in the `.form-status` line under the form, never by color alone (the text says what happened).
- **Tiles** (`.tiles`): linked cards with a 12px radius, a blue drawn icon, a title and one line; they lift and turn sky on hover. Used where a list would be too much text.
- **Lists of services** (`.rows`): one row per item with a hairline rule above, a blue drawn icon, the name, one line and tags; rows warm up (sun wash) on hover.
- **Marquee** (`.marquee`): a slow strip of what we make, between the hero and the next section, with sun and sky dots as separators. Pauses on hover; wraps as a static list under reduced motion.
- **Packages**: plan headers in a 3-column grid with a 3px gradient rule on top, then one comparison `<table>` (sky-wash header) (feature rows, plan columns). Below 720px the table becomes feature-first lists.
- **FAQ**: native `<details>` with a styled `<summary>`.
- **Navigation**: sticky white header, blue mark plus black wordmark, text links (blue underline on the current page), quiet "Log in", filled "Book a call". Below 900px a button opens a full-screen ink menu with the glow.
- **Footer**: ink band with the mark, three link columns, the newsletter form and the legal line.

## Imagery and motion

- No stock photography. Visuals are inline SVG drawn for the subject: the designer's week board (hero), the "one designer, five brands" diagram, the immersion timeline, and 24px line icons for services. Lines draw in `currentColor`, so they work on paper and on ink; finished checks, the in-house connection and the pulse are blue, and the review bar and filled bars use the gradient (`url(#sm-grad)`, a `<linearGradient>` placed at the top of every page's body).
- Motion: on load the headline rises and the board assembles, while two soft blobs drift behind the hero. On scroll, grids, lists and diagrams come in once with a short stagger (`data-reveal` and `data-reveal="stagger"`, class `is-visible` added by `sm.js`), icons draw themselves (`pathLength="1"` and a dash offset), the timeline fills, and a horizon line draws under the header. Always on: the marquee, the juggling diagram and the in-house pulse. Feedback: button gradient shift and lift, tiles lift, rows warm up, accordions open smoothly, the mark tilts on hover, menu links stagger in. Never parallax or auto-playing video. `prefers-reduced-motion` switches all of it off and shows final states.
- The monkey mark is the white PNG used as a CSS mask, so it takes the current text color.

## Copy

- Short. Every section says one thing and the headline carries it; a lead is one or two sentences; a tile or fact gets one line. Long explanations belong on the in-house page's FAQ or on the call, not on the home page.
