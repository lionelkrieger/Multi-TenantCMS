# Deployment Checklist

## 1. Dependencies
- PHP 8.1 with PDO MySQL, mbstring, intl, and cURL extensions
- Composer dependencies (currently only autoloader) via `composer install`
- MySQL 8.0 with an application user that can run migrations/installer
- Nginx (or compatible) configured so `/public_html` is the web root

## 2. Initial Install
1. Copy the repository to `/var/www/property-management`
2. Set the document root to `/var/www/property-management/public_html`
3. Run the web installer at `https://your-domain/install.php`
4. Confirm that `app/config/database.php` is generated with `0600` permissions

## 3. Post-Install Migration (Invites)
Older databases created before November 2025 do **not** include the `token` and `status` columns required by the master-admin invite flow. Run the migration below before issuing invites:

```powershell
cd "c:\Users\JoeBomb\My Projects\Mult-TenantCMS"
php cli\migrations\20251117_add_user_invite_token_status.php
```

The script is idempotent—it only adds missing columns/indexes.

## 4. Verification Steps
- `php -l` across the repository or run `composer run lint` (script TBD)
- Create a test organization via `/admin/organizations.php`
- Issue an invite via `/admin/users.php` and confirm the row is stored with a token
- Verify that public property pages resolve for the organization domain or querystring context

## 5. Pending Follow-Ups
- Build invite acceptance endpoint and notification email
- Flesh out domain routing for subdomain tenants and SSL automation
- Add front-end assets under `public_html/assets/css` for production styling
