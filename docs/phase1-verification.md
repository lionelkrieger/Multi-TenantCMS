# Phase 1 Verification – Manifest & Registry Hardening

This checklist validates the deliverables for Phase 1 (Manifest & Registry Hardening) from `docs/extension-protocol-gap-analysis-2025-11-17.md`.

## Prerequisites
- Database configured via `/install.php` (generates `app/config/app.php` + `database.php`).
- CLI access to run migrations and extension commands.

## Steps
1. **Schema baseline**
   - Run `php cli/migrations/20251117_create_extensions_tables.php` (idempotent) followed by `php cli/migrations/20251117_extend_extension_manifest_metadata.php`.
   - Expected: new tables `extension_permissions`, `extension_hooks`, `extension_routes`, `extension_panels` exist and appear in `php cli/check-schema.php` output.
2. **Manifest sync**
   - Execute `php cli/extensions.php sync`.
   - Expected: each manifest validates, metadata persisted, `extensions.manifest_checksum` updated.
3. **CLI doctor**
   - Run `php cli/extensions.php doctor`.
   - Expected: report per extension with matching manifest/database counts and status `OK`.
4. **Spot check data**
   - Query tables (e.g., `SELECT * FROM extension_routes;`) to confirm stored route metadata matches manifest declarations.

## Current Repo Status
- Automated linting of modified PHP files: ✅ (`php -l` on registry, validator, model, CLI, capability registry, migration).
- `php cli/extensions.php sync` currently blocked because the demo environment has no generated database config. Once the installer runs, re-run the sync + doctor commands to complete verification.

Record the command outputs (or screenshots) when running in a configured environment to finalize the phase.
