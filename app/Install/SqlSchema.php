<?php

declare(strict_types=1);

namespace App\Install;

final class SqlSchema
{
    /**
     * @return string[]
     */
    public static function statements(): array
    {
        return [
            <<<SQL
            CREATE TABLE IF NOT EXISTS users (
                id VARCHAR(36) PRIMARY KEY NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                name VARCHAR(255) NULL,
                organization_id VARCHAR(36) NULL,
                user_type ENUM('master_admin','org_admin','employee','user') DEFAULT 'user',
                status ENUM('active','unassigned','deleted') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            SQL,
            <<<SQL
            CREATE TABLE IF NOT EXISTS organizations (
                id VARCHAR(36) PRIMARY KEY NOT NULL,
                name VARCHAR(255) NOT NULL,
                created_by VARCHAR(36) NOT NULL,
                logo_url VARCHAR(512) DEFAULT NULL,
                primary_color VARCHAR(7) DEFAULT '#0066cc',
                secondary_color VARCHAR(7) DEFAULT '#f8f9fa',
                accent_color VARCHAR(7) DEFAULT '#dc3545',
                font_family VARCHAR(50) DEFAULT 'Roboto, sans-serif',
                show_branding BOOLEAN DEFAULT TRUE,
                custom_css TEXT DEFAULT NULL,
                custom_domain VARCHAR(255) DEFAULT NULL,
                domain_verified BOOLEAN DEFAULT FALSE,
                domain_verification_token VARCHAR(64) DEFAULT NULL,
                domain_verified_at TIMESTAMP NULL,
                ssl_certificate_status ENUM('none','pending','active','failed') DEFAULT 'none',
                ssl_certificate_expires TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            SQL,
            <<<SQL
            CREATE TABLE IF NOT EXISTS properties (
                id VARCHAR(36) PRIMARY KEY NOT NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                address VARCHAR(512),
                organization_id VARCHAR(36) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
                INDEX idx_org_id (organization_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            SQL,
            <<<SQL
            CREATE TABLE IF NOT EXISTS user_property_access (
                id VARCHAR(36) PRIMARY KEY NOT NULL,
                user_id VARCHAR(36) NOT NULL,
                property_id VARCHAR(36) NOT NULL,
                assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
                UNIQUE KEY unique_user_property (user_id, property_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            SQL,
            <<<SQL
            CREATE TABLE IF NOT EXISTS user_invites (
                id VARCHAR(36) PRIMARY KEY NOT NULL,
                email VARCHAR(255) NOT NULL,
                organization_id VARCHAR(36) NULL,
                inviter_user_id VARCHAR(36) NOT NULL,
                invite_type ENUM('org_admin','employee','user') DEFAULT 'user',
                token VARCHAR(128) NOT NULL,
                status ENUM('pending','accepted','revoked','expired') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NULL,
                FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
                FOREIGN KEY (inviter_user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_user_invites_email (email),
                INDEX idx_user_invites_status (status),
                INDEX idx_user_invites_token (token)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            SQL,
            <<<SQL
            CREATE TABLE IF NOT EXISTS employee_org_assignments (
                id VARCHAR(36) PRIMARY KEY NOT NULL,
                employee_user_id VARCHAR(36) NOT NULL,
                organization_id VARCHAR(36) NOT NULL,
                assigned_by VARCHAR(36) NOT NULL,
                assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (employee_user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
                FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_employee_org (employee_user_id, organization_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            SQL,
            <<<SQL
            CREATE TABLE IF NOT EXISTS user_flows (
                id VARCHAR(36) PRIMARY KEY NOT NULL,
                token VARCHAR(64) NOT NULL UNIQUE,
                org_id VARCHAR(36) NOT NULL,
                property_id VARCHAR(36) NOT NULL,
                user_id VARCHAR(36) NULL,
                current_step VARCHAR(50) NOT NULL DEFAULT 'actions',
                completed_steps JSON DEFAULT NULL,
                expires_at TIMESTAMP DEFAULT (DATE_ADD(NOW(), INTERVAL 1 HOUR)),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE CASCADE,
                FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            SQL,
            <<<SQL
            CREATE TABLE IF NOT EXISTS extensions (
                id VARCHAR(36) PRIMARY KEY NOT NULL,
                slug VARCHAR(150) NOT NULL UNIQUE,
                name VARCHAR(150) NOT NULL,
                display_name VARCHAR(150) NOT NULL,
                version VARCHAR(20) NOT NULL,
                author VARCHAR(150) NULL,
                description TEXT,
                homepage_url VARCHAR(255) NULL,
                entry_point VARCHAR(255) NOT NULL,
                manifest_path VARCHAR(255) NOT NULL,
                status ENUM('installed','active','inactive','error') DEFAULT 'inactive',
                allow_org_toggle BOOLEAN DEFAULT FALSE,
                requires_core_version VARCHAR(20) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE INDEX idx_extensions_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            SQL,
            <<<SQL
            CREATE TABLE IF NOT EXISTS extension_settings (
                id VARCHAR(36) PRIMARY KEY NOT NULL,
                extension_id VARCHAR(36) NOT NULL,
                organization_id VARCHAR(36) NOT NULL,
                settings JSON NOT NULL,
                enabled BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (extension_id) REFERENCES extensions(id) ON DELETE CASCADE,
                FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
                UNIQUE KEY unique_org_extension (organization_id, extension_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            SQL,
            <<<SQL
            ALTER TABLE users
                ADD CONSTRAINT fk_users_organization
                FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL;
            SQL,
        ];
    }
}
