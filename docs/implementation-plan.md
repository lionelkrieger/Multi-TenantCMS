# Multi-Tenant CMS Coding Plan

This rewrite focuses solely on the work we can perform inside the repository right now so we end up with a demonstrable solution we can zip up, upload to Ubuntu/Nginx, and run end-to-end. Each phase ends when the feature works locally via `php -S` or the built-in installer, so we always have a walkable demo before moving forward.

---

## Phase 1 – Make the Installer + Core Bootstrap Bulletproof

### Objectives
- Running `/install.php` on localhost creates all required tables, the master admin user, and blocks re-entry.
- Installer writes `app/config/database.php` with secure permissions and logs the outcome.

### Tasks
1. **Schema Completeness Audit**
   - Ensure `App\Install\SqlSchema` includes every table/column used in code (invites token/status, extension tables, user_flows, etc.).
   - Add regression test script `cli/check-schema.php` (optional) that compares DB columns vs. models.
2. **Installer Lock & Cleanup**
   - After success, rename `public_html/install.php` to `install.locked` (or set a flag file) and show “already installed” if revisited.
   - Add friendly message explaining how to re-enable (delete config + lock file).
3. **Config Permissions + Validation**
   - Installer should `chmod 0600` on the generated config and warn if it fails.
   - Add sanity check: when bootstrap runs and config file missing, display actionable error.
4. **Logging Hook**
   - On success/failure, log to `/app/logs/application.log` with timestamp + exception details.
5. **Manual Verification (End Marker)**
   - From a clean DB, run installer locally → confirm config file created, installer blocked on second visit, master admin login works, and log entry exists.

---

## Phase 2 – Master Admin Dashboard Must Be Functional

### Objectives
- Master admin can log in, view metrics, invite users, and see organizations without errors.

### Tasks
1. **Wire Up Navigation & Auth Guards**
   - Confirm layouts show Master nav only for `user_type = master_admin` and that each admin route calls `ensureMasterAdmin()`.
2. **Organization Listing + Creation**
   - Ensure `/admin/organizations.php` loads data via repositories, handles pagination/filtering, and persists new orgs (with validation + CSRF).
   - Populate success/error flashes.
3. **User + Invite Management**
   - Finish `/admin/users.php` features: filtering, pagination, invite issuing/revoking with CSRF, success flash, and validation.
4. **Data Stubs**
   - Seed sample data script `cli/seed.php` to create one org, properties, and two users for easy demos.
5. **Manual Verification (End Marker)**
   - Run built-in PHP server (`php -S localhost:8000 -t public_html`).
   - Log in, navigate to admin dashboard, create an org, issue an invite, see pending invite list update.

---

## Phase 3 – Tenant Dashboard & Public Views Ready

### Objectives
- Organization admins/employees can log in, see their dashboard, list properties, and render public property pages.

### Tasks
1. **Org Routing Helpers**
   - Expand `domain_routing.php` to accept explicit `?org_id` for local testing and fall back to custom domain lookup.
2. **Organization Dashboard/View Enhancements**
   - Flesh out `views/org/dashboard.php` with real stats (property count, pending invites, etc.).
3. **Property CRUD Basics**
   - Add create/edit/delete forms for properties (CSRF-protected) tied to `PropertyService`.
4. **Public Property Pages**
   - Verify `/org/property/view.php`, `/checkout.php`, `/confirmation.php` use resolved org + property data and fail gracefully when not found.
5. **Manual Verification (End Marker)**
   - Using seed data, switch to org context, add a property, visit the public view, run through checkout/confirmation stubs.

---

## Phase 4 – Branding & Asset Delivery (Repo-Only)

### Objectives
- Admins can set colors/logo via the repo, assets stored under `/app/uploads`, and served via proxy script.

### Tasks
1. **Upload Endpoint**
   - Build POST handler (likely in `OrganizationSettingsController`) that saves uploaded logo after MIME validation.
   - Store references on organization record.
2. **Serve Logo Proxy**
   - Implement `public_html/serve-logo.php` to stream files, validate `organization_id`, and deny direct filesystem access.
3. **Apply Styles**
   - Update base layout + CSS to read branding fields and apply as CSS variables.
4. **Manual Verification (End Marker)**
   - Upload sample logo/color, see changes reflected on both admin + public pages; direct file URL should 403.

---

## Phase 5 – Registration Gatekeeper Within Repo

### Objectives
- Public registration writes pending org/user records; master admin approves inside the app; login blocked until approval.

### Tasks
1. **Registration Form Update**
   - Extend `/views/auth/register.php` + controller to capture org data and set statuses to `pending`.
2. **Pending State Enforcement**
   - `AuthService::attempt` returns error if user status != `active`.
3. **Admin Approval UI**
   - Add panel to master dashboard listing pending orgs with Approve/Deny buttons that update statuses.
4. **Manual Verification (End Marker)**
   - Register new tenant, log sees pending message, approve via admin UI, login now succeeds.

---

## Phase 6 – Soft Delete & Cleanup Jobs (Repo Scripts)

### Objectives
- Soft delete organizations inside codebase; CLI scripts handle cleanup when run manually.

### Tasks
1. **Schema Update**
   - Add `scheduled_for_deletion_at` to organizations via migration + model updates.
2. **Delete Flow**
   - Provide button to schedule deletion, show warning banner.
3. **Cleanup Script**
   - CLI command `php cli/cleanup_deleted_orgs.php` that purges expired orgs; log actions.
4. **Manual Verification (End Marker)**
   - Schedule delete, confirm banner, run script (after adjusting timestamp) and verify DB rows removed.

---

## Phase 7 – Security Polish (Repo-Focused)

### Objectives
- CSRF tokens everywhere, audit logging for critical actions, optional rate-limiting docs.

### Tasks
1. **CSRF Sweep**
   - Check every form/POST controller; add `CSRF::validate()` and 403 fallback where missing.
2. **Audit Logger Helper**
   - Add `App\Support\AuditLogger` that writes JSON lines to `/app/logs/application.log`.
   - Invoke on login attempt, registration, invite issue/revoke, org approval, deletion, flow events.
3. **Sample Rate-Limit Config**
   - Document recommended Nginx snippets in `docs/deployment.md`; no server edits required now.
4. **Manual Verification (End Marker)**
   - Intentionally submit form without token (expect 403) and confirm audit log entries exist for key events.

---

## Final Demo Checklist
1. Run installer on local MySQL; confirm lock and logs.
2. Seed sample data; login as master admin.
3. Create organizations, issue invites, and manage users.
4. Register new tenant → approve → login as org admin.
5. Configure branding + upload logo; verify public pages.
6. Create properties and walk through public detail → checkout → confirmation.
7. Soft delete org, run cleanup script.
8. Review `/app/logs/application.log` for installer, approvals, invites, flow events.

Once every step above works in the repository using the built-in PHP server and CLI scripts, the codebase is ready to be zipped and uploaded to Ubuntu/Nginx—no additional coding required before deployment.
