# Core Extension Protocol – Gap Analysis (2025-11-17)

This report compares the current Multi-Tenant CMS extension runtime with the binding requirements described in `docs/core-extension-protocol-spec.md` (revision 2025-11-17). The assessment focused on platform-owned code inside `/app/Extensions`, related controllers/UI, schema definitions, and CLI tooling.

---

## Methodology

1. Reviewed the specification sections that govern lifecycle (§2, §4, §5), schema (§3), hooks/events (§6), configuration (§7), security (§8), per-extension contracts (§9), platform deliverables (§12), documentation/policies (§13), and admin UI expectations (§14).
2. Inspected the current implementation of the extension registry, manifest validation, settings service, controllers, and bootstrap files (PayFast + GTM) to determine actual capabilities.
3. Classified each gap by severity:
   - **Critical** – blocks compliance or introduces security/multi-tenant risk.
   - **Major** – required for feature completeness but not a blocking risk.
   - **Minor** – polish, documentation, or future-proofing improvements.

---

## Coverage Snapshot

| Area | Spec Reference | Current State | Notes |
| --- | --- | --- | --- |
| Extension discovery & metadata | §2, §4 | ✅ Registry scans `/app/Extensions/**/extension.json`, persists base fields (`slug`, `version`, `entry_point`). | Does not persist permissions, hook declarations, UI panel metadata, or compatibility flags required later in the flow. |
| Lifecycle execution | §5 | ⚠️ Install/upgrade/deactivate hooks exist and run via `ExtensionRegistry`. | Lacks centralized error handling, version gating, uninstall cleanup, and per-phase verification logs. |
| Extension settings | §3.2, §7 | ⚠️ Per-key storage exists with opt-in encryption metadata. | No validation schemas, dependency awareness, or admin UI scaffolding. |
| Event & command infrastructure | §6 | ⚠️ In-memory dispatcher + command registry created. | No canonical envelope metadata (org, actor, version), no persistence/retry/DLQ, and no cross-extension replay tools. |
| Routing surfaces | §4.3, §12.3 | ❌ Registry stores route intents but the core does not mount them onto `/admin`, `/org`, `/api`, or webhook prefixes. |
| Organization extension center | §12.2, §14.2 | ⚠️ Basic page listing extensions with manual PayFast/GTM forms. | Missing catalog filters, health data, audit badges, manifest-driven panels, and per-org availability gates. |
| Master admin console | §12.1, §14.1 | ❌ No dedicated UI for uploading extensions, approving org access, or enforcing org-toggle policies. |
| Diagnostics & telemetry | §12.7 | ❌ No per-extension log streams, metrics, or health indicators. |
| Extension SDK & CLI | §12.8 | ❌ No scaffolder, dry-run installer, or developer tooling. |
| Marketplace distribution | §12.9 | ❌ Not started. |
| Extension-specific contracts | §9.1–§9.6 | ❌ Only PayFast/GTM stubs exist; Hotel Inventory, Reservations, POS, Email/Docs remain unimplemented. |
| Developer documentation & policies | §13 | ⚠️ Spec available, but no developer portal, cookbooks, or submission workflow. |

---

## Detailed Gaps

1. **Manifest data fidelity (Critical, §4.1, §14.3).**
   - Permissions, hooks, route intents, and UI panel schemas are not persisted or validated. The registry accepts manifests but discards the majority of spec-mandated metadata, preventing the admin shell from auto-rendering panels or enforcing capability requirements.

2. **Routing orchestrator missing (Critical, §4.3, §12.3).**
   - Although `HookRegistry` collects route intents during activation, nothing binds them to the canonical admin/public/API/webhook surfaces. There is no middleware enforcing auth, tenancy, rate limits, or CSRF before invoking extension handlers.

3. **Lifecycle & status governance incomplete (Major, §5, §12.1).**
   - Install/upgrade runs but lacks version guards (min/max core version), signature/compliance checks, and post-phase verification. Status transitions (`installed` vs `active`) are not reflected per organization, leading to limited observability when activation fails.

4. **Event bus limitations (Major, §6).**
   - The dispatcher is in-memory only, offers no delivery guarantees, and omits the canonical envelope fields (organization, actor, trace ID, version). Extensions cannot replay events or inspect history, undermining cross-extension interoperability.

5. **Configuration UX + validation absent (Major, §7, §14.3).**
   - PayFast and GTM settings are hard-coded in controller logic instead of manifest-driven schemas. No automatic encryption flags, dependency checks, or organization-level validation screens exist.

6. **Admin experiences underpowered (Major, §12.1–§12.2, §14.1–§14.2).**
   - There is no master admin catalog for upload/approval, org availability matrix, or telemetry. The org center cannot show pending/locked states, health indicators, or manifest-defined panels.

7. **Diagnostics/telemetry absent (Major, §12.7).**
   - No metrics, log routing, or sanity checks, so platform operators cannot monitor extension health or last webhook times.

8. **Developer tooling & distribution missing (Major, §12.8–§12.9, §13).**
   - No CLI scaffolder, dry-run installers, developer portal, or submission workflow. This blocks third-party extension development entirely.

9. **Extension-specific implementations outstanding (Critical, §9).**
   - Hotel Inventory, Reservation, POS, Email/Docs, and the full GTM/PayFast feature sets remain unimplemented, leaving cross-extension workflows (CRM ↔ POS, PayFast IPNs, GTM data layer) incomplete.

10. **Security hardening gaps (Minor, §8).**
    - While CSRF appears on org forms, there is no standardized capability enforcement for extension routes, no encrypted secret storage outside the settings JSON envelope, and no secure storage path helper for extension files.

11. **Documentation gaps (Minor, §13).**
    - Aside from the spec, there is no certification checklist, event fixture catalog, or testing matrix for extension developers.

---

## Immediate Next Steps

1. Define a phased remediation plan (Phase 0–6) that sequences registry/refactoring work before building the individual extensions.
2. Update engineering instructions (`.github/copilot-instructions.md`) so every task references the active phase and cites the spec sections addressed.
3. Kick off Phase 1 by hardening manifest ingestion (persist metadata, validate permissions, and prep routing orchestrator data).

---

## Phased Implementation Plan

The following roadmap mirrors the protocol scope while enforcing strict "green before go" gates. Each phase closes a coherent set of gaps, produces verification artifacts, and explicitly references the governing spec sections.

### Phase 0 – Baseline Assessment (Complete)
- **Objective:** Capture the current state vs. spec (§2–§14) and freeze scope for the remaining work.
- **Deliverables:** This gap analysis, backlog of remediation items, and instruction updates.
- **Exit Criteria:** Stakeholders sign off on the documented gaps and phased approach.

### Phase 1 – Manifest & Registry Hardening
- **Spec References:** §2, §3.1, §4, §5, §14.3.
- **Goals:**
   - Persist full manifest metadata (permissions, hooks, route intents, UI panels, compatibility ranges) in `extensions` + auxiliary tables.
   - Enforce manifest validation against capability registry and route intent schema.
   - Add signature/compliance placeholders ahead of marketplace support.
   - Extend CLI (`cli/extensions.php`) with `sync`, `validate`, and `doctor` commands that surface manifest issues.
- **Exit Criteria:** Registry can materially represent every manifest attribute, and admin experiences can query that data without reading files.

### Phase 2 – Routing Orchestrator & Security Envelope
- **Spec References:** §4.3, §5 (activate/deactivate), §8, §12.3.
- **Goals:**
   - Build router middleware that mounts extension route intents under `/admin`, `/org/{org}/extensions/{slug}/public`, `/api/extensions/{slug}`, and `/api/extensions/{slug}/webhook`.
   - Inject capability checks, organization scoping, CSRF/rate limiting, and structured logging into every request.
   - Wire HookRegistry-provided handlers into this orchestrator and ensure activation/deactivation clears routes deterministically.
- **Exit Criteria:** Sample routes from PayFast/GTM manifest can respond via canonical URLs with proper auth + audit coverage.

### Phase 3 – Event Bus, Commands, & Telemetry
- **Spec References:** §6, §7 (dependency awareness), §12.4, §12.7.
- **Goals:**
   - Upgrade the in-memory dispatcher to a persistent event bus with envelope metadata (`organization_id`, `actor`, `trace_id`, versions) and retry/DLQ queues.
   - Provide inspection CLI/UI to replay or tail events per extension.
   - Formalize the command registry with scheduling hooks (cron + queue consumers) and tenant-aware execution contexts.
   - Emit metrics/log streams per extension and surface them in the admin console.
- **Exit Criteria:** Canonical events (e.g., `reservation.created`) can be published, consumed, retried, and audited with trace IDs, and telemetry dashboards reflect listener health.

### Phase 4 – Admin Experiences & Configuration Panels
- **Spec References:** §7, §12.1–§12.2, §14.
- **Goals:**
   - Deliver the master admin catalog with upload/signature validation, lifecycle buttons, org availability matrix, and delegate-toggle controls.
   - Rebuild the organization extension center to consume manifest-defined UI panels and validation schemas (JSON Schema renderer), including dependency warnings and health badges.
   - Implement configuration APIs that unify secret storage, validation, and audit logging.
- **Exit Criteria:** Both master admins and org admins can manage extensions solely through the standardized UI, with all forms generated from manifest schemas.

### Phase 5 – Developer Tooling & Distribution
- **Spec References:** §12.5, §12.8–§12.9, §13.
- **Goals:**
   - Ship the extension SDK + scaffolder CLI (manifest templates, lifecycle stubs, PHPUnit harness).
   - Publish developer portal docs: protocol spec, API reference, cookbooks, submission workflow, certification checklist.
   - Implement signed package ingestion plus compatibility checks inside the registry.
- **Exit Criteria:** Third parties can scaffold, package, and submit an extension ZIP that passes automated validation and appears in the catalog.

### Phase 6 – Flagship Extension Implementations
- **Spec References:** §9.1–§9.6.
- **Goals:**
   - Build the Hotel Inventory, Reservation, POS, PayFast, Email/Docs, and GTM extensions according to their detailed specs, leveraging the hardened platform services.
   - Ensure cross-extension workflows (reservation ↔ POS folios, PayFast payment updates, GTM enhanced conversions) operate end-to-end via events and shared configuration.
   - Provide verification documents (one per extension) plus automated tests covering lifecycle hooks, schema migrations, and event emissions.
- **Exit Criteria:** All flagship extensions run through install → configure → activate → transact flows in a demo tenant, with telemetry and documentation captured for each.
