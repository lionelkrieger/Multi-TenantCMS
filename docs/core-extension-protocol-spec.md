# Core Extension Protocol Specification

_Last updated: November 17, 2025_

This specification is authoritative for **all current and future extensions**. It distills the requirements from the Hotel Inventory System, Reservation Layer, Point of Sale (POS), PayFast Payment Gateway, Email & Documentation, and Google Tag Manager (GTM) architecture briefs. No extension may diverge from these contracts without versioning this document.

---

## 1. Goals & Non-Negotiables

1. **Single install pipeline** – consistent lifecycle management (install/upgrade/activate/deactivate/uninstall).
2. **Multi-tenant isolation** – all code and storage scoped to `organization_id` with no data leakage.
3. **Event-first communication** – extensions talk via typed events instead of tight coupling.
4. **Security by default** – encrypted secrets, mandatory CSRF, role-based permissions, logging.
5. **Cross-extension interoperability** – CRM data sharing, reservation ↔ POS linking, PayFast updates, email access, GTM conversions.
6. **Uniform delivery surface** – all HTTP/UI touchpoints flow through core-managed routers; extensions cannot introduce bespoke top-level endpoints.

---

## 2. Extension Runtime Topology

```
/app
  /Extensions
    /<Vendor>
      /<Extension>
        extension.json
        bootstrap.php
        install.php
        upgrade.php
        uninstall.php
        src/**
        resources/**
        public/**
/public_html/extensions/<slug> (mirrored public assets)
```

*Discovery* happens by scanning `/app/Extensions/**/extension.json`. The manifest instructs the core where to find lifecycle scripts, what routes to mount, and which events/commands to wire.

Extensions never bootstrap themselves; they receive an `ExtensionContext` with the active `organization_id`, PDO connection, event dispatcher, logger, and filesystem handles.

---

## 3. Core Schema Requirements

### 3.1 `extensions`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | CHAR(36) PK | UUID v4 |
| `slug` | VARCHAR(150) UNIQUE | `vendor/name` (lowercase) |
| `name` | VARCHAR(150) | From manifest |
| `version` | VARCHAR(20) | SemVer |
| `description` | TEXT | Human readable |
| `author` | VARCHAR(150) | Vendor contact |
| `entry_point` | VARCHAR(255) | Relative path to `bootstrap.php` |
| `status` | ENUM(`installed`,`active`,`inactive`,`error`) | Core-managed |
| `created_at`,`updated_at` | DATETIME | Auto timestamps |

### 3.2 `extension_settings`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | CHAR(36) PK | UUID |
| `extension_id` | CHAR(36) FK | References `extensions` |
| `organization_id` | CHAR(36) FK | Tenant boundary |
| `key` | VARCHAR(150) | Namespaced (`payfast.credentials`) |
| `value` | JSON | Secrets encrypted via `App\Support\Encryptor` |
| `created_at`,`updated_at` | DATETIME | |
| UNIQUE (`extension_id`,`organization_id`,`key`) | | Ensures deterministic settings |

### 3.3 Extension Tables

- All tables created by an extension must be prefixed with the slug (`pos_categories`, `email_queue`, `documents`, `gtm_audit`).
- Migrations must be **idempotent** and **reversible**. Use `install.php` to create baselines and `upgrade.php` for SemVer bumps.
- Examples derived from specification docs:
  - POS: `pos_categories`, `pos_items`, `folios`, `folio_charges` (see §9.3)
  - Email: `email_queue`, `documents` (see §9.5)
  - Reservation enhancements: `reservations` table columns for dual payment flow (see §9.2)

---

## 4. Manifest & File Structure

### 4.1 `extension.json`

```json
{
  "slug": "platform/hotel-inventory",
  "name": "Hotel Inventory",
  "version": "1.2.0",
  "description": "Room type + loyalty-aware availability service",
  "entry_point": "bootstrap.php",
  "php": ">=8.1",
  "permissions": ["crm:read", "inventory:write"],
  "hooks": {
    "events": ["guest.updated", "reservation.created"],
    "commands": ["inventory.sync"],
    "routes": {
      "public": ["GET /inventory/{property}"],
      "admin": ["POST /inventory/room-type"]
    }
  }
}
```

Validation:

1. `slug` is unique and matches directory name.
2. `permissions` must exist in the global capability registry (deny by default).
3. `hooks.routes` declare route **intents** (method + relative path). The core router materializes them under canonical prefixes so every extension uses the same surface (no ad-hoc endpoints).

### 4.2 Required Files

| File | Responsibility |
| --- | --- |
| `install.php` | Bootstrap schema, seed defaults, copy assets |
| `upgrade.php` | Version-aware migrations |
| `uninstall.php` | Optional cleanup (drop tables, remove files) |
| `bootstrap.php` | Register service providers, event listeners, and describe route handlers the core should mount |
| `resources/views` | Blade/vanilla PHP templates (admin + public) |
| `resources/docs` | `setup.md`, `permissions.md`, `changelog.md`, `events/*.json` |
| `public/` | Static assets automatically mirrored to `/public_html/extensions/<slug>` |

### 4.3 Core-Managed Routing Surfaces

The core exposes **fixed** entry points so extensions never invent their own public URLs:

- Admin UI: `/app/extensions/{slug}/admin/*`
- Public UI (org-aware): `/org/{orgSlug}/extensions/{slug}/public/*`
- API: `/api/extensions/{slug}/*`
- Webhook receiver helper: `/api/extensions/{slug}/webhook`

During activation the extension registers controllers/handlers for these slots via `bootstrap.php`. The core performs capability checks, organization resolution, throttling, and CSRF validation before invoking extension logic.

---

## 5. Lifecycle Contract

```php
final class ExtensionContext
{
    public string $extensionId;
    public string $extensionSlug;
    public ?string $organizationId; // null = global
    public PDO $connection;
    public EventDispatcherInterface $events;
    public LoggerInterface $logger;
    public StorageInterface $storage;
    public ConfigRepository $config;
}
```

| Phase | Executed When | Obligations |
| --- | --- | --- |
| `install()` | First registration | Validate manifest, create tables, publish assets, seed default settings |
| `upgrade($from,$to)` | SemVer bump detected | Only run differential migrations between versions |
| `activate()` | Org toggles extension on | Register routes, schedule jobs, hydrate caches scoped to organization |
| `deactivate()` | Org disables extension | Unschedule jobs, revoke listeners, disable public hooks |
| `uninstall()` | Extension removed | Optional but recommended cleanup (tables, files, settings) |

Errors must throw `ExtensionException`. The core marks status `error` and surfaces logs to admins.

---

## 6. Event-Driven Communication

### 6.1 Envelope

```json
{
  "event": "reservation.created",
  "version": "2025-11-01",
  "organization_id": "org-123",
  "actor": {"type": "user", "id": "user-456"},
  "trace_id": "uuid",
  "data": { /* domain payload */ }
}
```

Extensions must version payloads and remain backward compatible inside a version family.

### 6.2 Canonical Events & Payloads

| Event | Producer | Consumers | Data Fields |
| --- | --- | --- | --- |
| `guest.created` / `guest.updated` | CRM + Loyalty service | Inventory, Email, GTM | `guest_id`, profile, loyalty tier, magic-link tokens |
| `reservation.draft.started` | Reservation UI | Inventory | `reservation_id`, `room_type_id`, `check_in`, `check_out`, hold expiry |
| `reservation.created` | Reservation engine | POS, Email, GTM, PayFast | Reservation schema (§9.2) |
| `reservation.payment.updated` | PayFast extension | POS, Email, CRM | `payment_method`, `payment_status`, `gateway_reference`, `amount` |
| `reservation.checked_in` / `reservation.checked_out` | Core | POS, Email, GTM | `folio_id`, timestamps |
| `folio.charge.added` | POS | Email, CRM, GTM | Charge schema (§9.3) |
| `document.generated` | Email & Docs | GTM | Document metadata, download URL |

### 6.3 Commands / Jobs

- `inventory.sync` – triggered hourly or on-demand to reconcile available units.
- `pos.close_folio` – invoked at checkout to finalize folio totals.
- `payfast.capture` – future support for capturing holds.

Commands run through the existing job bus and inherit multi-tenant context.

---

## 7. Configuration & Secrets

1. Use `extension_settings` for per-org configuration (e.g., PayFast merchant IDs, SMTP credentials, GTM container IDs).
2. Secrets must be encrypted before persistence and decrypted only within the extension runtime.
3. Provide admin UI forms with validation rules derived from specs:
   - PayFast: `merchant_id` alphanumeric, `merchant_key` encrypted, sandbox toggle, test transaction endpoint.
   - Email: SMTP host/port/encryption, sender address, DMARC/SPF checklist, “Send Test Email”.
   - GTM: Regex `/^GTM-[A-Z0-9]{7}$/`, enable toggle, enhanced conversion disclosure.

---

## 8. Security & Multi-Tenant Guardrails

1. **Authorization** – All admin routes call `Auth::authorizeCapability($permission)` using manifest-declared permissions.
2. **Organization Scope** – Controllers must resolve the active organization via domain or query (`resolve_organization_from_request`) and verify it matches requested resources.
3. **Database** – All queries filter by `organization_id`. Cross-org joins are prohibited.
4. **Secrets** – Stored encrypted; decrypted values never logged. Access limited to org admins.
5. **CSRF & Inputs** – All forms embed `CSRF::token()`, all text output sanitized, all IDs validated (`uuid`/regex).
6. **File Handling** – Files saved to `storage_path('extensions/<slug>/<org>')`, served via signed URLs (e.g., `/serve-pdf.php`).
7. **Logging** – `logger()` used for lifecycle events, webhook processing, offline POS sync, PayFast IPNs, email queue failures.
8. **Offline/Retry** – POS offline buffer and Email queue must implement retry with exponential backoff and limit (3 retries recommended).

---

## 9. Extension-Specific Contracts

### 9.1 Hotel Inventory & Loyalty Integration

- Owns room-type definitions, availability counts, and loyalty-aware pricing.
- Must expose APIs/events for `room_type.created`, `availability.updated`, and respond to `guest.updated` (to surface loyalty tier, available discounts).
- Provides `inventory.reserve_hold(reservation_id, room_type_id, check_in, check_out)` and `inventory.release_hold(...)` commands to the reservation engine.
- Stores loyalty program rules centrally so CRM, Reservation, and POS can share status. Benefit previews displayed by Reservation UI must consume the same service.

### 9.2 Reservation Extension (Dual Payment Flow)

- Extends `reservations` table per spec:

```
payment_method ENUM('pay_on_arrival','payfast_online')
payment_status ENUM('pending','paid','failed','refunded')
payfast_payment_id VARCHAR(50)
status ENUM('draft','confirmed','completed','cancelled')
```

- Draft records expire after 15 minutes to release inventory holds.
- Emits `reservation.created` immediately after confirmation with: guest identity, room type, property, check-in/out, pricing breakdown, payment method/status.
- If PayFast is enabled: create reservation, redirect to hosted payment, await IPN, then emit `reservation.payment.updated` (`paid`/`failed`).
- If PayFast disabled: reservation stays `payment_status = pending` until staff marks paid.

### 9.3 Point of Sale (POS) Extension

- Must create the tables defined in the specification (`pos_categories`, `pos_items`, `folios`, `folio_charges`). All tables include `organization_id` foreign keys.
- Auto-creates a folio when a reservation receives its first POS charge. Emits `folio.opened` and `reservation.folio_linked` events.
- Charges are append-only; voiding creates compensating entries.
- Offline mode: cache charges locally, replay when online, and log success/failure.
- Events:
  - `folio.charge.added`: includes `folio_id`, `reservation_id`, `item_id`, `category_name`, `quantity`, `unit_price`, `total_amount`, `charged_by`.
  - `folio.closed`: triggered at checkout to notify Email/Docs for final invoices.

### 9.4 PayFast Payment Gateway

- Stores credentials either at organization level or property override using `extension_settings` (encrypt merchant key). Backfill columns (`payfast_enabled`, etc.) on organizations/properties only as a transitional helper; the canonical source is the extension settings.
- Admin UI (Settings → Payment Methods) must include enable toggle, credential inputs, sandbox mode, test transaction button, and status indicator.
- Webhook/IPN endpoint: use the shared `/api/extensions/{slug}/webhook` route. Core resolves `{slug}` = `platform/payfast`, performs auth + throttling, then hands payload to the extension’s webhook handler which validates signature, merchant ID, amount, reservation ID, and logs every request.
- Emits `reservation.payment.updated` with `payment_method = payfast_online`, `payment_status = paid|failed|refunded`, `gateway_reference`, and `amount`.
- Provides service for Reservation extension to generate PayFast `return_url`, `cancel_url`, `notify_url`, and signed payloads.

### 9.5 Email & Documentation Extension

- Provides configuration for PHP mail, SMTP, or future API providers. Stores SMTP credentials encrypted per org.
- Enforces deliverability checklist (SPF, DKIM, DMARC, reverse DNS) via setup UI.
- Implements `email_queue` table and background worker to retry deliveries (max 3 attempts, statuses `pending|retrying|failed|sent`).
- Templates pull from org branding (logo, colors, footer). Extensions can register additional template partials.
- Generates PDFs via HTML → PDF pipeline, storing metadata in `documents` table and files under `storage/extensions/email-docs/<org>`.
- Events:
  - Consumes `reservation.created`, `reservation.payment.updated`, `folio.charge.added`, `loyalty.tier_changed`.
  - Emits `document.generated` with download URL, entity type, and hashed guest ID for GTM conversions.

### 9.6 Google Tag Manager (GTM) Extension

- Adds `gtm_container_id` + `gtm_enabled` settings per organization.
- Admin UI validates container ID format `/^GTM-[A-Z0-9]{7}$/` and displays enhanced conversion data availability.
- Injects GTM script only on public pages when enabled, using `htmlspecialchars()` to avoid script injection.
- Initializes `window.dataLayer` early and pushes structured events during the booking funnel:
  - `guest_identified`: hashed CRM data for enhanced conversions.
  - `room_selected`, `dates_selected`, `pricing_reviewed` with property + rate info.
  - `conversion`: final transaction payload with hashed PII and ecommerce fields.
- Must respect privacy settings (future consent banner integration). Logs every injection for audit.

---

## 10. Testing, Observability & Tooling

1. **Automated Tests** – Every extension ships PHPUnit coverage for lifecycle hooks, event listeners, and multi-tenant guards (`./vendor/bin/phpunit --group=extension`).
2. **Schema Dry-Run** – `install.php` and `upgrade.php` must succeed with `--dry-run` flag in CI.
3. **Event Fixtures** – Provide JSON fixtures under `resources/docs/events/` for each emitted event.
4. **Metrics** – Use `ExtensionMetrics::increment('payfast.ipn.success')`, `ExtensionMetrics::timing('email.queue.latency')`, etc., to feed platform dashboards.
5. **Logging** – Errors bubble through the core logger and surface in the admin audit trail. Sensitive payloads (PII, secrets) must be redacted.

---

## 11. Reference Payloads

### 11.1 `reservation.created`

```json
{
  "event": "reservation.created",
  "version": "2025-11-01",
  "organization_id": "org-123",
  "data": {
    "reservation_id": "res-456",
    "guest": {
      "guest_id": "guest-789",
      "email": "masked@example.com",
      "loyalty_tier": "Gold"
    },
    "room_type_id": "room-abc",
    "property_id": "prop-xyz",
    "check_in": "2025-12-01",
    "check_out": "2025-12-05",
    "nights": 4,
    "pricing": {
      "base_amount": 600.00,
      "discount_amount": 90.00,
      "final_amount": 510.00,
      "currency": "USD"
    },
    "payment_method": "pay_on_arrival",
    "payment_status": "pending"
  }
}
```

### 11.2 `reservation.payment.updated` (PayFast)

```json
{
  "event": "reservation.payment.updated",
  "version": "2025-11-01",
  "organization_id": "org-123",
  "data": {
    "reservation_id": "res-456",
    "payment_method": "payfast_online",
    "payment_status": "paid",
    "gateway": "payfast",
    "gateway_reference": "PF-9988",
    "amount": 510.00,
    "currency": "USD",
    "paid_at": "2025-11-17T10:05:00Z"
  }
}
```

### 11.3 `folio.charge.added`

```json
{
  "event": "folio.charge.added",
  "version": "2025-11-01",
  "organization_id": "org-123",
  "data": {
    "folio_id": "folio-789",
    "reservation_id": "res-456",
    "item_id": "item-222",
    "category_name": "Minibar",
    "quantity": 2,
    "unit_price": 25.00,
    "total_amount": 50.00,
    "charged_at": "2025-11-18T02:15:00Z",
    "charged_by": "user-555"
  }
}
```

### 11.4 `document.generated`

```json
{
  "event": "document.generated",
  "version": "2025-11-01",
  "organization_id": "org-123",
  "data": {
    "document_id": "doc-333",
    "entity_type": "reservation",
    "entity_id": "res-456",
    "document_type": "final_invoice",
    "title": "Reservation Invoice RES-456",
    "download_url": "https://tenant.example.com/documents/doc-333",
    "generated_at": "2025-11-18T03:00:00Z"
  }
}
```

---

By adhering to this protocol, every extension—Inventory, Reservations, POS, PayFast, Email/Docs, GTM, and future modules—can be installed, upgraded, and executed deterministically across every tenant while sharing data safely and predictably.

---

## 12. Core Platform Deliverables (What the Main App Provides)

To make this protocol workable without bespoke code paths, the core application team must ship and maintain the following services:

1. **Extension Registry Service** – scans `/app/Extensions/**/extension.json`, validates manifests, records metadata in `extensions`, runs lifecycle scripts, and exposes registry APIs to the admin UI.
2. **Organization Extension Manager UI** – shared admin screens that let org admins discover, install, activate, configure, and monitor extensions. Includes capability mapping, per-org status badges, and audit logs.
3. **Routing Orchestrator** – middleware that mounts extension intents onto the canonical admin/public/API/webhook prefixes, applies tenancy checks, rate limiting, CSRF, and permission enforcement before handing off to extension handlers.
4. **Event Bus + Hook Runner** – centralized dispatcher with retry, DLQ, tracing metadata, and tooling to inspect event history for each extension.
5. **Configuration API & Secrets Vault** – CRUD endpoints + UI for `extension_settings`, automatic encryption/decryption, validation helpers, and dependency awareness (e.g., PayFast requires Reservation extension enabled).
6. **Job Scheduler & Queue Integrations** – reusable cron + queue workers that extensions register with declaratively (`registerCron`, `registerQueueConsumer`).
7. **Diagnostics & Telemetry** – per-extension log streams, metrics dashboards, health checks, and sandbox/test-mode switches accessible from the admin UI.
8. **Extension SDK & CLI Scaffolder** – PHP package (autoloadable from Packagist or tarball) plus `php artisan extension:make`-style command that generates the required folder structure, manifest template, lifecycle scripts, and sample tests.
9. **Marketplace/Distribution Layer** – optional but recommended catalogue where trusted extensions are discoverable, digitally signed, and versioned; includes review workflow and compatibility matrix.

These foundation blocks guarantee that every extension hooks into the same surfaces without modifying the core source code, satisfying the “no special cases per extension” requirement.

---

## 13. Extension Developer Documentation & Policies

### 13.1 Documentation Delivery

To empower third parties who cannot view the core source, the platform team will publish a **Developer Portal** with:

- **Protocol Spec** (this document) – versioned, searchable, and downloadable as PDF/HTML.
- **API Reference** – autogenerated from PHPDoc/openAPI describing extension-facing services (event payloads, config APIs, routing helpers, storage interfaces).
- **Cookbooks & Samples** – end-to-end walkthroughs (e.g., “build a webhook-driven payment gateway”) plus runnable sample extensions hosted in a public repo.
- **CLI Reference** – documentation for the scaffolding/packaging CLI, including how to run local tests and package releases.
- **Testing Matrix** – guidance on the supported PHP versions, database engines, and core app versions.
- **Certification Checklist** – validation steps (security scan, tenancy tests, performance benchmarks) required before submission to the marketplace.

Documentation is published alongside every core release. Breaking protocol changes require bumping the spec version, highlighting migration guides, and providing deprecation timelines.

### 13.2 Policies for Extension Developers

1. **Source-Free Development** – all necessary APIs, events, schemas, and lifecycle hooks are documented. Developers are prohibited from relying on internal, undocumented classes.
2. **Version Compatibility** – extensions must declare the minimum/maximum core version they support. The registry prevents activation outside that range.
3. **Security Reviews** – submissions must pass automated scans (static analysis, dependency audit) plus optional manual review for marketplace inclusion.
4. **Testing Requirements** – provide PHPUnit + integration test results using the published core test harness; include CI badges or logs when submitting updates.
5. **Submission Workflow** – package extensions as signed ZIPs (or git tags) with manifest, changelog, and docs. Upload via the developer portal; the registry verifies signature + manifest before making it available.
6. **Support & SLAs** – developers must publish contact info, response-time expectations, and upgrade cadence. Critical vulnerabilities must be patched within 48 hours.
7. **Deprecation Handling** – when removing events/fields, provide at least one minor version overlap with warnings emitted via logs + developer portal notifications.
8. **Compliance & Privacy** – adhere to data-processing agreements; no exporting PII outside documented flows. Extensions handling payments or email must pass the additional compliance checklist.

By combining these core deliverables, documentation practices, and policies, extension teams can build fully functional modules without ever inspecting the proprietary core application.

---

## 14. Extension Administration UI

### 14.1 Master Admin Console (Global Control)

Only master admins can upload, approve, or globally enable extensions. The console (accessible under `/admin/extensions`) includes:

1. **Catalog view** – uses `ExtensionRegistry::all()` to show every discovered manifest, version, signature state, and health. Uploads happen through a signed ZIP form; on success the CLI `extensions sync` job is triggered and the manifest is validated before the record becomes “installable”.
2. **Lifecycle actions** – buttons for install/upgrade/uninstall call the registry service, which invokes lifecycle scripts (install, upgrade, uninstall). Status changes (active/inactive/error) are logged and broadcast to orgs.
3. **Org availability matrix** – master admins can whitelist which organizations (or org tiers) may see each extension. PayFast and GTM ship as “core extensions” and are marked as available for all orgs by default.
4. **Delegate toggle rights** – each extension row includes a “Org Admin Control” switch. When ON, every org admin can toggle that extension for their org; when OFF, only master admins can activate/deactivate it platform-wide. This setting is stored per extension in the registry (`extensions.allow_org_toggle` column) so it’s consistent for all orgs and avoids bespoke exceptions.
5. **Signature + compliance checks** – checksum, vendor signature, and policy acknowledgements live on this screen so third-party uploads can be approved before exposure to orgs.

No other role can access this console. All routes are protected by `Auth::authorizeCapability('extensions.manage')`, which only master admins possess.

### 14.2 Organization Extension Center (Org Admin Control)

Org admins reach `/org/{org}/extensions` where the UI shows:

- **Enabled view** – PayFast and GTM appear with toggle switches because their manifests include `"org_toggles": {"allow_enable": true}`. When toggled, the UI calls the Extension Settings API to set `enabled` plus any configuration keys (merchant IDs, GTM container ID). Audit trails capture the actor + timestamp.
- **No-org exceptions** – if the master console turns off “Org Admin Control” for an extension, the toggle disappears for *all* orgs simultaneously, forcing activation back through master admins. This keeps policy uniform and avoids per-org overrides.
- **Pending/locked view** – extensions that exist globally but aren’t approved for the org show as read-only with a “Contact master admin” message.
- **Health indicators** – per-extension cards surface status from telemetry (last webhook, queue depth, configuration errors).

The org center never uploads code; it only manages activation state and per-org configuration stored in `extension_settings`.

### 14.3 Plugin-Provided UI Panels (Agnostic Integration)

Extensions can declare their configuration/toggle UI via manifest metadata so the core admin shell renders them without custom code:

```json
{
  "hooks": {
    "ui_panels": [
      {
        "id": "settings",
        "title": "PayFast Settings",
        "component": "form",
        "schema": "resources/config/payfast-settings.json",
        "permissions": ["payments:manage"],
        "visible_to": ["master_admin","org_admin"],
        "org_toggle": true
      }
    ]
  }
}
```

- **`component`** can be `form`, `toggle`, or `custom-view`. The core UI has renderers for each, ensuring consistent styling.
- **`schema`** files define fields/validation rules (JSON Schema). The UI renderer automatically builds the form and maps submissions to `ExtensionSettingsService` keys. Secrets are marked with `{ "format": "password", "encrypted": true }` so values are encrypted when saved.
- **`visible_to`** restricts who sees the panel. PayFast and GTM specify `org_admin` so organization admins can toggle them, while upload/install panels omit this flag so only master admins see them.
- **`org_toggle`** indicates the panel should include the enabled/disabled switch for that org. At MVP, only PayFast and GTM set this true; other extensions can opt in as they become tenant-aware.

During activation the extension’s `bootstrap.php` registers its panel assets (language strings, icons) via the forthcoming `ExtensionDashboardProvider`. Because the admin shell simply iterates over declared panels, plugins can expose new UI without modifying the core source—fulfilling the agnostic requirement.
