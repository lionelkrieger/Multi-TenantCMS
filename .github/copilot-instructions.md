# Project Context
- **Stack:** PHP 8.1+, MySQL 8.0+, Nginx, Vanilla JS/jQuery.
- **Architecture:** Custom MVC (No frameworks like Laravel/Symfony). Multi-tenant SaaS.
- **Root Structure:** `/var/www/property-management/` (Application root).
- **Web Root:** `/public_html/` (Only entry points and assets).
- **Core Logic:** `/app/` (Controllers, Models, Config - OUTSIDE web root).

# Security & Multi-Tenancy (CRITICAL)
- **Database Access:** ALWAYS use PDO prepared statements. NEVER inject variables directly into SQL.
- **Multi-Tenancy:** EVERY database query involving data must filter by `organization_id`. Data isolation is paramount.
- **CSRF:** All POST requests must include and validate a CSRF token using `CSRF::validate()`.
- **Output Escaping:** Sanitize ALL user output using `htmlspecialchars()`.
- **File Uploads:** Validate MIME types (not just extensions). Store files in `/app/uploads/` (outside web root). Serve via `serve-logo.php` proxy.
- **Permissions:** Config files (`config/`) must be generated with 600 permissions.

# Extension Architecture Rules
- **Core Philosophy:** Core is "dumb". Business logic (car rentals, hotels, etc.) belongs in `app/extensions/`.
- **Hooks:** Use `HookSystem::executeHook('hook_name', $data)` for UI/Logic injection. Do not modify core Controller/View files for extension features.
- **Data Storage:** Extension data goes into extension-specific tables (e.g., `car_rental_vehicles`) or `extension_settings`.
- **Flexibility:** Use JSON columns (`extension_data`, `pricing_data`) for variable schema attributes in extensions.
- **Interface:** All extensions must implement `ExtensionInterface`.

# Code Style & Patterns
- **PHP:** Strict typing (`declare(strict_types=1);`). PSR-12 coding standards.
- **User Flows:** Do NOT use Sessions for multi-step flows (Checkout, etc.). Use the `user_flows` table with token persistence.
- **Error Handling:** Use `try/catch` blocks for all DB operations and external API calls. Log errors to `/app/logs/application.log`.
- **Routing:** Custom routing via `includes/domain_routing.php`. Handle Subdomain -> Organization mapping explicitly.
- **Frontend:** Keep JS modular. Use AJAX with CSRF headers for dynamic interactions.

# Database Guidelines
- **IDs:** Use UUIDs (VARCHAR 36) for all Primary Keys.
- **Foreign Keys:** Always define foreign keys with `ON DELETE CASCADE` or `SET NULL` as appropriate.
- **Indexing:** Index all `organization_id` columns and Foreign Keys.