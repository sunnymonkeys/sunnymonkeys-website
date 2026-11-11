# sunnymonkeys.com

Website for Sunny Monkeys LLC, a small creative design studio. The owner is Marco. He's learning how the site works, so explain changes in plain language as you go.

This file is technical only. The repo is public, so keep business details out of it.

## How the site is built

- A static HTML site with no framework and no build step, plus a small PHP client portal. Redesigned on 2026-11-04; the purchased template it used before (jQuery, Bootstrap, GSAP, template images) is gone from the repo.
- Public pages sit in the repo root: `index.html`, `services.html`, `in-house-designer.html`, `about.html`, `contact.html`, `privacy-policy.html`, `terms.html`, `error.html` (the 404 page), plus `brand.html`, the brand sheet at `/brand` (not in the menu, not in the sitemap, `noindex`).
- `llms.txt` at the root is a Markdown summary of the public pages for AI assistants (the llmstxt.org convention): who the studio is, services, packages with prices, the in-house offer and how to get in touch. It repeats what the pages already say, nothing more. Update it whenever services, packages, prices or contact details change. It is not a page, so it is not in the sitemap, and `.txt` files are not cached by `.htaccess`.
- One stylesheet, `assets/css/sm.css`, and one script, `assets/js/sm.js` (menu, forms, the timeline reveal). No libraries. Fonts (Barlow Condensed and Barlow) come from Google Fonts.
- `DESIGN.md` at the root is the design system: colors (black and white, the logo's sky blue `#2988FC` for the in-house side and anything active, and a warm sun `#FFB42B` for the Build side), type scale, spacing, components (tiles, rows, marquee, plans, table), and the rules for motion and imagery. Read it before changing the look of anything. Its values are the CSS custom properties at the top of `sm.css`. `/brand` shows the same rules as a page.
- `portal/` is a PHP + MySQL client portal: login, client dashboard, documents, and admin (`portal/admin/`). Since 2026-11-04 every portal page links one shared stylesheet, `portal/assets/portal.css` (same tokens as the site, light design; bump its `?v=` when it changes), and the pages hold only markup. The portal uses `/assets/images/logo-sunny-monkeys-white.png` (as a CSS mask for the blue mark) and `/assets/images/fav.png` from the public assets. There is no PHP on Marco's Mac: push to a branch first and the `lint.yml` workflow runs `php -l` and `node --check` on it; merge into `main` only when it is green. Every portal script starts by requiring `portal/lib/core.php` (database connection, hardened session, `require_admin()` / `require_client()`, `csrf_field()` / `csrf_check()`, `h()` for escaping, flash messages, the sign-in throttle). Portal JavaScript lives in `portal/assets/portal.js` (`?v=1`); the pages hold markup only, no inline `<script>` or `onclick`, because the Content-Security-Policy refuses inline scripts. Its config files `portal/config/db.php` and `portal/config/mail.php` exist only on the server, never in Git (see `.gitignore`).

## Conventions and gotchas

- **Root font size is the browser default (16px), so `1rem = 16px`.** The old template's 10px rule is gone.
- **Header and footer are repeated in every page** (there are no includes). When you change the menu, the footer or the `<head>`, change all nine files the same way. The header is: brand, menu links, the filled "Book a call", then "Log in" alone at the far right (`.nav-login`, body font, hidden below 900px where the phone menu carries a "Client log in" link instead).
- **Drawings are inline SVG**, drawn in `currentColor` so they work on white and on black, with blue for finished or active parts and the gradient `url(#sm-grad)` (a `<linearGradient>` sits at the top of every page's body): the designer's week board (home and in-house hero), the "one designer, five brands" diagram, the immersion timeline, and the 24px service icons. Each board and diagram exists twice: a desktop drawing (`.board-d`, `.diagram-d`) and a phone drawing (`.board-m`, `.diagram-m`) that CSS swaps below 600px, because text inside an SVG scales with it and became unreadable on phones.
- **Motion:** the hero entrance and the board loop are CSS animations; elements with `data-reveal` (or `data-reveal="stagger"` for their children) come in once when `sm.js` adds `is-visible`; icons draw themselves thanks to `pathLength="1"` on every shape; the header gets `is-scrolled` after 24px. Everything respects `prefers-reduced-motion`. Keep reveals to grids, lists and diagrams, not to every paragraph.
- **Cache busting:** browsers and Cloudflare cache CSS and JS for a month (see `.htaccess`), so bump the `?v=` on the `sm.css` and `sm.js` links in all pages whenever you change them (`sm.css` is at `?v=6`, `sm.js` at `?v=4`, `portal.css` at `?v=3`, `portal.js` at `?v=1`). HTML is never cached.
- **Clean URLs:** `.htaccess` serves `/about` from `about.html` and 301-redirects any `*.html` URL to the clean one. Link with clean absolute paths (`/about`, `/services`, `/contact?package=growth`). A new page `name.html` is automatically available at `/name`; add it to `sitemap.xml`.
- **Writing style:** no em dashes in site copy (use commas, colons or periods). Headlines are uppercase Barlow Condensed; no eyebrow labels, no accent colors. Client-facing copy in English.
- Every page has its own `<title>`, meta description, canonical URL, Open Graph tags and the shared social image `assets/images/og-sunny-monkeys.png`. Keep them when you add pages.
- **Questions and answers** (`ul.faq` with `<details>`, on `/services` and `/in-house-designer`) exist twice in each page: the visible list and a `FAQPage` block of structured data in the `<head>`. Change both together, with the same wording. Answers only restate what the pages already promise.
- Local preview: any static server works, but clean URLs need one that maps `/about` to `about.html`.

## Deploying

- **Push to `main` and the site deploys itself.** `.github/workflows/deploy.yml` runs `php -l` on every PHP file (nothing deploys if one is broken), then calls the cPanel API: `VersionControl/update` pulls the repo on the server, and `VersionControlDeployment/create` runs `.cpanel.yml`, which copies the repo into `public_html`. It takes about 15 seconds after the job starts.
- **Always check the change on the live site afterwards,** e.g. `curl -s "https://sunnymonkeys.com/page?nc=$RANDOM"`. GitHub Actions has had outages where jobs never started.
- `.cpanel.yml` copies files but never deletes them on the server. Removing a file from the repo leaves it live until someone removes it on the server by hand (only Marco can do that, from his Mac or cPanel). The old template's files (`assets/css/style.css`, `assets/js/main.js`, `assets/images/*` and so on) are still there for that reason; nothing links to them.
- `.cpanel.yml` strips `.git`, `.github`, `.cpanel.yml`, `.gitignore`, `README.md`, `CLAUDE.md` and `DESIGN.md` out of `public_html`.
- Commit author: `Marco Mendoza <contact@sunnymonkeys.com>`.

## Security (as of 2026-11-09)

- `.htaccess` forces HTTPS and sends security headers on every response: a Content-Security-Policy (scripts only from this site and never inline; styles from this site and Google Fonts, inline `style=""` attributes allowed; fonts from Google Fonts; images from this site; forms and `fetch()` only to this site; never shown in a frame), HSTS, `nosniff`, `X-Frame-Options`, referrer and permissions policies. Adding a third-party script, font or embed means editing that policy first.
- `.htaccess` also refuses `.example`, `.md`, `.yml`, `.log`, `.sql`, `.bak`, `.sh`, `.env` and `.ini` files, `error_log` and anything starting with a dot; `portal/.htaccess` refuses `portal/config/` and `portal/lib/`; `portal/uploads/.htaccess` refuses everything (documents are served by `download.php`).
- Portal sessions: cookie `smportal`, HttpOnly, Secure, SameSite=Lax, strict mode, new id on sign-in, signed out after 4 hours idle, cookie cleared on sign-out. Every form carries a CSRF token (`csrf_field()`) and every POST handler calls `csrf_check()`; the login form uses `csrf_valid()` so it can show a friendly message. Messages after a redirect go through `flash_set()` / `flash_take()`, never the URL.
- Sign-in throttle: failed attempts are stored hashed in `login_attempts` (created automatically); 10 per connection or 5 per email within 15 minutes blocks sign-in for the rest of the window. The response takes the same time whether or not the email exists.
- Uploads must be real PDFs (`%PDF-` header plus `finfo`), under 10MB, and are stored as `doc_<random>.pdf`; `download.php` checks the stored name is a plain file inside `uploads/`.
- Queries use prepared statements with native prepares and select only the columns they use; admin changes to add-ons and requests are scoped to the client being viewed; numbers, dates and lengths are validated server-side. Errors are logged, never displayed.
- `portal/config/setup.php` was removed from the repo (outdated schema, default password). The copy on the server is blocked by `.htaccess` and should be deleted there.

## Contact form and newsletter

- Both forms post to `portal/inquiry.php` from `assets/js/sm.js` (plain `fetch`, form-encoded, plus `form=contact|newsletter` and `started=<page load time>`). The reply text is shown in the `.form-status` line under the form.
- Field names and option values are what the PHP validates (`INQUIRY_SERVICES` and `INQUIRY_START_OPTIONS` in `portal/lib/inquiries.php`). Choosing an In-House Designer option on the contact form shows a required "when would you like your designer to start" select (`#cf-start`); `inquiry.php` stores the answer as the first line of the message, so the `inquiries` table is unchanged. Buttons link to `/contact?package=<value>` to preselect an option.
- Every submission is saved to the MySQL table `inquiries` first (created automatically by `portal/lib/inquiries.php`). Contact messages are then emailed through the Titan mailbox by `portal/lib/mailer.php`, a small SMTP client with no libraries, using `portal/config/mail.php`.
- Spam handling: a hidden `website` trap field, submissions under 3 seconds after page load, and at most 5 submissions per hour per connection. Suspected spam is saved and flagged, never dropped.
- Admins see everything at `/portal/admin/inquiries.php` (messages, newsletter sign-ups, spam). If an email failed, it shows "Email not sent" with the reason.
- `portal/.htaccess` blocks direct access to `config/*.php` secrets and to `portal/lib/`.

## Open items (as of 2026-11-09)

- [ ] Marco sends a test message from /contact and confirms the email reaches contact@sunnymonkeys.com. If it doesn't arrive, the likely cause is HostGator blocking outgoing SMTP; the fallback is an HTTPS email API.
- [ ] Marco reviews the founder note on /about (written from the studio's positioning, not from an interview) and the "Where we are" facts.
- [ ] Marco resets the portal admin password himself (bcrypt hash into the `admins` table via phpMyAdmin).
- [ ] Make the repo private. This needs a read-only GitHub token in the server's git remote URL first (Marco does it in cPanel), then Marco flips the visibility, then test a deploy.
- [ ] Real numbers for the site, if Marco wants them (projects, clients). The made-up stats band is gone; the home page states commitments instead.
- [ ] Old template files and `service-1.html` are still on the server; harmless, but Marco can delete them from cPanel once he has a backup.
- [ ] The unused WordPress install (`wp-*`, `index.php`, `xmlrpc.php` in the web root) is blocked by `.htaccess` since 2026-11-09 but still on disk, like `public_html/portal/config/setup.php`. Marco deletes both from cPanel once backed up; then the WordPress rule in `.htaccess` can go.
- [ ] Marco tries the admin pages (clients, upload, client view, inquiries) after the 2026-11-09 hardening: every form now carries a token and the sign-in cookie changed, so everyone signs in again.
