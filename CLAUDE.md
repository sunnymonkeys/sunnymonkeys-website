# sunnymonkeys.com

Website for Sunny Monkeys LLC, a small creative design studio. The owner is Marco. He's learning how the site works, so explain changes in plain language as you go.

This file is technical only. The repo is public, so keep business details out of it.

## How the site is built

- A static HTML site with no framework and no build step, plus a small PHP client portal. Redesigned on 2026-11-04; the purchased template it used before (jQuery, Bootstrap, GSAP, template images) is gone from the repo.
- Public pages sit in the repo root: `index.html`, `services.html`, `in-house-designer.html`, `about.html`, `contact.html`, `privacy-policy.html`, `terms.html`, `error.html` (the 404 page).
- One stylesheet, `assets/css/sm.css`, and one script, `assets/js/sm.js` (menu, forms, the timeline reveal). No libraries. Fonts (Barlow Condensed and Barlow) come from Google Fonts.
- `DESIGN.md` at the root is the design system: colors, type scale, spacing, components and the rules for motion and imagery. Read it before changing the look of anything. Its values are the CSS custom properties at the top of `sm.css`.
- `portal/` is a PHP + MySQL client portal: login, client dashboard, documents, and admin (`portal/admin/`). It has its own stylesheet (`portal/assets/portal.css`) and only uses `/assets/images/logo-sunny-monkeys-white.png` and `/assets/images/fav.png` from the public assets. Its config files `portal/config/db.php` and `portal/config/mail.php` exist only on the server, never in Git (see `.gitignore`).

## Conventions and gotchas

- **Root font size is the browser default (16px), so `1rem = 16px`.** The old template's 10px rule is gone.
- **Header and footer are repeated in every page** (there are no includes). When you change the menu, the footer or the `<head>`, change all eight files the same way.
- **Drawings are inline SVG**, drawn in `currentColor` so they work on white and on black: the designer's week board (home and in-house hero), the "one designer, five brands" diagram, the immersion timeline, and the 24px service icons. Each board and diagram exists twice: a desktop drawing (`.board-d`, `.diagram-d`) and a phone drawing (`.board-m`, `.diagram-m`) that CSS swaps below 600px, because text inside an SVG scales with it and became unreadable on phones.
- **Motion:** the hero entrance and the board loop are CSS animations; the timeline fills when it scrolls into view (`sm.js` adds `is-visible`). Everything respects `prefers-reduced-motion`. Don't add fade-ups to sections.
- **Cache busting:** browsers and Cloudflare cache CSS and JS for a month (see `.htaccess`), so bump the `?v=` on the `sm.css` and `sm.js` links in all pages whenever you change them (currently `?v=2`). HTML is never cached.
- **Clean URLs:** `.htaccess` serves `/about` from `about.html` and 301-redirects any `*.html` URL to the clean one. Link with clean absolute paths (`/about`, `/services`, `/contact?package=growth`). A new page `name.html` is automatically available at `/name`; add it to `sitemap.xml`.
- **Writing style:** no em dashes in site copy (use commas, colons or periods). Headlines are uppercase Barlow Condensed; no eyebrow labels, no accent colors. Client-facing copy in English.
- Every page has its own `<title>`, meta description, canonical URL, Open Graph tags and the shared social image `assets/images/og-sunny-monkeys.png`. Keep them when you add pages.
- Local preview: any static server works, but clean URLs need one that maps `/about` to `about.html`.

## Deploying

- **Push to `main` and the site deploys itself.** `.github/workflows/deploy.yml` runs `php -l` on every PHP file (nothing deploys if one is broken), then calls the cPanel API: `VersionControl/update` pulls the repo on the server, and `VersionControlDeployment/create` runs `.cpanel.yml`, which copies the repo into `public_html`. It takes about 15 seconds after the job starts.
- **Always check the change on the live site afterwards,** e.g. `curl -s "https://sunnymonkeys.com/page?nc=$RANDOM"`. GitHub Actions has had outages where jobs never started.
- `.cpanel.yml` copies files but never deletes them on the server. Removing a file from the repo leaves it live until someone removes it on the server by hand (only Marco can do that, from his Mac or cPanel). The old template's files (`assets/css/style.css`, `assets/js/main.js`, `assets/images/*` and so on) are still there for that reason; nothing links to them.
- `.cpanel.yml` strips `.git`, `.github`, `.cpanel.yml`, `.gitignore`, `README.md`, `CLAUDE.md` and `DESIGN.md` out of `public_html`.
- Commit author: `Marco Mendoza <contact@sunnymonkeys.com>`.

## Contact form and newsletter

- Both forms post to `portal/inquiry.php` from `assets/js/sm.js` (plain `fetch`, form-encoded, plus `form=contact|newsletter` and `started=<page load time>`). The reply text is shown in the `.form-status` line under the form.
- Field names and option values are what the PHP validates (`INQUIRY_SERVICES` and `INQUIRY_START_OPTIONS` in `portal/lib/inquiries.php`). Choosing an In-House Designer option on the contact form shows a required "when would you like your designer to start" select (`#cf-start`); `inquiry.php` stores the answer as the first line of the message, so the `inquiries` table is unchanged. Buttons link to `/contact?package=<value>` to preselect an option.
- Every submission is saved to the MySQL table `inquiries` first (created automatically by `portal/lib/inquiries.php`). Contact messages are then emailed through the Titan mailbox by `portal/lib/mailer.php`, a small SMTP client with no libraries, using `portal/config/mail.php`.
- Spam handling: a hidden `website` trap field, submissions under 3 seconds after page load, and at most 5 submissions per hour per connection. Suspected spam is saved and flagged, never dropped.
- Admins see everything at `/portal/admin/inquiries.php` (messages, newsletter sign-ups, spam). If an email failed, it shows "Email not sent" with the reason.
- `portal/.htaccess` blocks direct access to `config/*.php` secrets and to `portal/lib/`.

## Open items (as of 2026-11-04)

- [ ] Marco sends a test message from /contact and confirms the email reaches contact@sunnymonkeys.com. If it doesn't arrive, the likely cause is HostGator blocking outgoing SMTP; the fallback is an HTTPS email API.
- [ ] Marco reviews the founder note on /about (written from the studio's positioning, not from an interview) and the "Where we are" facts.
- [ ] Marco resets the portal admin password himself (bcrypt hash into the `admins` table via phpMyAdmin).
- [ ] Make the repo private. This needs a read-only GitHub token in the server's git remote URL first (Marco does it in cPanel), then Marco flips the visibility, then test a deploy.
- [ ] Real numbers for the site, if Marco wants them (projects, clients). The made-up stats band is gone; the home page states commitments instead.
- [ ] Old template files and `service-1.html` are still on the server; harmless, but Marco can delete them from cPanel once he has a backup.
