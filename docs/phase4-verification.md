# Phase 4 Verification – Branding & Asset Delivery

This checklist proves the repo-only branding workflow works end-to-end before moving on to Phase 5. Run it against a local environment using the built-in PHP server (`php -S localhost:8000 -t public_html`).

---

## Prerequisites
- One organization with at least a single admin user (seed data works).
- Authenticated session as an organization admin.
- A square PNG or SVG logo under 2&nbsp;MB.
- Browser dev tools handy for verifying CSS variables and network responses.

---

## 1. Upload Endpoint
1. Visit `/org/settings.php` in the browser (include `?id={org_id}` locally if needed).
2. In **Branding**, choose the logo file, update primary/secondary/accent colors, and optionally set brand font + custom CSS.
3. Submit the form.

✅ Expected:
- Success flash appears.
- Logo preview refreshes with cache-busting query string.
- Database `organizations` row reflects new `logo_path`, color values, and optional CSS/font fields (confirm using MySQL client or CLI tinker script).

---

## 2. Serve Logo Proxy
1. Right-click the logo preview → open image in new tab. The URL should be `/serve-logo.php?org={org_id}&v={timestamp}`.
2. Copy the fully qualified path returned by the database (e.g., `/app/uploads/org_1/logo.png`) and attempt to access it directly via the browser or `curl`.

✅ Expected:
- Proxy request returns `200 OK`, correct `Content-Type`, and `Cache-Control: max-age=3600` headers.
- Direct file request responds with `403` (web server blocks access under `app/`).
- Changing the `org` parameter to another org you do **not** control returns `404`.

---

## 3. Layout & CSS Variables
1. Navigate through both admin (`/org/dashboard.php`) and public (`/org/property/view.php?id=...`) pages.
2. Inspect `<header>` and buttons to ensure brand colors/fonts are applied.
3. Use dev tools → Elements tab → check `:root` to confirm CSS variables:
   - `--brand-primary`
   - `--brand-secondary`
   - `--brand-accent`
   - `--brand-font`
4. Disable custom CSS in settings, save, and confirm the layout reverts to defaults.

✅ Expected:
- Header background and CTA buttons match selected colors.
- Body text uses custom font when configured.
- Removing custom CSS resets to fallback palette without page errors.

---

## 4. Regression & Edge Cases
1. Upload an oversized (>2&nbsp;MB) or unsupported MIME file (e.g., `.exe`).
   - Expect validation error explaining allowed formats.
2. Remove the logo (check "Remove logo" if provided or upload new blank) and save.
   - Proxy should start returning `404`; layout hides image gracefully.
3. Submit form without CSRF token (use dev tools → delete token input before submit).
   - Expect `403` response and log entry in `app/logs/application.log` if auditing enabled.

---

## Sign-off
Complete Phase 4 when all ✅ expectations pass for both an admin and public visitor session. Capture before/after screenshots of the dashboard header plus the dev tools view of CSS variables for future release notes.
