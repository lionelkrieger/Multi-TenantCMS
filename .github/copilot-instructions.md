# Core Extension Protocol Specification

## Context
We have a working multi-tenant SaaS core system with users, organizations, properties, and master admin capabilities. We have also designed comprehensive specifications for multiple extensions:

- Hotel Inventory System (room types, amenities, availability)
- Point of Sale (folio charges, categories, items)  
- PayFast Payment Gateway (online payments, IPN handling)
- Email & Documentation System (transactional emails, PDF generation)
- Google Tag Manager (analytics, enhanced conversions)

However, these extensions don't exist yet, and we need to define the **standardized protocol** that ensures they will integrate seamlessly with the core system and with each other.

## Purpose
This document defines the mandatory interface between the core application and all future extensions. It ensures:
- Consistent installation and activation across all extensions
- Secure, multi-tenant data isolation 
- Reliable communication between extensions
- Predictable lifecycle management
- Unified data layer for analytics and conversions
- Clear upgrade and versioning paths

## Key Requirements

### 1. Core System Changes Required
The core system must be refactored to support:
- Extension registration and discovery via `/app/extensions/` directory scanning
- Extension metadata storage in `extensions` and `extension_settings` tables
- Standardized extension lifecycle methods (install, uninstall, activate, deactivate)
- Event-driven hook system for cross-extension communication
- Organization-scoped extension activation (per-org enable/disable)
- Unified data layer for enhanced conversions and analytics

### 2. Extension Development Standards
Every extension MUST adhere to:
- Required file structure (`extension.json`, `install.php`, etc.)
- Manifest format with hooks, routes, permissions
- Security practices (no raw script input, prepared statements, output escaping)
- Multi-tenancy requirements (all data scoped to `organization_id`)
- Public vs admin context separation
- Version compatibility declaration

### 3. Communication Protocol
Extensions communicate through:
- Core-provided Hook System (event-driven, not direct calls)
- Standardized data contracts for cross-extension data
- Organization context preservation in all operations
- API endpoints for safe data access (not direct database queries)

### 4. Security & Compliance
All extensions must:
- Respect organization boundaries (no cross-org data access)
- Store sensitive data encrypted
- Follow least-privilege principles
- Validate all inputs and escape all outputs
- Support secure file handling outside web root

### 5. Integration Points
The protocol must support the specific integration needs identified in our extension specs:
- CRM data sharing for guest identification across all extensions
- Reservation → POS folio creation and linking
- PayFast payment status updates to reservation records
- Email system access to reservation and folio data for confirmations
- GTM data layer population with guest, reservation, and transaction data

## Output Format
Generate a comprehensive markdown specification that serves as the single source of truth for all future extension development. Include concrete examples, required database schema changes, file structure templates, and validation rules.

## Phase Roadmap (Authoritative 2025-11-17)

All extension work must follow the roadmap defined in `docs/extension-protocol-gap-analysis-2025-11-17.md`. The phases are:

0. **Baseline Assessment** – maintain the gap analysis and keep it current.
1. **Manifest & Registry Hardening** – persist full manifest metadata, capability validation, and CLI doctor tooling.
2. **Routing Orchestrator & Security Envelope** – mount admin/public/API/webhook surfaces with tenancy/auth enforcement.
3. **Event Bus, Commands, & Telemetry** – ship canonical event envelopes, persistence, retries, and health dashboards.
4. **Admin Experiences & Config Panels** – deliver master/org UIs powered by manifest-defined schemas and settings APIs.
5. **Developer Tooling & Distribution** – release the SDK, scaffolder CLI, and submission workflow/docs.
6. **Flagship Extension Implementations** – build Hotel Inventory, Reservation, POS, PayFast, Email/Docs, GTM per §9.

Do not advance to a subsequent phase until the current one has verified schema/code/tests/docs artifacts.

## Scope Guardrails (Read Before Every Task)

- **Stay extension-focused.** Any new work must directly advance the Core Extension Protocol (manifest validation, lifecycle hooks, routing orchestrator, event bus, configuration vault, etc.). Do not revisit unrelated core features unless the user explicitly says so.
- **No surprise rewrites.** If a change requires touching existing core code, keep edits minimal and strictly in service of extension readiness (e.g., adding hooks, exposing context, wiring registries). Avoid redesigning or re-styling parts of the app just because you spot issues.
- **Confirm scope alignment.** When a request seems outside extension enablement, pause and ask for confirmation before proceeding. Default assumption: the core app is considered “ready”; our job is to make it extension-ready.
- **Prefer incremental protocol milestones.** Organize work by the phase checklist (0–6). Document what phase you’re on, what remains, and resist jumping to other enhancements until the current phase is complete.
- **Surface blockers immediately.** If extension work depends on missing context or conflicting requirements, call it out and request direction instead of filling the gap by broad redevelopment.

## Execution Discipline

To guarantee we never drift away from the Core Extension Protocol:

1. **Phase tracker required.** Mirror the Phase 0–6 checklist from `docs/extension-protocol-gap-analysis-2025-11-17.md` (derived from the core spec) in the active todo list. Explicitly mark which phase is in progress and do not start another until the current phase’s acceptance criteria (schema, code, tests, docs, verification) are complete.
2. **Spec-first planning.** Before writing code, cite the exact section(s) from the protocol spec that authorize the work (e.g., §3.2 schema, §5 lifecycle). Summaries and PR notes must point back to those sections.
3. **Scope gate on edits.** Any change to existing core files must include a short justification referencing the extension phase/requirement it serves. If a requested change seems outside that scope, pause and confirm with the user instead of proceeding.
4. **Verification artifacts.** Each finished phase must produce a brief verification document/checklist (like `docs/phase4-verification.md`) plus any supporting tests or CLI commands proving the phase is done.
5. **Stoplight reporting.** After each working session, report which phase is green (done), yellow (in progress), or red (blocked) so stakeholders can see progress against the extension roadmap at a glance.