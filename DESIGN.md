# sunnymonkeys.com design system

Written 2026-11-04 for the redesign. Every public page follows this file; the values live as CSS custom properties in `assets/css/sm.css`. The client portal (`portal/`) keeps its own stylesheet.

## Direction

Paper and ink. A black-and-white editorial site for a small design studio: white pages for projects, black bands for the in-house designer story, condensed poster type, and monochrome drawings instead of stock photos. Nothing is decorated with color; the only brand color is the blue of the monkey mark, kept for the logo file and favicon.

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

- Radius: 6px on buttons, inputs and board chips; 999px on tags; 0 on bands and tables.
- No shadows. Depth comes from paper on ink, rules and spacing.

## Components

- **Buttons**: `.btn` (outline, 1.5px) and `.btn-primary` (filled). Barlow Condensed 600, min height 48px, padding 12px 24px. Hover inverts fill. Focus: 3px outline in the current foreground color, 2px offset. Disabled: 50% opacity. Inside `.ink` the colors flip automatically.
- **Links in text**: underlined, 2px offset; hover removes the underline.
- **Inputs**: 1.5px border, 6px radius, 48px tall, same font as body; labels in Barlow Condensed 600. Error and success messages appear in the `.form-status` line under the form, never by color alone (the text says what happened).
- **Lists of services** (`.rows`): one row per item with a hairline rule above, a drawn icon, the name, one sentence and tags.
- **Packages**: plan headers in a 3-column grid, then one comparison `<table>` (feature rows, plan columns). Below 720px the table becomes feature-first lists.
- **FAQ**: native `<details>` with a styled `<summary>`.
- **Navigation**: sticky white header, mark plus wordmark, text links, quiet "Log in", filled "Book a call". Below 900px a button opens a full-screen ink menu.
- **Footer**: ink band with the mark, three link columns, the newsletter form and the legal line.

## Imagery and motion

- No stock photography. Visuals are inline monochrome SVG drawn for the subject: the designer's week board (hero), the "one designer, five brands" diagram, the immersion timeline, and 24px line icons for services. Everything draws in `currentColor`, so it works on paper and on ink.
- Motion is limited to: the hero board assembling once on load and then looping slowly; diagrams animating once when they enter the viewport; interactive feedback (menu, accordions, hover). No fade-ups on sections. `prefers-reduced-motion` shows every animation's final state.
- The monkey mark is the white PNG used as a CSS mask, so it takes the current text color.
