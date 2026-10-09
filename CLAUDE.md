# sunnymonkeys.com

Website for Sunny Monkeys LLC, a small creative design studio. The owner is Marco. He's learning how the site works, so explain changes in plain language as you go.

This file is technical only. The repo is public, so keep business details out of it.

## How the site is built

- A static HTML site built on a purchased third-party template (jQuery, Bootstrap, GSAP), plus a small PHP client portal. No build step and no framework.
- Public pages sit in the repo root: `index.html`, `about.html`, `services.html`, `contact.html`, `privacy-policy.html`, `terms.html`, `error.html` (the 404 page).
- `portal/` is a PHP + MySQL client portal: login, client dashboard, documents, and admin (`portal/admin/`). Its config files `portal/config/db.php` and `portal/config/mail.php` exist only on the server, never in Git (see `.gitignore`).
- The template is licensed, so don't copy it into other projects or publish it as a template.

## Deploying

- **Push to `main` and the site deploys itself.** `.github/workflows/deploy.yml` runs `php -l` on every PHP file (nothing deploys if one is broken), then calls the cPanel API: `VersionControl/update` pulls the repo on the server, and `VersionControlDeployment/create` runs `.cpanel.yml`, which copies the repo into `public_html`. It takes about 15 seconds after the job starts.
- **Always check the change on the live site afterwards,** e.g. `curl -s "https://sunnymonkeys.com/page?nc=$RANDOM"`. GitHub Actions has had outages where jobs never started.
- `.cpanel.yml` copies files but never deletes them on the server. Removing a file from the repo leaves it live until someone removes it on the server by hand (only Marco can do that, from his Mac or cPanel).
- `.cpanel.yml` strips `.git`, `.github`, `.cpanel.yml`, `.gitignore`, `README.md` and `CLAUDE.md` out of `public_html`.
- Commit author: `Marco Mendoza <contact@sunnymonkeys.com>`.

## Conventions and gotchas

- **The template sets `html { font-size: 10px }`, so `1rem = 10px`** (1.6rem = 16px). Older custom styles assumed 16px and rendered tiny. Body text should be at least about 16px, labels at least 13px.
- **Shared fixes go in `assets/css/site.css`,** which every page loads right after `style.css`. Pages also have inline `<style>` blocks for page-specific styles.
- **Cache busting:** browsers and Cloudflare cache CSS and JS for a month (see `.htaccess`), so bump the `?v=` on the `<link>`/`<script>` tags whenever you change `site.css` or a JS file. HTML is never cached.
- **Clean URLs:** `.htaccess` serves `/about` from `about.html` and 301-redirects any `*.html` URL to the clean one. Link with clean absolute paths (`/about`, `/services`, `/contact?package=growth`). A new page `name.html` is automatically available at `/name`; add it to `sitemap.xml`.
- **Theming:** inner pages (`body.inner-page`) are always dark. The homepage follows the template's light/dark switch (light by default).
- **Writing style:** no em dashes in site copy (use commas, colons or periods). Black-and-white editorial look. Client-facing copy in English.
- Every page has its own `<title>`, meta description, canonical URL and Open Graph tags. Keep them when you add pages.

## Contact form and newsletter

- Both website forms post to `portal/inquiry.php` (JS in `assets/js/plugins/contact.form.js`).
- Every submission is saved to the MySQL table `inquiries` first (created automatically by `portal/lib/inquiries.php`). Contact messages are then emailed through the Titan mailbox by `portal/lib/mailer.php`, a small SMTP client with no libraries, using `portal/config/mail.php`.
- Spam handling: a hidden `website` trap field, submissions under 3 seconds after page load, and at most 5 submissions per hour per connection. Suspected spam is saved and flagged, never dropped.
- Admins see everything at `/portal/admin/inquiries.php` (messages, newsletter sign-ups, spam). If an email failed, it shows "Email not sent" with the reason.
- `portal/.htaccess` blocks direct access to `config/*.php` secrets and to `portal/lib/`.

## Open items (as of 2026-10-08)

- [ ] Marco sends a test message from /contact and confirms the email reaches contact@sunnymonkeys.com. If it doesn't arrive, the likely cause is HostGator blocking outgoing SMTP; the fallback is an HTTPS email API.
- [ ] Marco resets the portal admin password himself (bcrypt hash into the `admins` table via phpMyAdmin).
- [ ] Make the repo private. This needs a read-only GitHub token in the server's git remote URL first (Marco does it in cPanel), then Marco flips the visibility, then test a deploy.
- [ ] Homepage "stats band": replace the made-up numbers with a mix of real numbers and promises. Waiting on Marco's numbers.
- [ ] About page (currently "Coming Soon"): write a short founder story with Marco.
- [ ] Decide on one look site-wide (all dark vs. the homepage's light mode).
- [ ] Old leftover `service-1.html` is still on the server; the redirect in `.htaccess` makes it harmless.
