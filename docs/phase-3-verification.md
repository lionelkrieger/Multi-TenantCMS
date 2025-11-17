# Phase 3 – Tenant Dashboard & Public Views Verification

This log explains how to confirm every Phase 3 objective on a clean checkout of the repository. Follow the steps below after completing the installer.

## Prerequisites

1. Run the platform installer (`public_html/install.php`) against a local MySQL instance.
2. Seed demo data for convenience:
   ```powershell
   cd "c:\Users\JoeBomb\My Projects\Mult-TenantCMS"
   php cli\seed.php
   ```
3. Start the local web server:
   ```powershell
   php -S localhost:8000 -t public_html
   ```

## 1. Org Routing Helpers

1. Visit `http://localhost:8000/org/dashboard.php` while logged in as the `owner@demo.local` user created by `cli/seed.php`.
2. Confirm the dashboard loads without needing `?id=`. This exercises:
   - Domain -> org lookup (`find_organization_by_host`).
   - Query parameter fallback (`?org_id` or `?id`).
   - New session fallback (`resolve_authenticated_user_organization`).

## 2. Org Dashboard Metrics

1. On `/org/dashboard.php`, verify:
   - **Active Properties** shows the seeded count (2 by default).
   - **Team Members** equals the number of active org users (1 initially).
   - **Pending Invites** matches outstanding invites (should start at 0; issue an invite from `/org/users.php` to see it increment).
2. Scroll down and confirm:
   - Recent Properties list matches the two seeded properties with working "Edit" links.
   - Pending invitations table lists any invites you issue.
   - Recent team members shows your org admin (and any employees you add).

## 3. Property CRUD Basics

1. Navigate to `/org/properties.php` (or use the shortcut link on the dashboard header).
2. Create a new property via **Add property**.
3. Edit the new property and change the description.
4. Delete the property using the inline delete form.
5. Observe flash messages for each action (`created`, `updated`, `deleted`) and verify CSRF protection by resubmitting the delete form with an old token (should redirect with `error=invalid_csrf`).

## 4. Public Property Pages

1. Visit the search page: `http://localhost:8000/search.php?org=<ORG_ID>`.
   - Confirm listings render with organization branding text.
   - Use the search bar to filter by property name.
2. Click into a listing to load `/org/property/view.php?org=<ORG_ID>&property=<PROPERTY_ID>`.
   - Check the action button routes to `/org/property/actions.php` with matching IDs.
3. Follow the CTA to `/org/property/actions.php`, `/org/property/checkout.php`, and `/org/order/confirmation.php` to ensure each page resolves the same organization + property pair and shows the stub messaging.
4. Visit `/org/property/view.php` with an invalid property ID and confirm the "Property not available" screen appears with a link back to search.

## 5. Optional Extensions

- Use different hosts (e.g., add `demo.localhost` to your hosts file) and map it to an organization by setting `custom_domain` + `domain_verified` in the database. Confirm `resolve_organization_from_request()` picks it up without query params.
- Capture screenshots of each stage and attach them to your delivery package for stakeholders.

## Result

All checks above should pass without code changes. If any step fails, log the issue in `docs/implementation-plan.md` and file a ticket before moving to Phase 4.
