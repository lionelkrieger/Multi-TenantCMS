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